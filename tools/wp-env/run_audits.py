#!/usr/bin/env python3
"""Declared wp-env audit execution, complete logs, and explicitly paired DB snapshots."""
from __future__ import annotations

import argparse
from datetime import datetime, timezone
import fnmatch
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time
import uuid

ROOT = Path(__file__).resolve().parents[2]
PLUGINS = ROOT / "products/wordpress/plugins"
MANIFEST = Path(__file__).with_name("audits.json")
SUMMARY = [
    re.compile(r"== (?P<checks>\d+) checks, (?P<failed>\d+) failed =="),
    re.compile(r"PASS: (?P<checks>\d+) (?:[A-Za-z -]+ )?checks"),
    re.compile(r"PASS [A-Za-z0-9_.-]+ (?P<checks>\d+)"),
]
PASS_CHECK = re.compile(r"^\s*\[PASS\](?:\s|$)")
FAILURE = re.compile(r"^\s*\[FAIL\](?:\s|$)|^(?:\[[^\]\r\n]+\]\s*)?(?:PHP )?(?:Fatal error:|Parse error:|Uncaught |Error:|FAIL(?:\s|:))", re.M)


def classify(code: int, output: str, *, timeout_seconds: int | None = None, elapsed_seconds: float | None = None) -> dict:
    summaries = []
    for line in output.splitlines():
        for pattern in SUMMARY:
            match = pattern.fullmatch(line.strip())
            if match:
                summaries.append((int(match["checks"]), int(match.groupdict().get("failed", 0))))
    error_output = "\n".join(line for line in output.splitlines() if not PASS_CHECK.match(line))
    error = FAILURE.search(error_output)
    reason = error_output[error.start():].splitlines()[0] if error else ""
    deadline_signal = (code in (137, 143) and timeout_seconds is not None
                       and elapsed_seconds is not None and elapsed_seconds >= timeout_seconds)
    if code == 124 or deadline_signal:
        return {"status": "TIMEOUT", "checks": 0, "reason": "Container execution time limit exceeded"}
    if code or error or any(failed for _, failed in summaries):
        return {"status": "FAIL", "checks": summaries[-1][0] if summaries else 0,
                "reason": reason or f"exit={code}; summaries={summaries}"}
    if not summaries or summaries[-1][0] <= 0:
        return {"status": "INCOMPLETE", "checks": 0, "reason": "No recognized nonempty final summary"}
    return {"status": "PASS", "checks": summaries[-1][0], "reason": ""}


def parser_sanity() -> None:
    samples = [
        (0, "[PASS] a misleading individual check\n== 40 checks, 0 failed ==\n", "PASS"),
        (0, "[PASS] a misleading individual check\n", "INCOMPLETE"),
        (0, "Fatal error: Uncaught RuntimeException: broken invariant\nframe\nframe\nPASS test 3\n", "FAIL"),
        (0, "== 335 checks, 59 failed ==\n", "FAIL"),
        (0, "", "INCOMPLETE"), (1, "PASS: 71 checks\n", "FAIL"),
    ]
    for code, text, expected in samples:
        if classify(code, text)["status"] != expected:
            raise RuntimeError("Built-in summary classifier sanity failed")


def process(args: list[str], **kwargs) -> subprocess.CompletedProcess:
    env = dict(os.environ, MSYS_NO_PATHCONV="1", MSYS2_ARG_CONV_EXCL="*")
    return subprocess.run(args, cwd=ROOT, env=env, check=False, **kwargs)


def checked(args: list[str]) -> str:
    result = process(args, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=180)
    if result.returncode:
        raise RuntimeError(result.stderr.decode("utf-8", "replace") or f"Command failed: {args}")
    return result.stdout.decode("utf-8", "replace").strip()


def cleanup_remote(container: str, remote: str) -> None:
    """Cleanup warnings must not replace export errors or bypass postcondition verification."""
    try:
        checked(["docker", "exec", container, "rm", "-f", "--", remote])
    except (RuntimeError, subprocess.SubprocessError, OSError) as error:
        print(f"WARNING: temporary dump remains at {container}:{remote}: {error}", file=sys.stderr)


def code_identity() -> dict:
    revision = checked(["git", "rev-parse", "HEAD"])
    changed = process(["git", "diff", "--binary", "HEAD"], stdout=subprocess.PIPE).stdout
    untracked = checked(["git", "ls-files", "--others", "--exclude-standard", "-z"])
    digest = hashlib.sha256(changed)
    for name in sorted(filter(None, untracked.split("\0"))):
        file = ROOT / name
        if file.is_file():
            digest.update(name.encode())
            digest.update(file.read_bytes())
    return {"revision": revision, "worktree_sha256": digest.hexdigest()}


