#!/bin/sh
set -e
BASE="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
[ -d "$BASE/public" ] || { echo "Pasta public não encontrada."; exit 1; }
echo "Landing já incluída nesta versão em: public/landing-page.php"
echo "CSS: public/css/landing-page.css"
echo "JS:  public/js/landing-page.js"
echo "Acesse: https://SEU-DOMINIO/landing-page.php"
