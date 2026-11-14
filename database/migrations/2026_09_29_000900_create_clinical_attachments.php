<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
 public function up():void {
  Schema::create('clinical_attachments',function(Blueprint $t){$t->engine='InnoDB';$t->id();$t->foreignId('company_id')->constrained()->restrictOnDelete();$t->foreignId('customer_id')->constrained()->restrictOnDelete();$t->foreignId('author_id')->constrained('users')->restrictOnDelete();$t->string('author_name',150);$t->string('author_registration',100)->default('');$t->text('title');$t->text('original_name');$t->text('description')->nullable();$t->string('mime',50);$t->unsignedInteger('bytes');$t->string('sha256',64);$t->string('path')->unique();$t->string('request_key',64)->unique();$t->timestamp('disregarded_at')->nullable();$t->text('disregard_reason')->nullable();$t->timestamps();$t->index(['company_id','customer_id','id'],'attachments_patient');});
  Schema::table('clinical_audits',function(Blueprint $t){$t->foreignId('attachment_id')->nullable()->constrained('clinical_attachments')->restrictOnDelete();});
 }
 public function down():void {if(DB::table('clinical_attachments')->exists())throw new RuntimeException('Há anexos clínicos. Restaure um backup completo, incluindo os arquivos privados.');Schema::table('clinical_audits',fn(Blueprint $t)=>$t->dropConstrainedForeignId('attachment_id'));Schema::dropIfExists('clinical_attachments');}
};