class DockerDB:
    def __init__(self, container: str, database: str | None = None):
        self.container = container
        if database is not None and not re.fullmatch(r"[A-Za-z0-9_]+", database):
            raise RuntimeError("Invalid database override")
        self.database = database
        self._prefix = None

    def exec_args(self, interactive: bool = False) -> list[str]:
        args = ["docker", "exec"] + (["-i"] if interactive else [])
        if self.database is not None:
            args += ["-e", "WORDPRESS_DB_NAME=" + self.database]
        return args + [self.container]

    def database_name(self) -> str:
        actual = self.query("SELECT DATABASE()")
        if not re.fullmatch(r"[A-Za-z0-9_]+", actual) or (self.database is not None and self.database != actual):
            raise RuntimeError("Configured DB override did not select the requested database")
        return actual

    def wp(self, *args: str) -> str:
        return checked(self.exec_args() + ["wp", *args])

    def import_dump(self, dump: Path) -> None:
        # docker cp creates root-owned files in sticky /tmp; stdin needs no remote file at all.
        with dump.open("rb") as source:
            result = process(self.exec_args(interactive=True) + ["wp", "db", "import", "-"],
                             stdin=source, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=180)
        if result.returncode:
            raise RuntimeError("Database import failed: " + result.stderr.decode("utf-8", "replace"))

    def query(self, sql: str) -> str:
        return self.wp("db", "query", sql, "--skip-column-names", "--batch")

    def prefix(self) -> str:
        if self._prefix is None:
            # db prefix may bootstrap WordPress; read configuration without loading plugins.
            self._prefix = self.wp("config", "get", "table_prefix", "--type=variable")
            if not re.fullmatch(r"[A-Za-z0-9_]+", self._prefix):
                raise RuntimeError("Invalid WordPress table prefix")
        return self._prefix

    def tables(self, scope: str) -> list[str]:
        prefix = self.prefix()
        names = self.query("SHOW TABLES").splitlines()
        selected = [n for n in names if scope == "full" or n.startswith(prefix + "ax_") or n == prefix + "options"]
        if not selected or any(not re.fullmatch(r"[A-Za-z0-9_]+", n) for n in selected):
            raise RuntimeError("Snapshot table inventory is empty or contains unsafe names")
        return sorted(selected)

    def state(self, scope: str) -> str:
        names = self.tables(scope)
        schema = self.query("; ".join(f"SHOW CREATE TABLE `{name}`" for name in names))
        prefix = self.prefix()
        markers = self.query(f"SELECT option_name,option_value FROM `{prefix}options` WHERE option_name LIKE 'ax%version%' OR option_name LIKE 'ax%system_actor%' OR option_name LIKE 'ax%instance_identity%' ORDER BY option_name")
        return hashlib.sha256(json.dumps([schema, markers], sort_keys=True).encode()).hexdigest()

    def snapshot(self, directory: Path, scope: str) -> Path:
        directory.mkdir(parents=True, exist_ok=False)
        database = self.database_name()
        names = self.tables(scope)
        before = self.state(scope)
        source = code_identity()
        remote = f"/tmp/ax-audit-{uuid.uuid4().hex}.sql"
        try:
            options = ["--tables=" + ",".join(names)] if scope == "plugins" else []
            self.wp("db", "export", remote, "--single-transaction", "--skip-lock-tables", *options)
            checked(["docker", "cp", f"{self.container}:{remote}", str(directory / "database.sql")])
        finally:
            cleanup_remote(self.container, remote)
        dump = directory / "database.sql"
        if not dump.is_file() or dump.stat().st_size == 0 or before != self.state(scope) or source != code_identity():
            raise RuntimeError("Snapshot failed or code/schema changed during capture; pair is invalid")
        metadata = {"version": 1, "container": self.container, "database": database, "scope": scope, "tables": names,
                    "code": source, "state": before, "sha256": hashlib.sha256(dump.read_bytes()).hexdigest(),
                    "created_utc": datetime.now(timezone.utc).isoformat()}
        target = directory / "snapshot.json"
        target.write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8", newline="\n")
        return target


def verify_pair(metadata: dict, db: DockerDB) -> None:
    if metadata["container"] != db.container:
        raise RuntimeError("Snapshot container does not match target")
    if metadata.get("database") is not None and metadata["database"] != db.database_name():
        raise RuntimeError("Snapshot database does not match target")
    if metadata["code"] != code_identity():
        raise RuntimeError("Code does not match paired snapshot; a code-only checkout/stash is not a DB restore")
    if metadata["state"] != db.state(metadata["scope"]):
        raise RuntimeError("Database schema/migration markers do not match paired snapshot")


