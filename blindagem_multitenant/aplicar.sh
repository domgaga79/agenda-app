#!/usr/bin/env bash
set -euo pipefail

PATCH_DIR="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
ROOT="$(CDPATH= cd -- "$PATCH_DIR/.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$ROOT/_backup_multitenant_$STAMP"

if [ ! -f "$ROOT/artisan" ]; then
  echo "ERRO: artisan não encontrado em $ROOT"
  echo "Extraia a pasta blindagem_multitenant dentro da raiz do agenda_app."
  exit 1
fi

mkdir -p "$BACKUP"
while IFS= read -r rel; do
  [ -n "$rel" ] || continue
  if [ -f "$ROOT/$rel" ]; then
    mkdir -p "$BACKUP/$(dirname "$rel")"
    cp -a "$ROOT/$rel" "$BACKUP/$rel"
  fi
done < "$PATCH_DIR/ARQUIVOS.txt"

echo "Backup criado em: $BACKUP"
echo "Aplicando arquivos de blindagem..."
cp -a "$PATCH_DIR/files/." "$ROOT/"

cd "$ROOT"

echo "Limpando caches do Laravel..."
"$PHP_BIN" artisan optimize:clear

echo "Aplicando migration de reserva de slugs..."
"$PHP_BIN" artisan migrate --force

echo "Auditando referências entre tenants..."
"$PHP_BIN" artisan tenant:audit

echo
echo "Blindagem multitenant aplicada."
echo "Backup dos arquivos anteriores: $BACKUP"
echo "Recomendado: valide login, agenda, financeiro, prontuário e agendamento público."
