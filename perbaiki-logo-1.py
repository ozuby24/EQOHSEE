#!/usr/bin/env python3
# ============================================================
#  perbaiki-logo.py (v2) — Mengganti kotak "E" lime-gradient lama
#  dengan komponen <x-brand>, DAN memperbaiki penggantian sebelumnya
#  yang salah pilih varian gelap/terang.
#
#  Pemakaian:
#    python3 perbaiki-logo.py                 # target: ~/eqohsee
#    python3 perbaiki-logo.py /path/ke/proyek  # target kustom
#
#  Perbedaan dari v1:
#  - v1 memukul rata "kotak+wrapper leading-none = pasti latar gelap".
#    Ternyata ada juga (navbar landing page) yang wrapper-nya sama
#    tapi latar TERANG (glass-light) — jadi versi putih nyaris tak
#    terlihat. v2 mendeteksi gelap/terang dari petunjuk kelas di
#    sekitar kecocokan (brand-gradient/bg-cam-dark = gelap,
#    glass-light/bg-white/bg-cam-cream = terang).
#  - v1 memakai if/elif per BERKAS, jadi kalau satu berkas punya
#    lebih dari satu kotak logo (mis. navbar DAN footer di file yang
#    sama), yang kedua tidak pernah diperiksa. v2 mengulang pencarian
#    sampai tidak ada pola lagi di berkas itu.
#  - v2 juga mendeteksi <x-brand variant="wordmark" dark .../> hasil
#    v1 yang salah taruh di latar terang, dan membetulkannya.
# ============================================================
import re
import sys
import datetime
from pathlib import Path

TARGET = Path(sys.argv[1]).expanduser() if len(sys.argv) > 1 else Path.home() / "eqohsee"
VIEWS  = TARGET / "resources" / "views"
STAMP  = datetime.datetime.now().strftime("%Y%m%d-%H%M%S")

if not VIEWS.is_dir():
    print(f"✘ Folder tidak ditemukan: {VIEWS}")
    print("  Jalankan: python3 perbaiki-logo.py /path/ke/proyek")
    sys.exit(1)

DARK_HINTS  = ["brand-gradient", "bg-cam-dark", "bg-cam-black", "bg-black"]
LIGHT_HINTS = ["glass-light", "bg-white", "bg-cam-cream", "bg-cam-bg", "bg-stone"]

def detect_dark(text: str, pos: int, window: int = 700):
    """Lihat teks SEBELUM posisi kecocokan untuk menebak latar gelap/terang."""
    ctx = text[max(0, pos - window):pos]
    last_dark  = max((ctx.rfind(h) for h in DARK_HINTS), default=-1)
    last_light = max((ctx.rfind(h) for h in LIGHT_HINTS), default=-1)
    if last_dark == -1 and last_light == -1:
        return None
    return last_dark > last_light

# Pola A — kotak ikon + wrapper "leading-none" berisi EQOHSEE + subjudul
PATTERN_FULL = re.compile(
    r'<div class="[^"]*lime-gradient[^"]*"[^>]*>\s*E\s*</div>\s*'
    r'<div class="leading-none">\s*'
    r'<div[^>]*>\s*EQOHSEE\s*</div>\s*'
    r'<div[^>]*>[^<]*</div>\s*'
    r'</div>',
    re.IGNORECASE,
)
# Pola B — kotak ikon + <span>EQOHSEE</span> polos
PATTERN_SPAN = re.compile(
    r'<div class="[^"]*lime-gradient[^"]*"[^>]*>\s*E\s*</div>\s*'
    r'<span[^>]*>\s*EQOHSEE\s*</span>',
    re.IGNORECASE,
)
# Pola C — kotak ikon sendirian (fallback paling aman: ganti ikon saja)
PATTERN_ICON_ONLY = re.compile(
    r'<div class="[^"]*lime-gradient[^"]*"[^>]*>\s*E\s*</div>',
    re.IGNORECASE,
)
# Pola D — perbaikan: <x-brand wordmark dark> hasil v1 yang salah di latar terang
PATTERN_WRONG_DARK = re.compile(
    r'<x-brand variant="wordmark" dark class="([^"]*)"\s*/>',
)

def tag_wordmark(dark: bool, size: str) -> str:
    d = " dark" if dark else ""
    return f'<x-brand variant="wordmark"{d} class="{size}" />'

def patch_text(text: str, fname: str):
    n_changes = 0
    is_guest = "guest" in fname.lower()

    for _ in range(20):  # batas aman agar tak pernah berputar tanpa henti
        m = PATTERN_FULL.search(text)
        if m:
            dark = detect_dark(text, m.start())
            if dark is None:
                dark = is_guest or "app" in fname.lower()  # tebakan terakhir
            size = "h-8" if is_guest else "h-6"
            text = text[:m.start()] + tag_wordmark(dark, size) + text[m.end():]
            n_changes += 1
            continue

        m = PATTERN_SPAN.search(text)
        if m:
            dark = detect_dark(text, m.start())
            text = text[:m.start()] + tag_wordmark(bool(dark), "h-6") + text[m.end():]
            n_changes += 1
            continue

        m = PATTERN_ICON_ONLY.search(text)
        if m:
            text = text[:m.start()] + '<x-brand variant="mark" class="h-8 rounded-xl" />' + text[m.end():]
            n_changes += 1
            continue

        # Perbaiki hasil v1 yang salah: dark dipasang di konteks terang
        m = PATTERN_WRONG_DARK.search(text)
        if m:
            dark_ctx = detect_dark(text, m.start())
            if dark_ctx is False:  # yakin ini terang, tapi ter-tag dark → betulkan
                size = m.group(1)
                text = text[:m.start()] + tag_wordmark(False, size) + text[m.end():]
                n_changes += 1
                continue

        break

    return text, n_changes

changed, skipped = [], []

for fpath in sorted(VIEWS.rglob("*.blade.php")):
    original = fpath.read_text(encoding="utf-8")
    text, n = patch_text(original, fpath.name)

    if n > 0:
        backup = fpath.with_name(fpath.name + f".bak-{STAMP}")
        backup.write_text(original, encoding="utf-8")
        fpath.write_text(text, encoding="utf-8")
        changed.append((str(fpath.relative_to(TARGET)), n))
    elif "lime-gradient" in original and re.search(r">\s*E\s*<", original):
        skipped.append(str(fpath.relative_to(TARGET)))

print("============================================")
print(" Perbaikan Logo EQOHSEE (v2)")
print("============================================")

if changed:
    print(f"\n✔ {len(changed)} berkas diubah (cadangan .bak-{STAMP} di sebelahnya):")
    for c, n in changed:
        print(f"   - {c}  ({n} penggantian)")
else:
    print("\n⚠ Tidak ada perubahan — mungkin sudah rapi semua, atau pola tak dikenali.")

if skipped:
    print(f"\n⚠ {len(skipped)} berkas punya kotak 'E' tapi pola di sekitarnya tak dikenali — cek manual:")
    for s in skipped:
        print(f"   - {s}")

print("\nSelesai. Jalankan 'php artisan optimize:clear' lalu HARD-REFRESH browser.")
