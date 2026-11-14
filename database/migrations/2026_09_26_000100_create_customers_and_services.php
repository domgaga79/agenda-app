<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('customers',function(Blueprint $t){
   $t->id(); $t->foreignId('company_id')->constrained()->cascadeOnDelete();
   $t->string('name',150); $t->string('phone',30)->nullable(); $t->string('email',150)->nullable();
   $t->boolean('active')->default(true); $t->timestamps(); $t->index(['company_id','name']);
  });
  Schema::create('services',function(Blueprint $t){
   $t->id(); $t->foreignId('company_id')->constrained()->cascadeOnDelete();
   $t->string('name',150); $t->unsignedSmallInteger('duration_minutes'); $t->unsignedInteger('price_cents')->nullable();
   $t->boolean('active')->default(true); $t->timestamps(); $t->index(['company_id','name']);
  });
 }
 public function down(): void { Schema::dropIfExists('services'); Schema::dropIfExists('customers'); }
};
