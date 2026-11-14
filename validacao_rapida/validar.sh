#!/usr/bin/env bash
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

echo "==============================================="
echo " Agenda Bacuri - validação funcional automática"
echo "==============================================="
echo "Raiz: $ROOT"
echo

if [ ! -f artisan ]; then
  echo "FALHA: artisan não encontrado em $ROOT"
  exit 1
fi

if [ ! -f vendor/autoload.php ]; then
  echo "FALHA: vendor/autoload.php não encontrado."
  exit 1
fi

FAIL=0

echo "[1/5] Inicialização do Laravel"
php artisan about >/tmp/agenda_about_$$.txt 2>&1
if [ $? -eq 0 ]; then
  echo "OK: Laravel inicializou."
else
  echo "FALHA: Laravel não inicializou."
  cat /tmp/agenda_about_$$.txt
  FAIL=1
fi
rm -f /tmp/agenda_about_$$.txt
echo

echo "[2/5] Status das migrations"
php artisan migrate:status
if [ $? -ne 0 ]; then
  echo "FALHA: não foi possível consultar migrations."
  FAIL=1
fi
echo

echo "[3/5] Auditoria multitenant"
php artisan tenant:audit --fail
if [ $? -ne 0 ]; then
  echo "FALHA: tenant:audit encontrou problema."
  FAIL=1
fi
echo

echo "[4/5] Boot, banco, schema, rotas, views, storage e HTTP"
php validacao_rapida/smoke.php
if [ $? -ne 0 ]; then
  FAIL=1
fi
echo

echo "[5/5] Rotas registradas"
ROUTES="$(php artisan route:list 2>/dev/null | wc -l | tr -d ' ')"
if [ "${ROUTES:-0}" -gt 20 ]; then
  echo "OK: tabela de rotas carregou (${ROUTES} linhas)."
else
  echo "FALHA: quantidade inesperada de rotas (${ROUTES:-0})."
  FAIL=1
fi
echo

if [ "$FAIL" -eq 0 ]; then
  echo "==============================================="
  echo " APROVADO: validação rápida concluída sem falhas"
  echo "==============================================="
  exit 0
fi

echo "==============================================="
echo " FALHOU: revise as mensagens acima"
echo "==============================================="
exit 1
