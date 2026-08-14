#!/bin/bash
set -e

echo "Menyiapkan deployment darkoAI..."

# Pastikan berada di direktori yang benar
cd /home/awanda/darkoAI

# Hentikan container lama jika ada
echo "Menghentikan service lama..."
docker compose -f docker-compose.prod.yml down || true

# Tarik image terbaru
echo "Menarik image awandadarkotech/darkoai:latest..."
docker compose -f docker-compose.prod.yml pull

# Jalankan container baru
echo "Membangun dan menjalankan service baru..."
docker compose -f docker-compose.prod.yml up -d

echo "========================================="
echo "Deployment berhasil! "
echo "Gunakan 'docker compose -f docker-compose.prod.yml ps' untuk melihat status."
echo "========================================="
