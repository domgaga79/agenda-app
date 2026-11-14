<?php
namespace Tests;
abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase {
 public function createApplication() {
  $app=require dirname(__DIR__).'/bootstrap/app.php';
  $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  if(config('database.default')!=='sqlite' || config('database.connections.sqlite.database')!==':memory:') throw new \RuntimeException('Testes exigem SQLite em memória.');
  return $app;
 }
}
