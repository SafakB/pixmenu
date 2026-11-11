#!/usr/bin/env python3
"""
theme-skill drift kontrolü.

Skill'in anlattığı tema yapısı (layout'lar, partial'lar, helper, app.js yamaları,
public/assets/libs içeriği) ile koddaki gerçek durum arasında fark var mı bakar.

Kullanım (proje kökünden):
    python -I .claude/skills/theme-skill/scripts/check_drift.py           # rapor
    python -I .claude/skills/theme-skill/scripts/check_drift.py --accept  # mevcut durumu kabul et (manifest'i yenile)
    python -I .claude/skills/theme-skill/scripts/check_drift.py --hook    # PostToolUse hook modu (stdin: tool JSON)

Çıkış kodları: 0 = fark yok / ilgisiz dosya, 1 = fark var (rapor modu), 2 = fark var (hook modu; stderr Claude'a gider).
"""
from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path

SKILL_DIR = Path(__file__).resolve().parent.parent
ROOT = SKILL_DIR.parent.parent.parent  # .claude/skills/theme-skill -> proje kökü
MANIFEST = SKILL_DIR / "manifest.json"

# Hash'i izlenen dosyalar (glob, proje köküne göre)
WATCHED_GLOBS = [
    "app/Views/layouts/*.php",
    "app/Views/partials/layout/*.php",
    "app/Views/partials/flash.php",
    "app/Views/partials/login_form.php",
    "app/Views/partials/translations.php",
    "app/Helpers/pixmenu_helper.php",
    "app/Config/View.php",
    "public/assets/js/app.js",
    "public/assets/js/layout.js",
    "public/assets/js/pix-confirm.js",
    "docs/VIEW_CONTRACT.md",
]

# İçeriği listelenen dizinler (dosya/klasör eklenip çıkarılması fark sayılır)
LISTED_DIRS = [
    "public/assets/libs",
    "public/assets/js",
    "public/assets/js/pages",
    "public/assets/css",
]

# Skill metninin dayandığı yapısal gerçekler: (dosya, regex, açıklama)
FACTS = [
    ("app/Views/layouts/admin.php", r"renderSection\('content'\)", "admin layout: content section"),
    ("app/Views/layouts/admin.php", r"renderSection\('css'\)", "admin layout: css section"),
    ("app/Views/layouts/admin.php", r"renderSection\('js'\)", "admin layout: js section"),
    ("app/Views/layouts/admin.php", r"partials/layout/page_title", "admin layout: page_title partial"),
    ("app/Views/layouts/panel.php", r"renderSection\('content'\)", "panel layout: content section"),
    ("app/Views/layouts/panel.php", r"renderSection\('css'\)", "panel layout: css section"),
    ("app/Views/layouts/panel.php", r"renderSection\('js'\)", "panel layout: js section"),
    ("app/Views/layouts/panel.php", r"\$isOwner", "panel layout: isOwner ile menü"),
    ("app/Views/layouts/auth.php", r"renderSection\('heading'\)", "auth layout: heading section"),
    ("app/Views/layouts/auth.php", r"renderSection\('subtitle'\)", "auth layout: subtitle section"),
    ("app/Views/partials/layout/sidebar.php", r"url_is\(", "sidebar: url_is ile aktif menü"),
    ("app/Views/partials/layout/head.php", r"PIX_ASSET_BASE", "head: window.PIX_ASSET_BASE"),
    ("app/Helpers/pixmenu_helper.php", r"function asset_url\(", "helper: asset_url()"),
    ("app/Views/partials/layout/scripts.php", r"js/pix-confirm\.js", "scripts: pix-confirm.js yüklü"),
    ("app/Views/partials/layout/scripts.php", r"sweetalert2\.min\.js", "scripts: sweetalert2 yüklü"),
    ("public/assets/js/pix-confirm.js", r"data-confirm", "pix-confirm.js: form[data-confirm] sözleşmesi"),
    ("app/Config/View.php", r"\$saveData\s*=\s*true", "Config\\View::$saveData = true"),
    ("public/assets/js/app.js", r"window\.PIX_ASSET_BASE\+", "app.js yaması 1: PIX_ASSET_BASE"),
    ("public/assets/js/app.js", r'function o\(\)\{document\.getElementById\("header-lang-img"\)&&', "app.js yaması 2: dil yükleyici guard"),
    ("public/assets/js/app.js", r'getElementById\("removeNotificationModal"\)&&', "app.js yaması 3: bildirim modal guard"),
]
ABSENT_FACTS = [
    ("public/assets/js/app.js", r"L\.click\(\)", "app.js yaması 4: customizer otomatik açılmamalı"),
    ("public/assets/js/app.js", r'"assets/', "app.js yaması 1: göreli \"assets/ yolu kalmamalı"),
]


