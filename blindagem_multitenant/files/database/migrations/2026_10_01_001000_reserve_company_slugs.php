<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_slug_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('slug', 100)->unique();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->index('company_id');
        });

        DB::table('companies')->select(['id','slug'])->orderBy('id')->get()->each(function ($company): void {
            DB::table('company_slug_reservations')->insertOrIgnore([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'first_seen_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_slug_reservations');
    }
};
