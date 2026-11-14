<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('financial_expenses',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->text('description');$t->text('supplier')->nullable();$t->string('category',30);$t->date('due_on');$t->unsignedBigInteger('amount_cents');$t->string('status',20)->default('active');$t->unsignedInteger('revision')->default(1);$t->string('request_key',64)->unique();$t->text('cancel_reason')->nullable();$t->timestamps();$t->index(['company_id','due_on','status']);});
  Schema::create('expense_payments',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('expense_id')->constrained('financial_expenses')->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->unsignedBigInteger('amount_cents');$t->date('paid_on');$t->string('method',20);$t->string('request_key',64)->unique();$t->timestamp('reversed_at')->nullable();$t->foreignId('reversed_by')->nullable()->constrained('users')->restrictOnDelete();$t->text('reversal_reason')->nullable();$t->timestamps();$t->index(['company_id','paid_on']);});
  Schema::create('expense_events',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('expense_id')->constrained('financial_expenses')->restrictOnDelete();$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();$t->string('event',30);$t->longText('payload');$t->timestamp('created_at')->useCurrent();$t->index(['company_id','expense_id']);});
 }
 public function down():void{foreach(['expense_events','expense_payments','financial_expenses'] as $t)if(DB::table($t)->exists())throw new RuntimeException('Há despesas registradas. Restaure um backup completo.');foreach(['expense_events','expense_payments','financial_expenses'] as $t)Schema::dropIfExists($t);}
};
