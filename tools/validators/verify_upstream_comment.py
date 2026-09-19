#!/usr/bin/env python3
"""Fail unless an external-comment candidate exactly matches its source draft.

Three checks, each closing a way a public post has gone wrong here:

- source == candidate: the candidate is the declared draft, not a summary or a
  reconstruction of it (Trac #44001 comment:17 went up as an abbreviated
  version first).
- --require-file: literals that must appear in the source. A byte comparison
  cannot see a draft that was already broken when it was saved; the Trac #66104
  draft had its escape notation decoded into characters by the file tools, and
  the candidate copied from it matched perfectly.
- --posted / --github: what the server stored matches the candidate. Markup the
  target rewrites, or a paste into the wrong syntax, only shows up after
  submission.

Line endings are normalized. For posted text, trailing newlines are ignored,
since GitHub and Trac trim them.
"""

from __future__ import annotations

import argparse
import difflib
import hashlib
import json
import re
from pathlib import Path
import subprocess
import sys


def unique_index(text: str, marker: str, role: str) -> int:
	"""Where a marker starts; it must occur exactly once, or the section is a guess."""
	count = text.count(marker)
	if count == 0:
		raise ValueError(f"Source {role} marker not found: {marker!r}")
	if count > 1:
		raise ValueError(f"Source {role} marker is ambiguous ({count} occurrences): {marker!r}")
	return text.index(marker)


def section(
	text: str,
	source_after: str | None = None,
	source_before: str | None = None,
	source_from: str | None = None,
) -> str:
	text = text.replace("\r\n", "\n")
	if source_after is not None and source_from is not None:
		raise ValueError("Use either --source-after or --source-from, not both")
	if source_after is not None:
		start = unique_index(text, source_after, "start")
		text = text[start + len(source_after):].lstrip("\n")
	elif source_from is not None:
		text = text[unique_index(text, source_from, "start"):]
	if source_before is not None:
		text = text[:unique_index(text, source_before, "end")].rstrip("\n") + "\n"
	return text


def read(path: Path) -> str:
	return path.read_text(encoding="utf-8")


def diff(a: str, b: str, a_name: str, b_name: str) -> str:
	return "".join(
		difflib.unified_diff(
			a.splitlines(keepends=True),
			b.splitlines(keepends=True),
			fromfile=a_name,
			tofile=b_name,
			n=2,
		)
	)


def required_literals(path: Path) -> list[str]:
	return [line for line in read(path).replace("\r\n", "\n").split("\n") if line != ""]


def fetch_github_body(url: str) -> str:
	"""Body of a GitHub issue, pull request or issue comment, via the gh CLI."""
	match = re.match(r"https://github\.com/([^/]+)/([^/]+)/(?:issues|pull)/(\d+)(?:#issuecomment-(\d+))?$", url)
	if not match:
		raise ValueError(
			"Unsupported GitHub URL; use an issue, pull request or #issuecomment- link, "
			"or save the posted text and pass --posted."
		)
	owner, repo, number, comment = match.groups()
	endpoint = f"repos/{owner}/{repo}/issues/comments/{comment}" if comment else f"repos/{owner}/{repo}/issues/{number}"
	result = subprocess.run(["gh", "api", endpoint], capture_output=True)
	if result.returncode != 0:
		raise ValueError("gh api failed: " + result.stderr.decode("utf-8", "replace").strip())
	return json.loads(result.stdout)["body"] or ""


def check(
	source_path: Path,
	candidate_path: Path | None,
	source_after: str | None = None,
	source_before: str | None = None,
	require_path: Path | None = None,
	posted_text: str | None = None,
	source_from: str | None = None,
) -> int:
	try:
		source = section(read(source_path), source_after, source_before, source_from)
	except ValueError as error:
		print(error)
		return 2

	if require_path is not None:
		missing = [literal for literal in required_literals(require_path) if literal not in source]
		if missing:
			print("External post blocked: the source lacks required literals (it may have been altered when saved).")
			for literal in missing:
				print("  missing:", ascii(literal))
			return 1

	text = source
	if candidate_path is not None:
		candidate = section(read(candidate_path))
		if source != candidate:
			print("External post blocked: candidate differs from the declared source.")
			print(diff(source, candidate, "source", "candidate"), end="")
			return 1
		text = candidate

	if posted_text is not None:
		expected = text.rstrip("\n")
		posted = posted_text.replace("\r\n", "\n").rstrip("\n")
		if expected != posted:
			print("Posted text differs from what was meant to be posted.")
			print(diff(expected + "\n", posted + "\n", "intended", "posted"), end="")
			return 1

	digest = hashlib.sha256(text.encode("utf-8")).hexdigest()
	checked = ["source"] + (["candidate"] if candidate_path is not None else []) + (["posted"] if posted_text is not None else [])
	print(f"External post verified ({' == '.join(checked)}): {source_path}")
	print(f"SHA-256: {digest}")
	return 0


