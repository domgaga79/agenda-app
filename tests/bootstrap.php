<?php
// Isolated SQLite IN MEMORY. Never reads a production database, even if config is cached.
$isolated = [
 'APP_MAINTENANCE_DRIVER'=>'array','APP_ENV'=>'testing','APP_KEY'=>'base64:'.base64_encode(str_repeat('t',32)),
 'APP_CONFIG_CACHE'=>sys_get_temp_dir().'/agenda-config-missing-'.getmypid().'.php',
 'APP_ROUTES_CACHE'=>sys_get_temp_dir().'/agenda-routes-missing-'.getmypid().'.php',
 'APP_URL'=>'http://localhost','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:',
 'DB_URL'=>'','SESSION_DRIVER'=>'array','SESSION_SECURE_COOKIE'=>'false','CACHE_STORE'=>'array',
 'QUEUE_CONNECTION'=>'sync','MAIL_MAILER'=>'array','BCRYPT_ROUNDS'=>'4',
 'AGENDA_REGISTRATION_OPEN'=>'false',
];
foreach($isolated as $key=>$value) { putenv($key.'='.$value); $_ENV[$key]=$value; $_SERVER[$key]=$value; }
require dirname(__DIR__).'/vendor/autoload.php';
