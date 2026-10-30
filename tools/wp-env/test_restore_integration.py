#!/usr/bin/env python3
"""Explicit integration check: create and remove only a uniquely named disposable database."""
import argparse
import json
from pathlib import Path
import re
import sys
import tempfile
import uuid

import run_audits as runner


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--container", required=True)
    args = parser.parse_args()
    admin = runner.DockerDB(args.container)
    live_name = admin.database_name()
    prefix = admin.prefix()
    live_sql = f"SELECT option_name,option_value FROM `{prefix}options` WHERE option_name IN ('ax_contacts_db_version','ax_actors_system_actor_id','ax_actors_instance_identity_id') ORDER BY option_name"
    live_before = admin.query(live_sql)
    name = "ax_audit_roundtrip_" + uuid.uuid4().hex[:16]
    if name == live_name or not re.fullmatch(r"ax_audit_roundtrip_[0-9a-f]{16}", name):
        raise RuntimeError("Unsafe disposable database name")
    if admin.query(f"SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='{name}'"):
        raise RuntimeError("Disposable database unexpectedly already exists")
    output = Path(tempfile.mkdtemp(prefix="axismundi-restore-integration-"))
    print(f"Artifacts: {output}", flush=True)
    created = False
    checks = 0
    try:
        admin.query(f"CREATE DATABASE `{name}`")
        created = True
        db = runner.DockerDB(args.container, name)
        if db.database_name() != name:
            raise RuntimeError("Database override was ignored")
        checks += 1
        db.query(f"CREATE TABLE `{prefix}options` (option_name varchar(191) PRIMARY KEY,option_value longtext) ENGINE=InnoDB; INSERT INTO `{prefix}options` VALUES ('ax_contacts_db_version','15'); CREATE TABLE `{prefix}ax_roundtrip_probe` (id int PRIMARY KEY,body varchar(64)) ENGINE=InnoDB; INSERT INTO `{prefix}ax_roundtrip_probe` VALUES (1,'original');")
        state_path = db.snapshot(output / "before", "plugins")
        metadata = json.loads(state_path.read_text(encoding="utf-8"))
        if metadata["database"] != name:
            raise RuntimeError("Snapshot failed to bind database identity")
        checks += 1
        db.query(f"UPDATE `{prefix}ax_roundtrip_probe` SET body='modified'; ALTER TABLE `{prefix}ax_roundtrip_probe` ADD changed int; CREATE TABLE `{prefix}ax_roundtrip_added` (id int) ENGINE=InnoDB; UPDATE `{prefix}options` SET option_value='changed';")
        runner.restore_snapshot(db, metadata, state_path.with_name("database.sql"), output)
        checks += 1
        if db.query(f"SELECT body FROM `{prefix}ax_roundtrip_probe`") != "original":
            raise RuntimeError("Restored data differs from snapshot")
        checks += 1
        if prefix + "ax_roundtrip_added" in db.tables("plugins"):
            raise RuntimeError("New table survived restore")
        checks += 1
        if db.query(f"SELECT option_value FROM `{prefix}options` WHERE option_name='ax_contacts_db_version'") != "15":
            raise RuntimeError("Migration marker was not restored")
        checks += 1
        if admin.database_name() != live_name or admin.query(live_sql) != live_before:
            raise RuntimeError("Development database identity or markers changed")
        checks += 1
    finally:
        # The generated target is checked before every destructive database cleanup.
        if created:
            if name == live_name or not re.fullmatch(r"ax_audit_roundtrip_[0-9a-f]{16}", name):
                raise RuntimeError("Refusing unsafe cleanup target")
            admin.query(f"DROP DATABASE `{name}`")
    print(f"PASS restore-integration {checks}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
