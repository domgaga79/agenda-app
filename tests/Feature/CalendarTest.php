<?php
namespace Tests\Feature;
use App\Models\{Company,User,Appointment};
use App\Support\CalendarLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
class CalendarTest extends TestCase {
 use RefreshDatabase;
 private function person(string $role='owner',?Company $c=null):User {
  $c ??= Company::create(['name'=>'Empresa','slug'=>uniqid('emp')]);
  return User::create(['company_id'=>$c->id,'name'=>'Pessoa','email'=>uniqid().'@example.test','password'=>'SenhaSegura123','role'=>$role,'active'=>true]);
 }
 private function appointment(User $p,string $start,string $end,string $name):Appointment {
  return Appointment::create(['company_id'=>$p->company_id,'professional_id'=>$p->id,'created_by'=>$p->id,'client_name'=>$name,'service'=>'Consulta','starts_at'=>$start,'ends_at'=>$end,'status'=>'scheduled']);
 }
 public function test_week_includes_overnight_events_and_excludes_other_tenants():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$other=$this->person('professional');
  $this->appointment($p,'2026-10-04 23:30','2026-10-05 00:30','Virada');
  $this->appointment($p,'2026-10-11 14:00','2026-10-11 15:00','Domingo');
  $this->appointment($p,'2026-10-12 14:00','2026-10-12 15:00','ForaPeriodo');
  $this->appointment($other,'2026-10-06 14:00','2026-10-06 15:00','OutraEmpresa');
  $this->actingAs($u)->get('/agenda?date=2026-10-07&view=week')->assertOk()->assertSee('Virada')->assertSee('Domingo')->assertDontSee('ForaPeriodo')->assertDontSee('OutraEmpresa');
  $this->get('/agenda?date=2026-10-07&view=day')->assertOk()->assertDontSee('Domingo');
 }
 public function test_filter_and_professional_scope():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$p2=$this->person('professional',$u->company);
  $this->appointment($p,'2026-10-07 10:00','2026-10-07 11:00','Visivel');
  $this->appointment($p2,'2026-10-07 10:00','2026-10-07 11:00','Restrito');
  $this->actingAs($u)->get('/agenda?date=2026-10-07&professional='.$p->id)->assertSee('Visivel')->assertDontSee('Restrito');
  $this->flushSession();$this->actingAs($p)->get('/agenda?date=2026-10-07')->assertSee('Visivel')->assertDontSee('Restrito');
  $this->get('/agenda?professional='.$p2->id)->assertNotFound();
 }
 public function test_overlap_placement_and_day_clipping():void {
  $p=$this->person('professional');$a=$this->appointment($p,'2026-10-07 23:00','2026-10-08 01:00','A');
  $b=$this->appointment($p,'2026-10-07 23:30','2026-10-08 00:30','B');
  $layout=CalendarLayout::build(collect([$a,$b]),Carbon::parse('2026-10-07'),2);
  $this->assertEquals(24,$layout['lastHour']);$this->assertEquals(0,$layout['firstHour']);
  $this->assertEquals(2,$layout['calendarDays'][0]['events'][0]['lanes']);
  $this->assertEquals(1440,$layout['calendarDays'][0]['events'][0]['to']);
  $this->assertEquals(0,$layout['calendarDays'][1]['events'][0]['from']);
 }
 public function test_slot_prefill_and_validation():void {
  $u=$this->person();$this->actingAs($u)->get('/agenda/novo?date=2026-10-07&time=10:30')->assertOk()->assertSee('2026-10-07T10:30',false)->assertSee('2026-10-07T11:00',false);
  $this->get('/agenda?view=invalid')->assertSessionHasErrors('view');
  $this->get('/agenda/novo?date=invalid')->assertSessionHasErrors('date');
 }
 public function test_empty_catalogs_have_no_table_headers():void {
  $u=$this->person();$this->actingAs($u)->get('/clientes')->assertOk()->assertDontSee('<thead>',false)->assertSee('Seu primeiro cliente');
  $this->get('/servicos')->assertOk()->assertDontSee('<thead>',false)->assertSee('Organize seus serviços');
 }
}