def restore_snapshot(db: DockerDB, metadata: dict, dump: Path, output: Path) -> None:
    if metadata["container"] != db.container or metadata["code"] != code_identity():
        raise RuntimeError("Restore refused: check out the snapshot's exact code/worktree first")
    if not metadata.get("database") or metadata["database"] != db.database_name():
        raise RuntimeError("Restore refused: snapshot database identity is missing or mismatched")
    if hashlib.sha256(dump.read_bytes()).hexdigest() != metadata["sha256"]:
        raise RuntimeError("Snapshot dump checksum mismatch")
    print("Recovery snapshot:", db.snapshot(output / "pre-restore", metadata["scope"]), flush=True)
    names = db.tables(metadata["scope"])
    db.query("SET FOREIGN_KEY_CHECKS=0; DROP TABLE " + ",".join(f"`{n}`" for n in names) + "; SET FOREIGN_KEY_CHECKS=1;")
    db.import_dump(dump)
    # Unlike preconditions, this checks that import actually restored the recorded DB state.
    verify_pair(metadata, db)


def catalog(config: dict) -> list[dict]:
    result = []
    for file in sorted(PLUGINS.glob("axismundi-*/tests/*.php")):
        name = file.relative_to(PLUGINS).as_posix()
        executable = file.name.startswith("audit-") or name in config["standalone"]
        excluded = any(fnmatch.fnmatch(file.name, pattern) for pattern in config["exclude"])
        env = config["environment"].get(name, {})
        getenv = re.findall(r"getenv\(\s*['\"]([A-Z][A-Z0-9_]*)['\"]", file.read_text(encoding="utf-8"))
        seconds = config.get("timeouts", {}).get(name, 300)
        if type(seconds) is not int or seconds < 1:
            raise RuntimeError(f"Invalid declared timeout for {name}: {seconds!r}")
        result.append({"name": name, "enabled": executable and not excluded,
                       "executable": executable, "excluded": excluded, "timeout_seconds": seconds,
                       "environment": env, "observed_getenv": sorted(set(getenv)),
                       "required_environment": config.get("requirements", {}).get(name, []),
                       "kind": "standalone" if name in config["standalone"] else "wordpress"})
    return result


def select_entries(entries: list[dict], plugins: list[str], audits: list[str]) -> list[dict]:
    selected = []
    for entry in entries:
        name = entry["name"]
        if plugins and name.split("/")[0] not in plugins:
            continue
        # A broad glob must not silently include negative controls or fixture helpers.
        explicit = entry.get("executable", False) and (name in audits or Path(name).name in audits)
        if not entry["enabled"] and not explicit:
            continue
        if audits and not any(fnmatch.fnmatch(name, p) or fnmatch.fnmatch(Path(name).name, p) for p in audits):
            continue
        selected.append(entry)
    return selected


def timeout_for(entry: dict, override: int | None) -> int:
    seconds = entry.get("timeout_seconds", 300) if override is None else override
    if type(seconds) is not int or seconds < 1:
        raise RuntimeError("Timeout must be a positive integer")
    return seconds


