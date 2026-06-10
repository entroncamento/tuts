#!/usr/bin/env python3
from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from core.utils import limpar_nome_uc

FAISS_DIR = ROOT / "faiss_db"
REQUIRED_FILES = ("index.faiss", "index.pkl")
MANIFEST_FILE = "manifest.json"


def _status_file(path: Path) -> str:
    return "ok" if path.exists() else "MISSING"


def _manifest_version(folder: Path) -> str:
    manifest_path = folder / MANIFEST_FILE

    if not manifest_path.exists():
        return "sem_versao"

    try:
        with manifest_path.open("r", encoding="utf-8") as handle:
            manifest = json.load(handle)
    except Exception:
        return "sem_versao"

    version = str(manifest.get("version") or "").strip()
    return version or "sem_versao"


def main() -> int:
    warnings = 0
    errors = 0

    print(f"FAISS base: {FAISS_DIR}")

    if not FAISS_DIR.exists():
        print("ERROR: faiss_db nao existe.")
        return 1

    folders = sorted((p for p in FAISS_DIR.iterdir() if p.is_dir()), key=lambda p: p.name.lower())

    if not folders:
        print("WARN: nenhuma pasta de UC encontrada em faiss_db.")
        return 0

    zone_files = sorted(FAISS_DIR.rglob("*Zone.Identifier*"))
    if zone_files:
        warnings += len(zone_files)
        print("\nWARN: ficheiros Zone.Identifier encontrados:")
        for path in zone_files:
            print(f"  - {path.relative_to(ROOT)}")

    print("\nUCs encontradas:")
    for folder in folders:
        canonical = limpar_nome_uc(folder.name)
        manifest_path = folder / MANIFEST_FILE

        print(f"\n- pasta real: {folder.name}")
        print(f"  canonico esperado: {canonical}")

        if folder.name != canonical:
            warnings += 1
            print("  WARN: nome legacy; leituras devem resolver por normalizacao.")

        for filename in REQUIRED_FILES:
            status = _status_file(folder / filename)
            print(f"  {filename}: {status}")
            if status != "ok":
                errors += 1

        manifest_status = _status_file(manifest_path)
        print(f"  {MANIFEST_FILE}: {manifest_status}")
        if manifest_status != "ok":
            warnings += 1
            print("  WARN: manifest em falta; cache deve usar versao sem_versao.")

        print(f"  versao manifest: {_manifest_version(folder)}")

    print(f"\nResumo: {len(folders)} pasta(s), {warnings} aviso(s), {errors} erro(s).")

    if errors:
        print("Resultado: FALHOU - faltam ficheiros obrigatorios de indice.")
        return 1

    print("Resultado: OK - nenhum ficheiro obrigatorio em falta.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
