<?php
namespace Tests\Feature;
use App\Models\{Company,User,Service,Appointment,WorkingPeriod,ScheduleBlock};
use App\Services\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;
class DentalBookingTest extends TestCase {
 use RefreshDatabase;
 private Company $clinic;private User $owner;private User $dentist;private Service $procedure;
 protected function setUp():void {
  parent::setUp();Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00','America/Bahia'));
  $this->clinic=Company::create(['name'=>'Clínica Sorriso','slug'=>'sorriso','active'=>true,'booking_enabled'=>true]);
  $this->owner=$this->user('owner');$this->dentist=$this->user('professional');
  $this->procedure=Service::create(['company_id'=>$this->clinic->id,'name'=>'Avaliação','duration_minutes'=>30,'price_cents'=>10000,'active'=>true]);
  DB::table('professional_services')->insert(['company_id'=>$this->clinic->id,'professional_id'=>$this->dentist->id,'service_id'=>$this->procedure->id]);
  WorkingPeriod::create(['company_id'=>$this->clinic->id,'professional_id'=>$this->dentist->id,'weekday'=>1,'start_time'=>'09:00','end_time'=>'12:00']);
 }
 protected function tearDown():void {Carbon::setTestNow();parent::tearDown();}
 private function user(string $role,?Company $c=null):User {return User::create(['company_id'=>($c??$this->clinic)->id,'name'=>'Dentista '.$role,'email'=>uniqid().'@test.test','password'=>'test-password','role'=>$role,'active'=>true,'must_change_password'=>false]);}
 private function url():string{return route('booking.index',['slug'=>$this->clinic->slug]);}
 private function form(string $nonce='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'):array{return ['professional_id'=>$this->dentist->id,'service_id'=>$this->procedure->id,'starts_at'=>'2026-09-28 10:00','client_name'=>'Paciente Exemplo','client_phone'=>'(71) 99999-2222','acknowledge'=>'1','nonce'=>$nonce];}
 private function sessionNonce(string $nonce='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'):self {$this->withSession(['booking_nonce.'.$this->clinic->id=>$nonce]);return $this;}
 private function slots():array{return app(Availability::class)->slots($this->clinic,$this->dentist,$this->procedure,'2026-09-28');}
 public function test_public_reservation_uses_server_duration_and_private_receipt():void {
  $this->get($this->url().'?service_id='.$this->procedure->id.'&professional_id='.$this->dentist->id.'&date=2026-09-28')->assertOk()->assertSee('10:00')->assertDontSee('09:00');
  $this->sessionNonce()->post($this->url(),$this->form()+['ends_at'=>'2026-09-28 23:00','status'=>'completed','price_cents'=>1,'customer_id'=>999,'company_id'=>999])->assertRedirect(route('booking.done','sorriso'));
  $a=Appointment::sole();$this->assertNull($a->created_by);$this->assertNull($a->customer_id);$this->assertSame('10:30',$a->ends_at->format('H:i'));$this->assertSame('public',$a->source);$this->assertSame('scheduled',$a->status);$this->assertEquals(10000,$a->price_cents);$this->assertEquals($this->clinic->id,$a->company_id);
  $this->get(route('booking.done','sorriso'))->assertOk()->assertSee('Seu horário está reservado.')->assertHeader('Referrer-Policy','no-referrer');
  $this->flushSession();$this->get(route('booking.done','sorriso'))->assertNotFound();
 }
 public function test_duplicate_submit_is_idempotent_and_another_patient_cannot_take_slot():void {
  $this->sessionNonce()->post($this->url(),$this->form())->assertSessionHasNoErrors();
  $this->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->assertDatabaseCount('appointments',1);
  $nonce=str_repeat('b',48);$this->sessionNonce($nonce)->post($this->url(),$this->form($nonce))->assertSessionHasErrors('starts_at');$this->assertDatabaseCount('appointments',1);
 }
 public function test_slots_respect_breaks_blocks_notice_horizon_and_cancellation():void {
  $slots=$this->slots();$this->assertArrayNotHasKey('2026-09-28 09:45',$slots);$this->assertArrayHasKey('2026-09-28 10:00',$slots);$this->assertArrayHasKey('2026-09-28 11:30',$slots);$this->assertArrayNotHasKey('2026-09-28 11:45',$slots);
  ScheduleBlock::create(['company_id'=>$this->clinic->id,'professional_id'=>null,'starts_at'=>'2026-09-28 10:30','ends_at'=>'2026-09-28 11:00','reason'=>'Motivo privado']);
  $this->assertArrayNotHasKey('2026-09-28 10:15',$this->slots());$this->assertArrayHasKey('2026-09-28 11:00',$this->slots());
  $this->assertEmpty(app(Availability::class)->slots($this->clinic,$this->dentist,$this->procedure,'2027-01-04'));
  $this->sessionNonce()->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->assertArrayNotHasKey('2026-09-28 10:00',$this->slots());
  Appointment::sole()->update(['status'=>'cancelled']);$this->assertArrayHasKey('2026-09-28 10:00',$this->slots());
  $this->get($this->url().'?service_id='.$this->procedure->id.'&professional_id='.$this->dentist->id)->assertDontSee('Motivo privado')->assertDontSee('Paciente Exemplo');
 }
 public function test_inactive_and_disabled_entities_are_not_bookable():void {
  $this->dentist->update(['active'=>false]);$this->assertEmpty($this->slots());$this->sessionNonce()->post($this->url(),$this->form())->assertNotFound();
  $this->dentist->update(['active'=>true]);$this->procedure->update(['active'=>false]);$this->assertEmpty($this->slots());
  $this->clinic->update(['booking_enabled'=>false]);$this->get($this->url())->assertNotFound();
  $this->clinic->update(['booking_enabled'=>true,'active'=>false]);$this->get($this->url())->assertNotFound();
 }
 public function test_cross_clinic_professional_and_service_are_rejected():void {
  $other=Company::create(['name'=>'Outra','slug'=>'outra']);$pro=$this->user('professional',$other);
  $this->sessionNonce()->post($this->url(),array_replace($this->form(),['professional_id'=>$pro->id]))->assertNotFound();
  $this->actingAs($this->owner)->post(route('availability.period'),['professional_id'=>$pro->id,'weekday'=>1,'start_time'=>'13:00','end_time'=>'17:00'])->assertNotFound();
  $s=Service::create(['company_id'=>$other->id,'name'=>'Privado','active'=>true,'duration_minutes'=>30,'price_cents'=>0]);
  $this->put(route('availability.services'),['professional_id'=>$this->dentist->id,'services'=>[$s->id]])->assertSessionHasErrors('services');
  $this->assertDatabaseHas('professional_services',['service_id'=>$this->procedure->id]);
 }
 public function test_management_permissions_and_period_overlap():void {
  $this->actingAs($this->dentist)->get(route('availability.index'))->assertForbidden();
  $this->flushSession();$this->actingAs($this->owner)->get(route('availability.index'))->assertOk()->assertSee('Expediente por dentista');
  $this->post(route('availability.period'),['professional_id'=>$this->dentist->id,'weekday'=>1,'start_time'=>'11:00','end_time'=>'13:00'])->assertSessionHasErrors('start_time');
  $this->post(route('availability.period'),['professional_id'=>$this->dentist->id,'weekday'=>1,'start_time'=>'13:00','end_time'=>'17:00'])->assertSessionHasNoErrors();
  $reception=$this->user('reception');$this->flushSession();$this->actingAs($reception)->get(route('availability.index'))->assertOk();
  $this->put(route('availability.settings'),[])->assertForbidden();
 }
 public function test_existing_appointments_prevent_block_creation():void {
  $this->sessionNonce()->post($this->url(),$this->form())->assertSessionHasNoErrors();
  $this->actingAs($this->owner)->post(route('availability.block'),['professional_id'=>$this->dentist->id,'starts_at'=>'2026-09-28T10:15','ends_at'=>'2026-09-28T11:00','reason'=>'Ausência'])->assertSessionHasErrors('starts_at');
  $this->assertDatabaseCount('schedule_blocks',0);
 }
 public function test_internal_schedule_respects_blocks_and_hours_and_preserves_unchanged_bookings():void {
  $data=['professional_id'=>$this->dentist->id,'client_name'=>'Interno','service'=>'Avaliação','starts_at'=>'2026-09-28 13:00','ends_at'=>'2026-09-28 13:30','status'=>'scheduled'];
  $this->actingAs($this->owner)->post(route('appointments.store'),$data)->assertSessionHasErrors('starts_at');
  $data['starts_at']='2026-09-28 10:00';$data['ends_at']='2026-09-28 10:30';
  ScheduleBlock::create(['company_id'=>$this->clinic->id,'starts_at'=>'2026-09-28 10:00','ends_at'=>'2026-09-28 10:30','reason'=>'Ausência']);
  $this->post(route('appointments.store'),$data)->assertSessionHasErrors('starts_at');ScheduleBlock::query()->delete();
  $this->post(route('appointments.store'),$data)->assertSessionHasNoErrors();$a=Appointment::sole();
  WorkingPeriod::query()->update(['start_time'=>'13:00','end_time'=>'17:00']);
  $this->put(route('appointments.update',$a->id),array_replace($data,['status'=>'confirmed']))->assertSessionHasNoErrors();
  $this->assertDatabaseHas('appointments',['id'=>$a->id,'status'=>'confirmed']);
 }
 public function test_missing_nonce_assignment_and_honeypot_do_not_create_appointments():void {
  $this->post($this->url(),$this->form())->assertStatus(419);
  $this->sessionNonce()->post($this->url(),$this->form()+['website'=>'spam'])->assertSessionHasErrors('website');
  DB::table('professional_services')->delete();
  $this->post($this->url(),$this->form())->assertSessionHasErrors('starts_at');$this->assertDatabaseCount('appointments',0);
 }
 public function test_clinic_block_applies_to_every_dentist_but_individual_block_does_not():void {
  $other=$this->user('professional');ScheduleBlock::create(['company_id'=>$this->clinic->id,'professional_id'=>$other->id,'starts_at'=>'2026-09-28 10:00','ends_at'=>'2026-09-28 11:00','reason'=>'Outro dentista']);
  $this->assertArrayHasKey('2026-09-28 10:00',$this->slots());
  ScheduleBlock::query()->update(['professional_id'=>null]);$this->assertArrayNotHasKey('2026-09-28 10:00',$this->slots());
 }
}
