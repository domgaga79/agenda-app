<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Compatibilidade:
         * - instalação nova: cria companies
         * - banco antigo: preserva companies existente
         */
        if (! Schema::hasTable('companies')) {
            Schema::create('companies', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('phone', 30)->nullable();
                $table->string('email')->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('companies', 'email')) {
                Schema::table('companies', function (Blueprint $table) {
                    $table->string('email')->nullable()->after('phone');
                });
            }
        }

        /*
         * O banco anterior já pode possuir alguns destes campos.
         * Adicionamos somente o que estiver faltando.
         */
        $companyIdMissing = ! Schema::hasColumn('users', 'company_id');
        $phoneMissing = ! Schema::hasColumn('users', 'phone');
        $roleMissing = ! Schema::hasColumn('users', 'role');
        $activeMissing = ! Schema::hasColumn('users', 'active');
        $mustChangePasswordMissing = ! Schema::hasColumn('users', 'must_change_password');

        if (
            $companyIdMissing ||
            $phoneMissing ||
            $roleMissing ||
            $activeMissing ||
            $mustChangePasswordMissing
        ) {
            Schema::table('users', function (Blueprint $table) use (
                $companyIdMissing,
                $phoneMissing,
                $roleMissing,
                $activeMissing,
                $mustChangePasswordMissing
            ) {
                if ($companyIdMissing) {
                    $table->foreignId('company_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('companies')
                        ->cascadeOnDelete();
                }

                if ($phoneMissing) {
                    $table->string('phone', 30)
                        ->nullable()
                        ->after('email');
                }

                if ($roleMissing) {
                    $table->string('role', 30)
                        ->default('professional')
                        ->after('phone');
                }

                if ($activeMissing) {
                    $table->boolean('active')
                        ->default(true)
                        ->after('password');
                }

                if ($mustChangePasswordMissing) {
                    $table->boolean('must_change_password')
                        ->default(false)
                        ->after('active');
                }

                /*
                 * Na instalação nova company_id e role são criados aqui,
                 * portanto também criamos o índice.
                 * No banco legado ele já existe.
                 */
                if ($companyIdMissing || $roleMissing) {
                    $table->index(
                        ['company_id', 'role'],
                        'users_company_id_role_index'
                    );
                }
            });
        }
    }

    public function down(): void
    {
        /*
         * Se segment existe, estamos no banco legado.
         * Nesse caso jamais removemos companies nem os campos históricos.
         */
        $legacyDatabase =
            Schema::hasTable('companies') &&
            Schema::hasColumn('companies', 'segment');

        if ($legacyDatabase) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'must_change_password')) {
                    $table->dropColumn('must_change_password');
                }

                if (Schema::hasColumn('users', 'phone')) {
                    $table->dropColumn('phone');
                }
            });

            Schema::table('companies', function (Blueprint $table) {
                if (Schema::hasColumn('companies', 'email')) {
                    $table->dropColumn('email');
                }
            });

            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'company_id')) {
                $table->dropForeign(['company_id']);
            }

            $columns = [];

            foreach ([
                'company_id',
                'phone',
                'role',
                'active',
                'must_change_password'
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('companies');
    }
};
