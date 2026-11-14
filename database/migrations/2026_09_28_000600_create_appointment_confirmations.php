<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {Schema::create('appointment_confirmations',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('appointment_id')->constrained()->cascadeOnDelete();$t->foreignId('created_by')->constrained('users')->restrictOnDelete();$t->string('token_hash',64)->unique();$t->text('token');$t->string('snapshot',64);$t->string('request_key',64)->unique();$t->timestamp('expires_at');$t->timestamp('revoked_at')->nullable();$t->string('response',20)->nullable();$t->timestamp('responded_at')->nullable();$t->timestamp('sent_at')->nullable();$t->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();$t->timestamps();$t->index(['company_id','appointment_id']);});}
 public function down():void {if(DB::table('appointment_confirmations')->exists())throw new RuntimeException('Há links e respostas registrados. Restaure um backup completo.');Schema::dropIfExists('appointment_confirmations');}
};
