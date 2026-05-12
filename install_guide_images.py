import zipfile, shutil, os, sys

ZIP_PATH = os.path.join(os.path.expanduser("~"), "Downloads", "admin_guide_fixed.zip")
DST = r"D:\MCP Versi Web\mcp-postgresql\public\admin_guide"

# Cari ZIP di beberapa lokasi umum
if not os.path.exists(ZIP_PATH):
    candidates = [
        os.path.join(os.path.expanduser("~"), "Downloads", "admin_guide_fixed.zip"),
        os.path.join(os.path.expanduser("~"), "Desktop", "admin_guide_fixed.zip"),
        r"D:\admin_guide_fixed.zip",
        r"D:\MCP Versi Web\admin_guide_fixed.zip",
    ]
    for c in candidates:
        if os.path.exists(c):
            ZIP_PATH = c
            break
    else:
        print("ERROR: File admin_guide_fixed.zip tidak ditemukan!")
        print("Pastikan sudah download dan taruh di folder Downloads atau Desktop.")
        print("Kandidat yang dicari:")
        for c in candidates:
            print(" -", c)
        input("\nTekan Enter untuk keluar...")
        sys.exit(1)

print(f"Membaca ZIP dari : {ZIP_PATH}")
print(f"Tujuan folder    : {DST}")
print()

os.makedirs(DST, exist_ok=True)

with zipfile.ZipFile(ZIP_PATH, "r") as z:
    names = z.namelist()
    png_files = [n for n in names if n.endswith(".png")]
    print(f"Ditemukan {len(png_files)} file PNG dalam ZIP")
    print()

    ok = 0
    for name in png_files:
        fname = os.path.basename(name)
        if not fname:
            continue
        data = z.read(name)
        out_path = os.path.join(DST, fname)
        with open(out_path, "wb") as f:
            f.write(data)
        print(f"  OK  {fname}")
        ok += 1

print()
print(f"=== SELESAI: {ok} file berhasil disalin ke:")
print(f"    {DST}")
print()
input("Tekan Enter untuk menutup...")
