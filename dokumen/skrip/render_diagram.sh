#!/bin/sh
# Render diagrams/*.drawio -> (PNG untuk DOCX, PDF vektor untuk PDF final).
#
# Kebutuhan (dipasang di /tmp, tidak menetap di ruang kerja):
#   curl -sL -o /tmp/drawio.deb https://github.com/jgraph/drawio-desktop/releases/download/v32.7.4/drawio-amd64-32.7.4.deb
#   dpkg -x /tmp/drawio.deb /tmp/dx
#   sudo apt-get install -y --no-install-recommends libgtk-3-0t64 libnotify4 libnss3 \
#        libxss1 libxtst6 xdg-utils libatspi2.0-0t64 libsecret-1-0 libasound2t64 xvfb xauth librsvg2-bin
#
# Kenapa dua keluaran:
#   * DOCX  -> PNG. Word/LibreOffice paling aman dengan PNG pada DOCX ini.
#   * PDF   -> diagram vektor. PNG selalu pecah saat di-zoom, berapa pun resolusinya;
#              ekspor PDF drawio murni vektor (teks tetap teks, 0 gambar raster).
#              dokumen/skrip/sisip_vektor.py mengganti gambar raster pada PDF hasil
#              LibreOffice dengan jalur vektor ini, pada kotak yang sama.
# Catatan teknis:
#   * Ekspor PNG langsung dari Electron kehabisan memori untuk diagram besar
#     (class-diagram 5200x4600) -> diekspor sebagai SVG/PDF, dirasterkan kemudian.
#   * PDF ekspor drawio membawa kanvas kosong di tepi -> dipangkas ke kotak isi
#     (teks + gambar) supaya diagram tampil sebesar mungkin di halaman.
#   * PNG hasil rsvg-convert transparan; tanpa latar putih, Word/LibreOffice
#     menggambarnya hitam.
set -e
DIAGRAM="${1:-/home/user/LelangIkan.id/diagrams}"
KELUAR="${2:-/tmp/diagram}"
DRAWIO=/tmp/dx/opt/drawio/drawio

mkdir -p "$KELUAR"
cd "$KELUAR"

ekspor() {  # $1 = berkas drawio di repo, $2 = nama dasar keluaran
    cp "$DIAGRAM/$1" "$KELUAR/$2.drawio"
    xvfb-run -a "$DRAWIO" --no-sandbox --disable-gpu --disable-dev-shm-usage \
        --js-flags="--max-old-space-size=3072" \
        --export --format pdf --border 20 \
        --output "$KELUAR/$2.pdf" "$KELUAR/$2.drawio" 2>&1 | grep -v -i "dbus\|Failed to call" || true
}

ekspor usecase-lelangikan.drawio usecase
ekspor erd-lelangikan.drawio erd
ekspor class-diagram-lelangikan.drawio class

python3 /home/user/dokumen/skrip/pangkas_diagram.py "$KELUAR"
