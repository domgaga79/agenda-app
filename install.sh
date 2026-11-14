#!/usr/bin/env bash
# Instalação nova. Para atualizar o sistema existente, use atualizar.sh.
set -Eeuo pipefail
cd -- "$(dirname -- "$0")"
PHP_BIN="${AGENDA_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
[[ -x "$PHP_BIN" ]] || { echo "PHP 8.4 não encontrado. Defina AGENDA_PHP_BIN."; exit 1; }
export PATH="$(dirname "$PHP_BIN"):$PATH"
"$PHP_BIN" -r 'if(PHP_VERSION_ID<80401)exit(1);'
if [[ ! -f .env ]]; then
 cp .env.example .env
 chmod 600 .env
 echo "Edite o .env com o banco exclusivo, URL e credenciais. Depois execute install.sh novamente."
 exit 1
fi
mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
"$PHP_BIN" artisan config:clear
if ! "$PHP_BIN" -r 'require "vendor/autoload.php";$app=require "bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();exit(empty(config("app.key"))?1:0);'; then
 "$PHP_BIN" artisan key:generate --force
fi
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
echo 'Instalação pronta. Use company:create "Nome da Empresa" email@empresa.com para criar o primeiro acesso.'
