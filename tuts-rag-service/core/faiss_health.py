from __future__ import annotations

import json
from datetime import UTC, datetime
from pathlib import Path
from typing import Any

from config import FAISS_INDEX_FILE, MANIFEST_FILE, settings
from core.utils import limpar_nome_uc

REQUIRED_INDEX_FILES = (FAISS_INDEX_FILE, "index.pkl")


def _base_dir() -> Path:
    return Path(settings.base_faiss_dir).resolve()


def _safe_relative(path: Path, base: Path) -> str:
    try:
        return str(path.resolve().relative_to(base))
    except Exception:
        return path.name


def _load_manifest(path: Path) -> tuple[dict[str, Any] | None, str | None]:
    if not path.exists():
        return None, "missing"

    try:
        with path.open("r", encoding="utf-8") as handle:
            manifest = json.load(handle)
    except Exception as exc:
        return None, f"invalid_json:{type(exc).__name__}"

    if not isinstance(manifest, dict):
        return None, "invalid_shape"

    version = str(manifest.get("version") or "").strip()
    if not version:
        return manifest, "missing_version"

    return manifest, None


def _legacy_manifest(folder: Path) -> dict[str, Any]:
    return {
        "uc": folder.name,
        "version": "sem_versao",
        "updated_at": datetime.now(UTC).isoformat(),
        "chunks": None,
        "source": "legacy_index_without_manifest",
        "generated_by": "scripts/sanity_faiss_paths.py",
    }


def validate_faiss_indexes(create_missing_manifests: bool = False) -> dict[str, Any]:
    base = _base_dir()
    result: dict[str, Any] = {
        "ready": False,
        "base_exists": base.exists(),
        "base_dir": base.name,
        "ucs": [],
        "errors": [],
        "warnings": [],
        "manifests_created": 0,
    }

    if not base.exists():
        result["errors"].append("faiss_db_missing")
        return result

    if not base.is_dir():
        result["errors"].append("faiss_db_not_directory")
        return result

    folders = sorted((path for path in base.iterdir() if path.is_dir()), key=lambda p: p.name.lower())

    if not folders:
        result["errors"].append("no_uc_indexes")
        return result

    for folder in folders:
        resolved = folder.resolve()

        if base != resolved and base not in resolved.parents:
            result["errors"].append(f"path_outside_base:{folder.name}")
            continue

        missing_required = [
            filename
            for filename in REQUIRED_INDEX_FILES
            if not (folder / filename).is_file()
        ]

        manifest_path = folder / MANIFEST_FILE
        manifest, manifest_error = _load_manifest(manifest_path)

        if manifest_error == "missing" and create_missing_manifests and not missing_required:
            manifest = _legacy_manifest(folder)
            with manifest_path.open("w", encoding="utf-8") as handle:
                json.dump(manifest, handle, ensure_ascii=False, indent=2)
                handle.write("\n")
            manifest_error = None
            result["manifests_created"] += 1

        if missing_required:
            result["errors"].append(f"{folder.name}:missing:{','.join(missing_required)}")

        if manifest_error:
            result["warnings"].append(f"{folder.name}:manifest:{manifest_error}")

        canonical = limpar_nome_uc(folder.name)
        if canonical != folder.name:
            result["warnings"].append(f"{folder.name}:legacy_name")

        result["ucs"].append(
            {
                "folder": _safe_relative(folder, base),
                "canonical": canonical,
                "required_ok": not missing_required,
                "missing_required": missing_required,
                "manifest_ok": manifest_error is None,
                "manifest_version": str((manifest or {}).get("version") or "sem_versao"),
            }
        )

    result["ready"] = not result["errors"]
    return result
