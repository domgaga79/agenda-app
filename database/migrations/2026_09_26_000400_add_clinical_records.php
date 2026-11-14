<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void {
  Schema::table('customers',function(Blueprint $t){$t->date('birth_date')->nullable();$t->string('guardian_name',150)->nullable();$t->string('guardian_relationship',60)->nullable();$t->string('guardian_phone',30)->nullable();$t->string('address',250)->nullable();$t->string('emergency_contact',150)->nullable();$t->string('emergency_phone',30)->nullable();});
  Schema::table('users',function(Blueprint $t){$t->boolean('clinical_access')->default(false);$t->string('professional_registration',40)->nullable();});
  Schema::create('clinical_records',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('customer_id')->constrained()->restrictOnDelete();$t->foreignId('author_id')->constrained('users')->restrictOnDelete();$t->string('author_name',150);$t->string('author_registration',40);$t->string('kind',20);$t->foreignId('parent_id')->nullable()->constrained('clinical_records')->restrictOnDelete();$t->longText('payload');$t->dateTime('occurred_at');$t->string('request_key',64)->unique();$t->timestamps();$t->index(['company_id','customer_id','kind']);});
  Schema::create('clinical_audits',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('actor_id')->constrained('users')->restrictOnDelete();$t->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();$t->foreignId('target_user_id')->nullable()->constrained('users')->restrictOnDelete();$t->string('event',40);$t->unsignedBigInteger('record_id')->nullable();$t->timestamp('created_at')->useCurrent();$t->index(['company_id','created_at']);});
 }
 public function down():void {
  if(\Illuminate\Support\Facades\DB::table('clinical_records')->exists()||\Illuminate\Support\Facades\DB::table('clinical_audits')->exists())throw new RuntimeException('Há registros clínicos ou de auditoria. Restaure um backup completo em vez de remover estas tabelas.');
  Schema::dropIfExists('clinical_audits');Schema::dropIfExists('clinical_records');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['clinical_access','professional_registration']));Schema::table('customers',fn(Blueprint $t)=>$t->dropColumn(['birth_date','guardian_name','guardian_relationship','guardian_phone','address','emergency_contact','emergency_phone']));
 }
};
