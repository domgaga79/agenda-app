<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('companies',function(Blueprint $t){
   $t->boolean('booking_enabled')->default(false);$t->string('booking_timezone',60)->default('America/Bahia');
   $t->unsignedSmallInteger('booking_notice_hours')->default(2);$t->unsignedSmallInteger('booking_window_days')->default(60);
   $t->string('booking_address',250)->nullable();$t->string('booking_description',1000)->nullable();
  });
  Schema::create('working_periods',function(Blueprint $t){
  $t->engine='InnoDB';
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
   $t->unsignedTinyInteger('weekday');$t->string('start_time',5);$t->string('end_time',5);$t->timestamps();$t->index(['company_id','professional_id','weekday']);
  });
  Schema::create('professional_services',function(Blueprint $t){
  $t->engine='InnoDB';
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('professional_id')->constrained('users')->cascadeOnDelete();
   $t->foreignId('service_id')->constrained('services')->cascadeOnDelete();$t->unique(['professional_id','service_id']);
  });
  Schema::create('schedule_blocks',function(Blueprint $t){
  $t->engine='InnoDB';
   $t->id();$t->foreignId('company_id')->constrained()->cascadeOnDelete();$t->foreignId('professional_id')->nullable()->constrained('users')->cascadeOnDelete();
   $t->dateTime('starts_at');$t->dateTime('ends_at');$t->string('reason',150);$t->timestamps();$t->index(['company_id','starts_at']);
  });
  Schema::table('appointments',function(Blueprint $t){
   $t->unsignedBigInteger('created_by')->nullable()->change();$t->string('source',20)->default('internal');
   $t->string('booking_request_key',64)->nullable()->unique();$t->dateTime('contact_acknowledged_at')->nullable();
  });
 }
 public function down(): void {
  Schema::table('appointments',function(Blueprint $t){$t->dropUnique(['booking_request_key']);$t->dropColumn(['source','booking_request_key','contact_acknowledged_at']);});
  Schema::dropIfExists('schedule_blocks');Schema::dropIfExists('professional_services');Schema::dropIfExists('working_periods');
  Schema::table('companies',function(Blueprint $t){$t->dropColumn(['booking_enabled','booking_timezone','booking_notice_hours','booking_window_days','booking_address','booking_description']);});
  // created_by stays nullable to preserve appointments created by patients.
 }
};
