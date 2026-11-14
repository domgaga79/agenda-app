<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
 public function up():void {
  Schema::create('financial_quotes',function(Blueprint $t){
   $t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('customer_id')->constrained()->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();
   $t->foreignId('source_record_id')->nullable()->unique()->constrained('clinical_records')->restrictOnDelete();$t->text('description');$t->string('customer_name',150);$t->unsignedBigInteger('gross_cents');$t->unsignedBigInteger('discount_cents')->default(0);$t->unsignedBigInteger('total_cents');$t->string('status',20)->default('draft');$t->unsignedInteger('revision')->default(1);$t->string('request_key',64)->unique();
   $t->string('approved_by_name',150)->nullable();$t->date('approved_on')->nullable();$t->string('approval_channel',30)->nullable();$t->text('cancel_reason')->nullable();$t->timestamps();$t->index(['company_id','customer_id','status']);
  });
  Schema::create('financial_installments',function(Blueprint $t){
   $t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('quote_id')->constrained('financial_quotes')->restrictOnDelete();$t->unsignedTinyInteger('sequence');$t->boolean('is_deposit')->default(false);$t->date('due_on');$t->unsignedBigInteger('amount_cents');$t->timestamps();$t->unique(['quote_id','sequence']);$t->index(['company_id','due_on']);
  });
  Schema::create('financial_payments',function(Blueprint $t){
   $t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('installment_id')->constrained('financial_installments')->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->unsignedBigInteger('amount_cents');$t->date('paid_on');$t->string('method',20);$t->string('request_key',64)->unique();$t->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamp('reversed_at')->nullable();$t->text('reversal_reason')->nullable();$t->timestamps();$t->index(['company_id','paid_on']);
  });
  Schema::create('financial_events',function(Blueprint $t){
   $t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('quote_id')->constrained('financial_quotes')->restrictOnDelete();$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();$t->string('event',30);$t->unsignedBigInteger('amount_cents')->nullable();$t->unsignedBigInteger('payment_id')->nullable();$t->timestamp('created_at')->useCurrent();$t->index(['company_id','quote_id']);
  });
 }
 public function down():void {
  foreach(['financial_events','financial_payments','financial_installments','financial_quotes'] as $table)if(DB::table($table)->exists())throw new RuntimeException('Há dados financeiros. Restaure um backup completo em vez de apagar estas tabelas.');
  foreach(['financial_events','financial_payments','financial_installments','financial_quotes'] as $table)Schema::dropIfExists($table);
 }
};