def self_test() -> int:
	from tempfile import TemporaryDirectory

	backslash = chr(92)
	escaped = backslash + "uD83E" + backslash + "uDEC8"
	decoded = chr(0x1FAC8)
	failures = []

	def expect(name: str, got: int, want: int) -> None:
		if got != want:
			failures.append(f"{name}: expected exit {want}, got {got}")

	with TemporaryDirectory() as directory:
		root = Path(directory)
		source = root / "source.md"
		candidate = root / "candidate.txt"
		required = root / "required.txt"

		source.write_text("Notes\n## Comment\nOne\nTwo\n## After\nprivate note\n", encoding="utf-8", newline="\n")
		candidate.write_text("One\r\nTwo\r\n", encoding="utf-8", newline="\n")
		expect("identical section passes", check(source, candidate, "## Comment", "## After"), 0)

		candidate.write_text("One\nThree\n", encoding="utf-8", newline="\n")
		expect("changed candidate is blocked", check(source, candidate, "## Comment", "## After"), 1)

		expect("missing end marker is an error", check(source, None, "## Comment", "## Nowhere"), 2)

		required.write_text(escaped + "\n", encoding="utf-8", newline="\n")
		source.write_text("## Comment\nUse '" + escaped + "'.\n", encoding="utf-8", newline="\n")
		candidate.write_text("Use '" + escaped + "'.\n", encoding="utf-8", newline="\n")
		expect("intact escape passes", check(source, candidate, "## Comment", None, required), 0)

		source.write_text("## Comment\nUse '" + decoded + "'.\n", encoding="utf-8", newline="\n")
		candidate.write_text("Use '" + decoded + "'.\n", encoding="utf-8", newline="\n")
		expect("decoded source is blocked even when candidate matches", check(source, candidate, "## Comment", None, required), 1)

		source.write_text("## Comment\nSee [https://example.org/ PR 1].\n", encoding="utf-8", newline="\n")
		candidate.write_text("See [https://example.org/ PR 1].\n", encoding="utf-8", newline="\n")
		expect("posted text equal apart from trailing newline passes", check(source, candidate, "## Comment", posted_text="See [https://example.org/ PR 1]."), 0)
		expect("posted text in another link syntax is blocked", check(source, candidate, "## Comment", posted_text="See [[https://example.org/ PR 1]]."), 1)

		source.write_text("Status: posted as Title\n## Title\nTitle\n## Body\nText\n", encoding="utf-8", newline="\n")
		expect("ambiguous start marker is an error", check(source, None, "Title", posted_text="x"), 2)

		candidate.write_text("## Body\nText\n", encoding="utf-8", newline="\n")
		expect("--source-from keeps the marker line", check(source, candidate, source_from="## Body"), 0)

	if failures:
		print("Self-test failed:")
		for failure in failures:
			print("  " + failure)
		return 1
	print("Self-test passed (9 cases).")
	return 0


def main() -> int:
	parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
	parser.add_argument("--source", type=Path, help="The draft the post must come from.")
	parser.add_argument("--candidate", type=Path, help="The exact text about to be posted.")
	parser.add_argument("--source-after", help="Use only the text below this exact marker in the source file.")
	parser.add_argument("--source-from", help="Start the source section at this exact marker, keeping the marker line.")
	parser.add_argument("--source-before", help="Stop the source section at this exact marker.")
	parser.add_argument("--require-file", type=Path, help="File of literals, one per line, that must appear in the source.")
	parser.add_argument("--posted", type=Path, help="Text copied back from the target after submission (for Trac).")
	parser.add_argument("--github", help="Issue, pull request or #issuecomment- URL to read the posted body from.")
	parser.add_argument("--self-test", action="store_true")
	args = parser.parse_args()

	if args.self_test:
		return self_test()
	if not args.source or not (args.candidate or args.posted or args.github):
		parser.error("--source and at least one of --candidate, --posted or --github are required unless --self-test is used")

	posted_text = None
	if args.posted and args.github:
		parser.error("use either --posted or --github, not both")
	if args.posted:
		posted_text = read(args.posted)
	elif args.github:
		try:
			posted_text = fetch_github_body(args.github)
		except ValueError as error:
			print(error)
			return 2

	return check(
		args.source,
		args.candidate,
		args.source_after,
		args.source_before,
		args.require_file,
		posted_text,
		args.source_from,
	)


if __name__ == "__main__":
	sys.exit(main())