def rel(p: Path) -> str:
    return p.relative_to(ROOT).as_posix()


def watched_files() -> list[Path]:
    files: set[Path] = set()
    for pattern in WATCHED_GLOBS:
        files.update(p for p in ROOT.glob(pattern) if p.is_file())
    return sorted(files)


def sha256(p: Path) -> str:
    # Satır sonu farkı (CRLF/LF, git autocrlf) yapısal değişiklik sayılmasın
    return hashlib.sha256(p.read_bytes().replace(b"\r\n", b"\n")).hexdigest()


def snapshot() -> dict:
    return {
        "files": {rel(p): sha256(p) for p in watched_files()},
        "dirs": {
            d: sorted(c.name for c in (ROOT / d).iterdir()) if (ROOT / d).is_dir() else None
            for d in LISTED_DIRS
        },
    }


def check_facts() -> list[str]:
    problems = []
    for path, pattern, desc in FACTS:
        f = ROOT / path
        if not f.is_file() or not re.search(pattern, f.read_text(encoding="utf-8", errors="replace")):
            problems.append(f"beklenen yapı yok: {desc} ({path})")
    for path, pattern, desc in ABSENT_FACTS:
        f = ROOT / path
        if f.is_file() and re.search(pattern, f.read_text(encoding="utf-8", errors="replace")):
            problems.append(f"yama bozulmuş: {desc} ({path})")
    return problems


def diff() -> list[str]:
    problems = check_facts()
    if not MANIFEST.is_file():
        return problems + ["manifest.json yok — `--accept` ile oluşturun"]

    old = json.loads(MANIFEST.read_text(encoding="utf-8"))
    new = snapshot()

    old_files, new_files = old.get("files", {}), new["files"]
    for path in sorted(set(old_files) | set(new_files)):
        if path not in new_files:
            problems.append(f"silindi: {path}")
        elif path not in old_files:
            problems.append(f"yeni dosya: {path}")
        elif old_files[path] != new_files[path]:
            problems.append(f"değişti: {path}")

    for d, entries in new["dirs"].items():
        before = old.get("dirs", {}).get(d)
        if before is None and entries is None:
            continue
        before, entries = set(before or []), set(entries or [])
        for name in sorted(entries - before):
            problems.append(f"eklendi: {d}/{name}")
        for name in sorted(before - entries):
            problems.append(f"kaldırıldı: {d}/{name}")
    return problems


def report(problems: list[str]) -> str:
    lines = ["theme-skill güncellenmeli — tema yapısı skill'in anlattığından farklı:"]
    lines += [f"  - {p}" for p in problems]
    lines += [
        "Yapılacak: değişikliği incele (git diff), .claude/skills/theme-skill/ altındaki ilgili",
        "referansı (project-structure.md / assets-and-patches.md / patterns.md) güncelle, kullanıcıya bildir,",
        "sonra: python -I .claude/skills/theme-skill/scripts/check_drift.py --accept",
    ]
    return "\n".join(lines)


def is_watched(path_str: str) -> bool:
    try:
        p = Path(path_str).resolve()
        r = p.relative_to(ROOT).as_posix()
    except (ValueError, OSError):
        return False
    if any(Path(r).match(g) for g in WATCHED_GLOBS):
        return True
    return any(r.startswith(d + "/") for d in LISTED_DIRS)


def main() -> int:
    args = sys.argv[1:]

    if "--accept" in args:
        facts = check_facts()
        MANIFEST.write_text(json.dumps(snapshot(), indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
        print(f"manifest güncellendi ({len(snapshot()['files'])} dosya).")
        if facts:
            print("UYARI — yapısal kontroller hâlâ başarısız (skill metni ve FACTS listesi güncellenmeli):")
            for p in facts:
                print(f"  - {p}")
            return 1
        return 0

    if "--hook" in args:
        try:
            payload = json.load(sys.stdin)
        except Exception:
            return 0
        tool_input = payload.get("tool_input") or {}
        path = tool_input.get("file_path") or tool_input.get("notebook_path") or ""
        if not path or not is_watched(path):
            return 0
        problems = diff()
        if problems:
            print(report(problems), file=sys.stderr)
            return 2
        return 0

    problems = diff()
    if problems:
        print(report(problems))
        return 1
    print("OK — theme-skill güncel.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
