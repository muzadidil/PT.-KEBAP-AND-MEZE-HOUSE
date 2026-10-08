#!/usr/bin/env bash
#
# Memasang versi terbaru kode di hosting, satu perintah:
#
#     bash deploy.sh                 # branch bawaan
#     bash deploy.sh nama-branch     # branch lain
#
# Urutannya: backup database -> ambil kode terbaru -> pasang paket ->
# migrasi -> bersihkan cache. Hanya KODE yang diganti. Database, file .env,
# folder storage, dan public/uploads tidak ditimpa.
#
# Dijalankan DI HOSTING, di folder aplikasi (yang berisi artisan).

set -euo pipefail

BRANCH="${1:-claude/kind-knuth-6x1z5z}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups-kasir}"
KEEP=14

cd "$(dirname "$0")"

[ -f artisan ] || { echo "Jalankan di folder aplikasi (yang ada file artisan)."; exit 1; }
[ -f .env ]    || { echo "File .env tidak ada. Buat dulu dari .env.example."; exit 1; }

# Ambil satu nilai dari .env, tanpa tanda kutip.
env_value() {
    grep -E "^$1=" .env | head -n1 | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//'
}

# Perubahan yang diedit langsung di hosting akan bentrok dengan git pull.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Ada file di hosting yang diubah langsung. Hentikan dan periksa:"
    git status --short --untracked-files=no
    exit 1
fi

echo "==> 1/5 Backup database"
mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$BACKUP_DIR/db-$STAMP.sql"
MYSQL_PWD="$(env_value DB_PASSWORD)" mysqldump \
    --host="$(env_value DB_HOST)" --port="$(env_value DB_PORT)" \
    --user="$(env_value DB_USERNAME)" --single-transaction --no-tablespaces \
    "$(env_value DB_DATABASE)" > "$FILE"
[ -s "$FILE" ] || { echo "Backup kosong, deploy dibatalkan."; rm -f "$FILE"; exit 1; }
echo "    tersimpan: $FILE"
# Simpan $KEEP backup terakhir saja.
ls -1t "$BACKUP_DIR"/db-*.sql 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -f

echo "==> 2/5 Ambil kode terbaru ($BRANCH)"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> 3/5 Pasang paket"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> 4/5 Migrasi database"
php artisan migrate --force

echo "==> 5/5 Bersihkan cache"
php artisan filament:assets
php artisan optimize:clear

echo
echo "Selesai. Buka situsnya dan tekan Ctrl+F5."
echo "Kalau ada masalah, backup sebelum deploy ada di: $FILE"
