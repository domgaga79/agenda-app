<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Http;
use App\Models\Company;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

$app = require $base . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$errors = 0;
$warnings = 0;

function ok(string $msg): void {
    echo "OK   {$msg}\n";
}
function fail_check(string $msg): void {
    global $errors;
    $errors++;
    echo "FALHA {$msg}\n";
}
function warn_check(string $msg): void {
    global $warnings;
    $warnings++;
    echo "AVISO {$msg}\n";
}

echo "=== Agenda Bacuri - validação funcional rápida ===\n";
echo "Ambiente: " . app()->environment() . "\n";
echo "Laravel: " . app()->version() . "\n\n";

try {
    DB::select('SELECT 1');
    ok('Conexão com banco de dados');
} catch (Throwable $e) {
    fail_check('Banco de dados: ' . $e->getMessage());
}

$criticalTables = [
    'companies','users','appointments','customers','services',
    'financial_quotes','financial_installments','financial_payments',
    'financial_expenses','expense_payments','clinical_records',
    'clinical_attachments','patient_followups','appointment_confirmations',
    'company_slug_reservations',
];

foreach ($criticalTables as $table) {
    if (Schema::hasTable($table)) {
        ok("Tabela {$table}");
    } else {
        fail_check("Tabela ausente: {$table}");
    }
}

$tenantTables = [
    'users','appointments','customers','services','financial_quotes',
    'financial_installments','financial_payments','financial_expenses',
    'expense_payments','clinical_records','clinical_attachments',
    'patient_followups','appointment_confirmations',
];

foreach ($tenantTables as $table) {
    if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id')) {
        ok("{$table}.company_id");
    } else {
        fail_check("Escopo tenant ausente em {$table}");
    }
}

$criticalRoutes = [
    'login','dashboard','appointments.index','customers.index','services.index',
    'clinical.index','finance.index','expenses.index','followups.index',
    'booking.index','confirmation.show',
];

foreach ($criticalRoutes as $name) {
    if (Route::has($name)) {
        ok("Rota {$name}");
    } else {
        fail_check("Rota ausente: {$name}");
    }
}

$criticalViews = ['auth.login','booking.index'];
foreach ($criticalViews as $view) {
    if (View::exists($view)) {
        ok("View {$view}");
    } else {
        fail_check("View ausente: {$view}");
    }
}

$temp = storage_path('framework/cache/agenda-smoke-' . bin2hex(random_bytes(4)) . '.tmp');
try {
    if (@file_put_contents($temp, 'ok') === false) {
        throw new RuntimeException('não foi possível gravar arquivo temporário');
    }
    @unlink($temp);
    ok('Storage/framework gravável');
} catch (Throwable $e) {
    fail_check('Storage: ' . $e->getMessage());
}

try {
    $companyCount = DB::table('companies')->count();
    $userCount = DB::table('users')->count();
    ok("Leitura de dados: {$companyCount} empresa(s), {$userCount} usuário(s)");
} catch (Throwable $e) {
    fail_check('Leitura de dados: ' . $e->getMessage());
}

try {
    $orphanUsers = DB::table('users')
        ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
        ->whereNull('companies.id')
        ->count();
    if ($orphanUsers === 0) ok('Nenhum usuário órfão de empresa');
    else fail_check("{$orphanUsers} usuário(s) sem empresa válida");
} catch (Throwable $e) {
    warn_check('Não foi possível checar usuários órfãos: ' . $e->getMessage());
}

try {
    $appUrl = rtrim((string) config('app.url'), '/');
    if ($appUrl !== '') {
        try {
            $response = Http::timeout(8)->withoutVerifying()->get($appUrl . '/up');
            if ($response->successful()) ok("HTTP /up ({$response->status()})");
            else warn_check("HTTP /up respondeu {$response->status()}");
        } catch (Throwable $e) {
            warn_check('Teste HTTP /up indisponível: ' . $e->getMessage());
        }

        try {
            $response = Http::timeout(8)->withoutVerifying()->get($appUrl . '/login');
            if ($response->successful()) ok("HTTP /login ({$response->status()})");
            else warn_check("HTTP /login respondeu {$response->status()}");
        } catch (Throwable $e) {
            warn_check('Teste HTTP /login indisponível: ' . $e->getMessage());
        }

        try {
            $company = Company::query()
                ->where('active', true)
                ->where('booking_enabled', true)
                ->whereNotNull('slug')
                ->first();

            if ($company) {
                $response = Http::timeout(8)->withoutVerifying()->get(
                    $appUrl . '/agendar/' . rawurlencode((string) $company->slug)
                );
                if ($response->successful()) {
                    ok("Agendamento público /agendar/{$company->slug} ({$response->status()})");
                } else {
                    warn_check("Agendamento público respondeu {$response->status()}");
                }
            } else {
                warn_check('Nenhuma empresa ativa com agendamento público habilitado para teste HTTP');
            }
        } catch (Throwable $e) {
            warn_check('Teste HTTP de agendamento público indisponível: ' . $e->getMessage());
        }
    } else {
        warn_check('APP_URL vazio; testes HTTP externos foram ignorados');
    }
} catch (Throwable $e) {
    warn_check('Não foi possível preparar testes HTTP: ' . $e->getMessage());
}

echo "\nResumo: {$errors} falha(s), {$warnings} aviso(s).\n";

if ($errors > 0) {
    echo "RESULTADO: FALHOU\n";
    exit(1);
}

echo "RESULTADO: APROVADO\n";
exit(0);
