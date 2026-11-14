#!/usr/bin/env bash
set -Eeuo pipefail
APP="$(cd -- "$(dirname -- "$0")/.." && pwd)"
PHP_BIN="${AGENDA_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
[[ -x "$PHP_BIN" ]] || { echo 'PHP não encontrado. Defina AGENDA_PHP_BIN.'; exit 1; }
[[ -f "$APP/vendor/bin/phpunit" ]] || { echo 'PHPUnit não está instalado nesta aplicação. As dependências de desenvolvimento são necessárias para executar a suíte.'; exit 1; }
"$PHP_BIN" -r 'if(PHP_VERSION_ID<80401 || !extension_loaded("pdo_sqlite")){fwrite(STDERR,"Exige PHP 8.4.1+ com pdo_sqlite.\n");exit(1);}'
cd "$APP"
case "${1:-todos}" in
 todos) exec "$PHP_BIN" vendor/bin/phpunit -c phpunit.xml ;;
 fluxo) exec "$PHP_BIN" vendor/bin/phpunit -c phpunit.xml --filter ClinicWorkflowTest ;;
 *) echo 'Uso: bash scripts/testar.sh [todos|fluxo]'; exit 1 ;;
esac
