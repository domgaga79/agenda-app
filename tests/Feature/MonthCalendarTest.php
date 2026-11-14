<?php
namespace Tests\Feature;
use App\Models\{Company,User,Appointment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class MonthCalendarTest extends TestCase {
 use RefreshDatabase;
 private function person(string $role='owner',?Company $company=null):User {
  $company ??=Company::create(['name'=>'Empresa','slug'=>uniqid('emp')]);
  return User::create(['company_id'=>$company->id,'name'=>'Pessoa','email'=>uniqid().'@example.test','password'=>'SenhaSegura123','role'=>$role,'active'=>true]);
 }
 private function add(User $p,string $name,string $start,string $end):void {
  Appointment::create(['company_id'=>$p->company_id,'created_by'=>$p->id,'professional_id'=>$p->id,'client_name'=>$name,'service'=>'Consulta','starts_at'=>$start,'ends_at'=>$end,'status'=>'confirmed']);
 }
 public function test_month_sizes_and_year_navigation():void {
  $this->actingAs($this->person());
  foreach(['2027-02-15'=>28,'2026-09-15'=>35,'2026-08-15'=>42] as $date=>$count){
   $this->get('/agenda?view=month&date='.$date)->assertOk()->assertViewHas('calendarDays',fn($days)=>count($days)===$count);
  }
  $this->get('/agenda?view=month&date=2026-12-31')->assertSee('date=2027-01-01',false)->assertSee('date=2026-11-01',false);
 }
 public function test_month_shows_more_link_and_keeps_tenant_isolation():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$other=$this->person('professional');
  for($i=0;$i<5;$i++)$this->add($p,'Paciente'.$i,'2026-09-15 '.(9+$i).':00','2026-09-15 '.(10+$i).':00');
  $this->add($other,'OutraEmpresa','2026-09-15 10:00','2026-09-15 11:00');
  $this->actingAs($u)->get('/agenda?view=month&date=2026-09-15')->assertOk()->assertSee('Paciente0')->assertSee('+ 2 atendimento(s)')->assertDontSee('OutraEmpresa');
  $this->get('/agenda?view=day&date=2026-09-15')->assertSee('Paciente4');
 }
 public function test_month_professional_filter_and_overnight():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$other=$this->person('professional',$u->company);
  $this->add($p,'Noturno','2026-09-15 23:30','2026-09-16 00:30');
  $this->add($other,'OutroProfissional','2026-09-15 10:00','2026-09-15 11:00');
  $this->actingAs($p)->get('/agenda?view=month&date=2026-09-15')->assertOk()->assertSee('Noturno')->assertDontSee('OutroProfissional')->assertViewHas('calendarDays',function($days){
   $count=0;foreach($days as $day)foreach($day['events'] as $event)if($event['appointment']->client_name==='Noturno')$count++;return $count===2;
  });
  $this->flushSession();$this->actingAs($u)->get('/agenda?view=month&date=2026-09-15&professional='.$p->id)->assertSee('Noturno')->assertDontSee('OutroProfissional');
 }
}
