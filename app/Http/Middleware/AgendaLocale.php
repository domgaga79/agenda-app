<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class AgendaLocale {
 public function handle(Request $request, Closure $next) { app()->setLocale('pt_BR'); return $next($request); }
}
