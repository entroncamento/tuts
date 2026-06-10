import ast
import importlib.util
import sys
from pathlib import Path

ROOT = Path(".").resolve()
sys.path.insert(0, str(ROOT))

IGNORE_DIRS = {
    "venv", ".venv", "__pycache__", ".git", "faiss_db", "pdfs",
    "node_modules", ".mypy_cache", ".pytest_cache"
}

imports = set()

for file in ROOT.rglob("*.py"):
    if any(part in IGNORE_DIRS for part in file.parts):
        continue

    try:
        tree = ast.parse(file.read_text(encoding="utf-8"))
    except Exception:
        continue

    for node in ast.walk(tree):
        if isinstance(node, ast.Import):
            for alias in node.names:
                imports.add(alias.name.split(".")[0])
        elif isinstance(node, ast.ImportFrom):
            if node.module:
                imports.add(node.module.split(".")[0])

stdlib = getattr(sys, "stdlib_module_names", set())

missing = []

for name in sorted(imports):
    if name in stdlib:
        continue

    if importlib.util.find_spec(name) is None:
        missing.append(name)

if missing:
    print("❌ Imports em falta:")
    for m in missing:
        print(" -", m)
else:
    print("✅ Não encontrei imports em falta.")
