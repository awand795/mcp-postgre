#!/bin/bash

# Pastikan script berhenti jika ada error
set -e

echo "Menyiapkan deployment darkoAI ke Docker Swarm..."

# Export variables from .env so Docker Stack can resolve ${VAR} references
# Note: Docker Stack (unlike Docker Compose) does NOT read .env automatically
export REDIS_PASSWORD=$(grep '^REDIS_PASSWORD=' .env | cut -d= -f2 | head -1)

# Tarik image terbaru dari registry
echo "Menarik image awandadarkotech/darkoai:latest..."
docker pull awandadarkotech/darkoai:latest

# Deploy ke Docker Swarm
echo "Mendeploy stack darkoAI..."
docker stack deploy -c docker-stack.yml darkoAI --with-registry-auth

echo "Deployment berhasil dikirim ke Swarm. Gunakan 'docker service ls' atau 'docker stack ps darkoAI' untuk melihat status."
