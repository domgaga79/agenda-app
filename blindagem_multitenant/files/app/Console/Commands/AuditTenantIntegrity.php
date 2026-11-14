<?php

namespace App\Console\Commands;

use App\Support\TenantIntegrity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditTenantIntegrity extends Command
{
    protected $signature = 'tenant:audit {--fail : Return a non-zero exit code when inconsistencies are found}';
    protected $description = 'Audita referências entre tenants e detecta vínculos com company_id divergente.';

    public function handle(): int
    {
        $problems = 0;

        foreach (TenantIntegrity::references() as $modelClass => $references) {
            $model = new $modelClass();
            $table = $model->getTable();
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            foreach ($references as $column => $targetTable) {
                if (!Schema::hasColumn($table, $column) || !Schema::hasTable($targetTable) || !Schema::hasColumn($targetTable, 'company_id')) {
                    continue;
                }

                $count = DB::table($table.' as src')
                    ->join($targetTable.' as dst', 'dst.id', '=', 'src.'.$column)
                    ->whereNotNull('src.'.$column)
                    ->whereColumn('src.company_id', '<>', 'dst.company_id')
                    ->count();

                if ($count > 0) {
                    $problems += $count;
                    $this->error("{$table}.{$column} -> {$targetTable}: {$count} vínculo(s) entre empresas diferentes");
                }
            }
        }

        // professional_services has no Eloquent model, so audit it explicitly.
        if (Schema::hasTable('professional_services')) {
            foreach ([['professional_id','users'],['service_id','services']] as [$column,$targetTable]) {
                $count = DB::table('professional_services as src')
                    ->join($targetTable.' as dst', 'dst.id', '=', 'src.'.$column)
                    ->whereColumn('src.company_id', '<>', 'dst.company_id')
                    ->count();
                if ($count > 0) {
                    $problems += $count;
                    $this->error("professional_services.{$column} -> {$targetTable}: {$count} vínculo(s) entre empresas diferentes");
                }
            }
        }

        if ($problems === 0) {
            $this->info('OK: nenhuma referência cruzada entre tenants foi encontrada.');
            return self::SUCCESS;
        }

        $this->warn("Total de inconsistências encontradas: {$problems}. Não corrija automaticamente sem revisar os registros.");
        return $this->option('fail') ? self::FAILURE : self::SUCCESS;
    }
}
