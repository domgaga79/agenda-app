<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('patient_followups',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('customer_id')->constrained()->restrictOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->foreignId('professional_id')->nullable()->constrained('users')->restrictOnDelete();$t->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();$t->string('kind',20);$t->date('from_date');$t->date('to_date')->nullable();$t->string('period',20)->default('any');$t->string('status',20)->default('pending');$t->text('note')->nullable();$t->unsignedInteger('revision')->default(1);$t->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();$t->timestamp('last_contact_at')->nullable();$t->string('request_key',64)->unique();$t->timestamps();$t->index(['company_id','kind','status','from_date'],'followups_scope');});
  Schema::create('followup_events',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('followup_id')->constrained('patient_followups')->restrictOnDelete();$t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();$t->string('event',30);$t->longText('payload');$t->string('request_key',64)->nullable()->unique();$t->timestamp('created_at')->useCurrent();$t->index(['company_id','followup_id']);});
 }
 public function down():void{if(DB::table('patient_followups')->exists()||DB::table('followup_events')->exists())throw new RuntimeException('Há solicitações registradas. Restaure um backup completo.');Schema::dropIfExists('followup_events');Schema::dropIfExists('patient_followups');}
};
