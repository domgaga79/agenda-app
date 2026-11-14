#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
PACKAGE="$(cd -- "$(dirname -- "$0")" && pwd)"
TARGET="${1:-/home/bacuridi/agenda-app}"
PHP_BIN="${AGENDA_PHP_BIN:-/opt/cpanel/ea-php84/root/usr/bin/php}"
[[ -d "$TARGET" && -x "$PHP_BIN" ]] || { echo 'Destino ou PHP não encontrado.'; exit 1; }
TARGET="$(cd -- "$TARGET" && pwd)"
[[ "$PACKAGE" != "$TARGET" ]] || { echo 'Extraia o pacote fora da aplicação, e execute atualizar.sh apontando para ela.'; exit 1; }
[[ -f "$TARGET/.env" && -f "$TARGET/artisan" && -f "$TARGET/vendor/autoload.php" ]] || { echo 'O destino precisa conter o Laravel configurado.'; exit 1; }
[[ ! -e "$TARGET/storage/framework/down" ]] || { echo 'Aplicação já está em manutenção. Confira antes de continuar.'; exit 1; }
export PATH="$(dirname "$PHP_BIN"):$PATH"
"$PHP_BIN" -r 'if(PHP_VERSION_ID<80401){fwrite(STDERR,"É necessário PHP 8.4.1 ou superior.\n");exit(1);}'
command -v composer >/dev/null
"$PHP_BIN" "$PACKAGE/scripts/preflight.php" "$PACKAGE" "$TARGET"
LOCK="$TARGET/.agenda-update.lock"
mkdir "$LOCK" 2>/dev/null || { echo 'Outra atualização está em andamento.'; exit 1; }
BACKUP="$(dirname "$TARGET")/agenda-backups/$(date +%Y%m%d-%H%M%S)-$$"
mkdir -p "$BACKUP"
trap 'rmdir "$LOCK" 2>/dev/null || true' EXIT
trap 'echo "Atualização interrompida. Backup: $BACKUP. Se a manutenção foi ativada, permanece até a correção. Envie o erro sem credenciais." >&2' ERR
"$PHP_BIN" -r '
$m=json_decode(file_get_contents($argv[1]."/update-manifest.json"),true);
$existing=[];$new=[];
foreach($m as $p=>$v){if(is_file($argv[2]."/".$p))$existing[]=$p;else $new[]=$p;}
file_put_contents($argv[3]."/existing-files",implode("\0",$existing).(count($existing)?"\0":""));
file_put_contents($argv[3]."/new-files.json",json_encode($new));
' "$PACKAGE" "$TARGET" "$BACKUP"
tar -czf "$BACKUP/arquivos-anteriores.tar.gz" -C "$TARGET" --null -T "$BACKUP/existing-files"
cp "$PACKAGE/update-manifest.json" "$BACKUP/update-manifest.json"
"$PHP_BIN" "$TARGET/artisan" down --retry=60
"$PHP_BIN" -r '
$m=json_decode(file_get_contents($argv[1]."/update-manifest.json"),true);
foreach($m as $p=>$v){$dest=$argv[2]."/".$p;$dir=dirname($dest);if(!is_dir($dir))mkdir($dir,0755,true);if(!copy($argv[1]."/".$p,$dest))throw new RuntimeException("Falha ao copiar ".$p);chmod($dest,0644);$d=dirname($p);while($d!=="."){chmod($argv[2]."/".$d,0755);$d=dirname($d);}}
' "$PACKAGE" "$TARGET"
composer dump-autoload --no-scripts --optimize --working-dir="$TARGET"
"$PHP_BIN" "$TARGET/artisan" config:clear
"$PHP_BIN" "$TARGET/artisan" route:clear
"$PHP_BIN" "$TARGET/artisan" view:clear
if [[ -f "$TARGET/vendor/bin/phpunit" ]]; then
 "$PHP_BIN" "$TARGET/vendor/bin/phpunit" -c "$TARGET/phpunit.xml"
fi
"$PHP_BIN" "$TARGET/artisan" migrate --force
"$PHP_BIN" "$TARGET/artisan" config:cache
"$PHP_BIN" "$TARGET/artisan" route:cache
"$PHP_BIN" "$TARGET/artisan" view:cache
"$PHP_BIN" "$TARGET/artisan" up
echo "Atualização concluída. Backup de arquivos: $BACKUP"
echo 'Entre em /login com seu acesso existente. Não é necessário recriar a empresa.'
