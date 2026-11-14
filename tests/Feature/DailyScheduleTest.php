<?php
namespace Tests\Feature;
use App\Models\{Company,User,Appointment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Str,Carbon};
use Tests\TestCase;
class DailyScheduleTest extends TestCase {
 use RefreshDatabase;
 private Company $company;private User $owner;private User $dentist;
 protected function setUp():void{parent::setUp();$this->travelTo(Carbon::parse('2026-10-01 01:00:00','UTC'));$this->company=Company::create(['name'=>'Clínica','slug'=>'daily','booking_timezone'=>'America/Bahia']);$this->owner=$this->user('owner');$this->dentist=$this->user('professional');}
 private function user(string $role,?Company $c=null):User{return User::create(['company_id'=>($c??$this->company)->id,'name'=>'Pessoa '.$role,'email'=>Str::uuid().'@example.test','password'=>'Test-Password-2026','role'=>$role,'active'=>true]);}
 private function appointment(string $start='2026-09-30 10:00',string $status='scheduled',?User $dentist=null):Appointment{$dentist??=$this->dentist;return Appointment::create(['company_id'=>$dentist->company_id,'professional_id'=>$dentist->id,'created_by'=>$dentist->id,'client_name'=>'Paciente de teste','client_phone'=>'71912345678','client_email'=>'private@example.test','notes'=>'Observação interna confidencial','service'=>'Consulta','starts_at'=>$start,'ends_at'=>Carbon::parse($start)->addHour(),'status'=>$status]);}
 private function url(array $q=[]):string{return route('appointments.daily',$q);}
 public function test_local_date_default_boundaries_and_hidden_contacts():void{
  $this->appointment('2026-09-30 00:00');$this->appointment('2026-09-30 23:59');$this->appointment('2026-10-01 00:00');$this->appointment('2026-09-29 23:59');$this->appointment('2026-09-30 11:00','cancelled');
  $this->actingAs($this->owner)->get($this->url())->assertOk()->assertHeader('Cache-Control','no-store, private')->assertHeader('Referrer-Policy','no-referrer')->assertViewHas('date','2026-09-30')->assertViewHas('appointments',fn($a)=>$a->total()===2)->assertSee('Término em 01/10/2026')->assertDontSee('71912345678')->assertDontSee('private@example.test')->assertDontSee('Observação interna confidencial');
 }
 public function test_professional_and_tenant_limits():void{
  $this->appointment();$peer=$this->user('professional');$this->appointment('2026-09-30 12:00','confirmed',$peer);$other=Company::create(['name'=>'Outra','slug'=>'daily-other']);$external=$this->user('professional',$other);$this->appointment('2026-09-30 14:00','scheduled',$external);
  $this->get($this->url())->assertRedirect(route('login'));$this->actingAs($this->owner)->get($this->url())->assertOk()->assertViewHas('appointments',fn($a)=>$a->total()===2);
  $this->get($this->url(['professional_id'=>$external->id]))->assertNotFound();$this->flushSession();$this->actingAs($this->dentist)->get($this->url())->assertOk()->assertViewHas('appointments',fn($a)=>$a->total()===1)->assertViewHas('professional',$this->dentist->id);
  $this->get($this->url(['professional_id'=>$peer->id]))->assertNotFound();$this->flushSession();$this->actingAs($this->user('reception'))->get($this->url())->assertOk()->assertViewHas('appointments',fn($a)=>$a->total()===2);
 }
 public function test_status_filters_and_historical_inactive_professional():void{
  foreach(['scheduled','confirmed','completed','cancelled','no_show'] as $s)$this->appointment('2026-09-30 10:00',$s);$this->dentist->update(['active'=>false]);$this->actingAs($this->owner)->get($this->url(['status'=>'all','professional_id'=>$this->dentist->id]))->assertOk()->assertViewHas('appointments',fn($a)=>$a->total()===5)->assertSee('(inativo)');
  foreach(['scheduled','confirmed','completed','cancelled','no_show'] as $s)$this->get($this->url(['status'=>$s]))->assertOk()->assertViewHas('appointments',fn($a)=>$a->total()===1);
 }
 public function test_pagination_and_empty_state():void{
  for($i=0;$i<51;$i++)$this->appointment();$this->actingAs($this->owner)->get($this->url(['date'=>'2026-09-30']))->assertOk()->assertViewHas('appointments',fn($a)=>$a->total()===51&&$a->count()===50&&str_contains($a->nextPageUrl(),'date=2026-09-30'))->assertSee('Lote 1 de 2');
  $this->get($this->url(['page'=>2]))->assertOk()->assertViewHas('appointments',fn($a)=>$a->count()===1);$this->get($this->url(['page'=>3]))->assertNotFound();$this->get($this->url(['date'=>'2026-01-01']))->assertOk()->assertSee('Nenhum atendimento')->assertDontSee('<thead>',false);
 }
 public function test_validation_and_html_escaping():void{
  $a=$this->appointment();$a->update(['client_name'=>'<script>alert(1)</script>']);$this->actingAs($this->owner)->get($this->url())->assertOk()->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false);
  foreach([['date'=>'bad'],['status'=>'bad'],['page'=>0],['professional_id'=>'bad']] as $q)$this->get($this->url($q))->assertSessionHasErrors();
 }
}