def run_one(db: DockerDB, entry: dict, directory: Path, seconds: int) -> dict:
    logfile = directory / (entry["name"].replace("/", "__") + ".log")
    for key in entry.get("required_environment", []):
        if not entry["environment"].get(key):
            reason = f"Missing declared environment {key} for {entry['name']}"
            logfile.write_text(reason + "\n", encoding="utf-8", newline="\n")
            return dict(entry, status="CONFIG_ERROR", checks=0, reason=reason, exit_code=None, seconds=0, log=str(logfile))
    if db.database is not None and any(key.endswith("_ORIGIN") for key in entry["environment"]):
        reason = "DB-isolated CLI cannot verify that HTTP origin uses the same isolated database"
        logfile.write_text(reason + "\n", encoding="utf-8", newline="\n")
        return dict(entry, status="CONFIG_ERROR", checks=0, reason=reason, exit_code=None, seconds=0, log=str(logfile))
    args = ["docker", "exec"]
    if db.database is not None:
        args += ["-e", "WORDPRESS_DB_NAME=" + db.database]
    for key, value in entry["environment"].items():
        if not value:
            raise RuntimeError(f"Missing declared environment {key} for {entry['name']}")
        args += ["-e", f"{key}={value}"]
    args += [db.container, "timeout", "-k", "10", str(seconds)]
    path = "wp-content/plugins/" + entry["name"]
    args += ["php", "/var/www/html/" + path] if entry["kind"] == "standalone" else ["wp", "eval-file", path]
    start = time.monotonic()
    with logfile.open("wb") as log:
        try:
            code = process(args, stdout=log, stderr=subprocess.STDOUT, timeout=seconds + 30).returncode
        except subprocess.TimeoutExpired:
            code = 124
    output = logfile.read_text(encoding="utf-8", errors="replace")
    elapsed = time.monotonic() - start
    return dict(entry, **classify(code, output, timeout_seconds=seconds, elapsed_seconds=elapsed),
                exit_code=code, timeout_seconds=seconds, seconds=round(elapsed, 2), log=str(logfile))


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=["list", "run", "snapshot", "restore"])
    parser.add_argument("--container", default="")
    parser.add_argument("--database", help="Explicit WORDPRESS_DB_NAME override; HTTP audits are refused")
    parser.add_argument("--plugin", action="append", default=[])
    parser.add_argument("--audit", action="append", default=[])
    parser.add_argument("--manifest", type=Path, default=MANIFEST)
    parser.add_argument("--output", type=Path)
    parser.add_argument("--scope", choices=["plugins", "full"], default="plugins")
    parser.add_argument("--db-mode", choices=["none", "before-after"], default="none")
    parser.add_argument("--state", type=Path, help="Previously captured snapshot.json to verify or restore")
    parser.add_argument("--confirm-restore", help="Must exactly name the destination container")
    parser.add_argument("--timeout", type=int, help="Override every audit's declared timeout (otherwise manifest, then 300 seconds)")
    args = parser.parse_args()
    parser_sanity()
    config = json.loads(args.manifest.read_text(encoding="utf-8"))
    entries = catalog(config)
    selected = select_entries(entries, args.plugin, args.audit)
    if args.timeout is not None:
        timeout_for({}, args.timeout)
    if args.command == "list":
        print(json.dumps(entries, indent=2)); return 0
    container = args.container
    if not container:
        candidates = checked(["docker", "ps", "--format", "{{.Names}}"]).splitlines()
        candidates = [c for c in candidates if c.startswith("wp-env-axismundi-") and c.endswith("-cli-1")]
        if len(candidates) != 1:
            raise RuntimeError("Specify --container; there is not exactly one matching running wp-env CLI")
        container = candidates[0]
    db = DockerDB(container, args.database)
    database = db.database_name()
    output = args.output or Path(tempfile.mkdtemp(prefix="axismundi-audits-"))
    if output.resolve().is_relative_to(ROOT):
        raise RuntimeError("Artifacts must be outside the checkout to keep code fingerprints stable and DB dumps private")
    output.mkdir(parents=True, exist_ok=True)
    print(f"Artifacts: {output}", flush=True)
    if args.command == "snapshot":
        print(db.snapshot(output / "state", args.scope)); return 0
    if args.command == "restore":
        if not args.state or args.confirm_restore != container:
            raise RuntimeError("Restore requires --state and --confirm-restore naming the target container")
        metadata = json.loads(args.state.read_text(encoding="utf-8"))
        dump = args.state.with_name("database.sql")
        restore_snapshot(db, metadata, dump, output)
        print("PASS snapshot-restore 1"); return 0
    if not selected:
        raise RuntimeError("No executable audits matched; empty selection is not success")
    pair = None
    if args.state:
        pair = json.loads(args.state.read_text(encoding="utf-8")); verify_pair(pair, db)
    if args.db_mode == "before-after":
        pair_path = db.snapshot(output / "before", args.scope)
        pair = json.loads(pair_path.read_text(encoding="utf-8"))
    source = code_identity()
    results = []
    sanity = next((e for e in entries if e["name"] == config["sanity"] and e["enabled"]), None)
    if sanity is None:
        raise RuntimeError("Manifest known-good sanity audit is missing")
    try:
        results.append(run_one(db, sanity, output, timeout_for(sanity, args.timeout)))
        if results[-1]["status"] != "PASS":
            raise RuntimeError("Known-good sanity audit failed; refusing to interpret suite results")
        for entry in selected:
            if entry["name"] == sanity["name"]:
                continue
            result = run_one(db, entry, output, timeout_for(entry, args.timeout))
            results.append(result)
            print(f"{result['status']:10} {result['checks']:5} {entry['name']} {result['reason']}", flush=True)
    finally:
        report = {"version": 1, "container": container, "database": database, "code": source, "paired_state": pair,
                  "code_unchanged": source == code_identity(), "results": results}
        (output / "results.json").write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8", newline="\n")
        rows = ["| Status | Checks | Audit | Log |", "|---|---:|---|---|"]
        rows += [f"| {r['status']} | {r['checks']} | {r['name']} | {Path(r['log']).name} |" for r in results]
        (output / "results.md").write_text("\n".join(rows) + "\n", encoding="utf-8", newline="\n")
        if args.db_mode == "before-after":
            db.snapshot(output / "after", args.scope)
    return 0 if report["code_unchanged"] and all(r["status"] == "PASS" for r in results) else 1


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (RuntimeError, subprocess.SubprocessError, OSError, ValueError) as error:
        print(f"ERROR: {error}", file=sys.stderr)
        sys.exit(2)
