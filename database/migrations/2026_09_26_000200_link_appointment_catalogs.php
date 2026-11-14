<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::table('appointments',function(Blueprint $t){
  $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
  $t->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
  $t->unsignedInteger('price_cents')->nullable();
 }); }
 public function down(): void { Schema::table('appointments',function(Blueprint $t){
  $t->dropConstrainedForeignId('customer_id'); $t->dropConstrainedForeignId('service_id'); $t->dropColumn('price_cents');
 }); }
};
