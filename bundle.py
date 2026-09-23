#!/usr/bin/env python3
"""
PayrollOS Project Bundler
--------------------------
Poore project ko ek hi .txt file mein export karta hai (payslip_bundle.txt format).

Usage:
    python bundle_project.py

Output:
    payslip_bundle.txt  (project root mein)

Notes:
    - vendor/, node_modules/, storage/ SKIP hote hain (regenerate ho sakte hain)
    - .env INCLUDE NAHI hota (security: APP_KEY/DB password protect rehte hain)
    - Binary files (images, PDFs, fonts) skip hote hain
"""
import os
from pathlib import Path

PROJECT_ROOT = Path(__file__).resolve().parent
OUTPUT_FILE = PROJECT_ROOT / "payslip_bundle.txt"
PROJECT_NAME = "payslip"

# ---------- Kya include karna hai ----------
INCLUDE_DIRS = [
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "tests",
]

INCLUDE_ROOT_FILES = [
    "composer.json",
    "package.json",
    "tsconfig.json",
    "vite.config.js",
    "postcss.config.js",
    "tailwind.config.js",
    ".env.example",
    "boost.json",
    "artisan",
]

# ---------- Kya skip karna hai ----------
EXCLUDE_DIRS = {
    "vendor", "node_modules", ".git", ".idea", ".vscode",
    "storage", ".cache", "build",
}

EXCLUDE_FILE_NAMES = {
    ".DS_Store", "Thumbs.db", "package-lock.json", "composer.lock",
    ".env",  # 🔒 secrets kabhi bundle mein nahi jane chahiye
}

EXCLUDE_EXTENSIONS = {
    ".pdf", ".png", ".jpg", ".jpeg", ".gif", ".svg", ".ico", ".webp",
    ".woff", ".woff2", ".ttf", ".eot", ".otf",
    ".zip", ".rar", ".7z", ".sqlite", ".db", ".map",
}

MAX_FILE_SIZE = 300 * 1024  # 300 KB se bari file skip (generated assets protection)


def should_skip(rel_path: Path) -> bool:
    if any(part in EXCLUDE_DIRS for part in rel_path.parts):
        return True
    if rel_path.name in EXCLUDE_FILE_NAMES:
        return True
    if rel_path.suffix.lower() in EXCLUDE_EXTENSIONS:
        return True
    return False


def is_text_file(path: Path) -> bool:
    """Binary file detection ka simple heuristic."""
    try:
        with open(path, "r", encoding="utf-8") as f:
            f.read(1024)
        return True
    except (UnicodeDecodeError, OSError):
        return False


def collect_files():
    files = []

    # Directories (recursive)
    for d in INCLUDE_DIRS:
        base = PROJECT_ROOT / d
        if not base.exists():
            continue
        for path in sorted(base.rglob("*")):
            if not path.is_file():
                continue
            rel = path.relative_to(PROJECT_ROOT)
            if should_skip(rel):
                continue
            if path.stat().st_size > MAX_FILE_SIZE:
                continue
            if not is_text_file(path):
                continue
            files.append(path)

    # Root files
    for f in INCLUDE_ROOT_FILES:
        p = PROJECT_ROOT / f
        if p.exists() and p.is_file():
            files.append(p)

    return files


def main():
    files = collect_files()

    parts = ["PROJECT BUNDLE: app + resources + routes + config + database + tests"]
    count = 0

    for path in files:
        rel = path.relative_to(PROJECT_ROOT).as_posix()
        try:
            content = path.read_text(encoding="utf-8", errors="replace")
        except Exception as e:
            content = f"[SKIPPED: could not read — {e}]"
        parts.append(f"===== FILE: {PROJECT_NAME}/{rel} =====")
        parts.append(content)
        count += 1

    OUTPUT_FILE.write_text("\n".join(parts), encoding="utf-8")

    size_mb = OUTPUT_FILE.stat().st_size / (1024 * 1024)
    print("=" * 50)
    print(f"✅ Bundle complete!")
    print(f"📁 Files bundled : {count}")
    print(f"📄 Output file   : {OUTPUT_FILE}")
    print(f"📦 Size          : {size_mb:.2f} MB")
    print("=" * 50)
    print("🔒 Note: .env, vendor/, node_modules/, storage/ Not included")


if __name__ == "__main__":
    main()