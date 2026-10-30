"""Reproduce reviewer measurement mistakes without mutating the development database."""
import contextlib
import hashlib
import io
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

import run_audits as runner


class AuditRunnerTests(unittest.TestCase):
    def test_known_good_sanity(self):
        runner.parser_sanity()
        self.assertEqual("PASS", runner.classify(0, "PASS: 71 checks\n")["status"])
        self.assertEqual("PASS", runner.classify(0, "PASS: 34 runtime checks\n")["status"])
        self.assertEqual("PASS", runner.classify(0, "PASS profile-separation 31\n")["status"])

    def test_error_words_in_pass_labels_are_not_errors(self):
        for label in ("an uncaught exception is reported", "Fatal error: is rendered safely", "Error: is preserved", "[FAIL] appears as data"):
            output = f"[PASS] {label}\n== 1 checks, 0 failed ==\n"
            self.assertEqual("PASS", runner.classify(0, output)["status"], label)

    def test_real_error_after_pass_label_is_not_hidden(self):
        for error in ("Fatal error: broken", "PHP Parse error: broken", "[30-Oct-2026 UTC] PHP Fatal error: broken", "Error: WP-CLI failed", "[FAIL] an uncaught exception is not reported"):
            output = f"[PASS] an uncaught exception is handled\n{error}\nPASS pretend 1\n"
            self.assertEqual("FAIL", runner.classify(0, output)["status"], error)

    def test_individual_pass_is_not_a_summary(self):
        self.assertEqual("INCOMPLETE", runner.classify(0, "[PASS] a completed individual check\n")["status"])

    def test_failed_summary_cannot_be_hidden_by_exit_zero(self):
        self.assertEqual("FAIL", runner.classify(0, "== 335 checks, 59 failed ==\n")["status"])

    def test_no_summary_or_zero_checks_is_not_pass(self):
        for value in ("", "fixture loaded\n", "PASS empty 0\n"):
            self.assertEqual("INCOMPLETE", runner.classify(0, value)["status"])

    def test_nonzero_or_timeout_cannot_be_hidden_by_summary(self):
        self.assertEqual("FAIL", runner.classify(1, "PASS: 71 checks\n")["status"])
        self.assertEqual("TIMEOUT", runner.classify(124, "PASS: 71 checks\n")["status"])

    def test_wrong_http_environment_is_refused_before_execution(self):
        entry = {"name": "admin-http", "environment": {"AX_C2S_HTTP_ORIGIN": "http://wordpress"},
                 "required_environment": ["AX_ADMIN_HTTP_ORIGIN"]}
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "process") as execute:
            result = runner.run_one(runner.DockerDB("fixture"), entry, Path(root), 30)
            self.assertEqual("CONFIG_ERROR", result["status"])
            self.assertIn("AX_ADMIN_HTTP_ORIGIN", result["reason"])
            execute.assert_not_called()

    def test_complete_log_keeps_first_exception_even_with_pass_at_end(self):
        output = b"Fatal error: Uncaught RuntimeException: named invariant\nframe\nframe\nPASS pretend 3\n"
        def fake(args, **kwargs):
            self.assertIn("-k", args)
            self.assertNotIn("--kill-after=10s", args)
            kwargs["stdout"].write(output)
            return subprocess.CompletedProcess(args, 0)
        entry = {"name": "plugin/tests/audit.php", "environment": {}, "kind": "wordpress"}
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "process", side_effect=fake):
            result = runner.run_one(runner.DockerDB("fixture"), entry, Path(root), 30)
            self.assertEqual("FAIL", result["status"])
            self.assertIn("named invariant", result["reason"])
            self.assertEqual(output, Path(result["log"]).read_bytes())

    def test_msys_path_conversion_is_disabled_without_shell(self):
        with patch.object(runner.subprocess, "run", return_value=subprocess.CompletedProcess([], 0)) as run:
            runner.process(["docker", "exec", "fixture", "wp", "db", "export", "/tmp/snapshot.sql"])
            self.assertEqual("1", run.call_args.kwargs["env"]["MSYS_NO_PATHCONV"])
            self.assertEqual("*", run.call_args.kwargs["env"]["MSYS2_ARG_CONV_EXCL"])
            self.assertNotIn("shell", run.call_args.kwargs)
            self.assertEqual("/tmp/snapshot.sql", run.call_args.args[0][-1])

    def test_code_only_rollback_is_not_a_valid_pair(self):
        metadata = {"container": "fixture", "code": {"revision": "before"}, "state": "schema15", "scope": "plugins"}
        with patch.object(runner, "code_identity", return_value={"revision": "after"}):
            with self.assertRaisesRegex(RuntimeError, "code-only"):
                runner.verify_pair(metadata, runner.DockerDB("fixture"))

    def test_matching_code_with_migrated_database_is_refused(self):
        metadata = {"container": "fixture", "code": {"revision": "before"}, "state": "schema14", "scope": "plugins"}
        with patch.object(runner, "code_identity", return_value=metadata["code"]), patch.object(runner.DockerDB, "state", return_value="schema15"):
            with self.assertRaisesRegex(RuntimeError, "schema/migration"):
                runner.verify_pair(metadata, runner.DockerDB("fixture"))

    def test_other_container_snapshot_is_refused(self):
        with self.assertRaisesRegex(RuntimeError, "container"):
            runner.verify_pair({"container": "other"}, runner.DockerDB("fixture"))

    def test_wrong_database_is_refused_before_restore(self):
        db = runner.DockerDB("fixture", "isolated_db")
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "code_identity", return_value={"revision": "same"}), patch.object(db, "database_name", return_value="isolated_db"), patch.object(db, "snapshot") as recovery:
            dump = Path(root) / "database.sql"; dump.write_bytes(b"SQL")
            with self.assertRaisesRegex(RuntimeError, "database identity"):
                runner.restore_snapshot(db, {"container": "fixture", "database": "live_db", "code": {"revision": "same"}}, dump, Path(root))
            recovery.assert_not_called()

    def test_database_override_is_passed_without_shell(self):
        args = runner.DockerDB("fixture", "isolated_db").exec_args(interactive=True)
        self.assertEqual(["docker", "exec", "-i", "-e", "WORDPRESS_DB_NAME=isolated_db", "fixture"], args)

    def test_prefix_lookup_does_not_bootstrap_wordpress(self):
        db = runner.DockerDB("fixture", "isolated_db")
        with patch.object(db, "wp", return_value="wp_") as wp:
            self.assertEqual("wp_", db.prefix())
            wp.assert_called_once_with("config", "get", "table_prefix", "--type=variable")

    def test_isolated_cli_refuses_live_http_audits(self):
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "process") as execute:
            entry = {"name": "http-audit", "environment": {"AX_ADMIN_HTTP_ORIGIN": "http://wordpress"}}
            result = runner.run_one(runner.DockerDB("fixture", "isolated_db"), entry, Path(root), 30)
            self.assertEqual("CONFIG_ERROR", result["status"])
            execute.assert_not_called()

    def test_snapshot_export_failure_has_no_valid_pair(self):
        with tempfile.TemporaryDirectory() as root, patch.object(runner.DockerDB, "database_name", return_value="fixture_db"), patch.object(runner.DockerDB, "tables", return_value=["wp_ax_contacts"]), patch.object(runner.DockerDB, "state", return_value="schema"), patch.object(runner, "code_identity", return_value={"revision": "test"}), patch.object(runner.DockerDB, "wp", side_effect=RuntimeError("export failed")), patch.object(runner, "checked", side_effect=RuntimeError("cleanup failed")), contextlib.redirect_stderr(io.StringIO()) as warnings:
            destination = Path(root) / "snapshot"
            with self.assertRaisesRegex(RuntimeError, "export failed"):
                runner.DockerDB("fixture").snapshot(destination, "plugins")
            self.assertFalse((destination / "snapshot.json").exists())
            self.assertIn("cleanup failed", warnings.getvalue())

    def test_cleanup_failure_is_a_warning_not_a_replacement_exception(self):
        with patch.object(runner, "checked", side_effect=RuntimeError("Operation not permitted")), contextlib.redirect_stderr(io.StringIO()) as output:
            runner.cleanup_remote("fixture", "/tmp/root-owned.sql")
        self.assertIn("WARNING", output.getvalue())
        self.assertIn("/tmp/root-owned.sql", output.getvalue())

    def test_stdin_import_creates_no_root_owned_temporary_file(self):
        payload = b"CREATE TABLE probe (id int);\n"
        def fake(args, **kwargs):
            self.assertEqual(["docker", "exec", "-i", "fixture", "wp", "db", "import", "-"], args)
            self.assertEqual(payload, kwargs["stdin"].read())
            self.assertNotIn("input", kwargs)
            return subprocess.CompletedProcess(args, 0, b"Success", b"")
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "process", side_effect=fake):
            dump = Path(root) / "database.sql"
            dump.write_bytes(payload)
            runner.DockerDB("fixture").import_dump(dump)

    def test_failed_stdin_import_is_not_success(self):
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "process", return_value=subprocess.CompletedProcess([], 1, b"", b"SQL failure")):
            dump = Path(root) / "database.sql"; dump.write_bytes(b"invalid SQL")
            with self.assertRaisesRegex(RuntimeError, "SQL failure"):
                runner.DockerDB("fixture").import_dump(dump)

    def test_restore_reaches_verification_even_after_recovery_cleanup_warning(self):
        events = []
        class Database:
            container = "fixture"
            restored = False
            def database_name(self):
                return "fixture_db"
            def snapshot(self, destination, scope):
                events.append("recovery")
                runner.cleanup_remote("fixture", "/tmp/root-owned.sql")
                return destination / "snapshot.json"
            def tables(self, scope):
                return ["wp_ax_probe"]
            def query(self, sql):
                events.append("drop")
            def import_dump(self, source):
                events.append("import"); self.restored = True
            def state(self, scope):
                events.append("verify")
                return "restored" if self.restored else "modified"
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "code_identity", return_value={"revision": "same"}), patch.object(runner, "checked", side_effect=RuntimeError("Operation not permitted")), contextlib.redirect_stderr(io.StringIO()), contextlib.redirect_stdout(io.StringIO()):
            dump = Path(root) / "database.sql"; dump.write_bytes(b"SQL")
            metadata = {"container": "fixture", "database": "fixture_db", "code": {"revision": "same"}, "sha256": hashlib.sha256(b"SQL").hexdigest(), "scope": "plugins", "state": "restored"}
            runner.restore_snapshot(Database(), metadata, dump, Path(root))
        self.assertEqual(["recovery", "drop", "import", "verify"], events)

    def test_recovery_failure_prevents_destructive_queries(self):
        db = runner.DockerDB("fixture")
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "code_identity", return_value={"revision": "same"}), patch.object(db, "database_name", return_value="fixture_db"), patch.object(db, "snapshot", side_effect=RuntimeError("recovery failed")), patch.object(db, "query") as query:
            dump = Path(root) / "database.sql"; dump.write_bytes(b"SQL")
            metadata = {"container": "fixture", "database": "fixture_db", "code": {"revision": "same"}, "sha256": hashlib.sha256(b"SQL").hexdigest(), "scope": "plugins"}
            with self.assertRaisesRegex(RuntimeError, "recovery failed"):
                runner.restore_snapshot(db, metadata, dump, Path(root))
            query.assert_not_called()

    def test_import_success_without_restored_state_is_not_success(self):
        db = runner.DockerDB("fixture")
        with tempfile.TemporaryDirectory() as root, patch.object(runner, "code_identity", return_value={"revision": "same"}), patch.object(db, "database_name", return_value="fixture_db"), patch.object(db, "snapshot", return_value="recovery"), patch.object(db, "tables", return_value=["wp_ax_probe"]), patch.object(db, "query"), patch.object(db, "import_dump") as imported, patch.object(db, "state", return_value="wrong"), contextlib.redirect_stdout(io.StringIO()):
            dump = Path(root) / "database.sql"; dump.write_bytes(b"SQL")
            metadata = {"container": "fixture", "database": "fixture_db", "code": {"revision": "same"}, "sha256": hashlib.sha256(b"SQL").hexdigest(), "scope": "plugins", "state": "expected"}
            with self.assertRaisesRegex(RuntimeError, "schema/migration"):
                runner.restore_snapshot(db, metadata, dump, Path(root))
            imported.assert_called_once_with(dump)


if __name__ == "__main__":
    unittest.main()
