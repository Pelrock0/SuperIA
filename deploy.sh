#!/usr/bin/env bash
#
# deploy.sh — Despliegue incremental de Superlistia en producción (VPS).
# Ejecutar EN el servidor, desde el directorio raíz del proyecto:
#   cd /var/www/superlistia && ./deploy.sh
#
# Requiere: git, composer, php, node/npm y permisos sobre el directorio.
set -euo pipefail

BRANCH="${1:-main}"

echo "▶ Modo mantenimiento"
php artisan down --render="errors::503" || true

# Restaura 'up' aunque algo falle a mitad del deploy
trap 'echo "⚠ Deploy interrumpido — reactivando la app"; php artisan up || true' ERR

echo "▶ Actualizando código ($BRANCH)"
git fetch --all --prune
git reset --hard "origin/$BRANCH"     # descarta cambios locales del server

echo "▶ Dependencias PHP (producción)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "▶ Migraciones"
php artisan migrate --force

echo "▶ Build de frontend (Vite)"
npm ci
npm run build

echo "▶ Recacheando configuración"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "▶ Reiniciando workers de cola"
php artisan queue:restart

echo "▶ Fin de mantenimiento"
php artisan up
trap - ERR

echo "✅ Deploy completado ($(git rev-parse --short HEAD))"
