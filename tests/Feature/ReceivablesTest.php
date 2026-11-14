<?php
namespace Tests\Feature;
use App\Models\{Company,User,Customer,FinancialQuote as Quote,FinancialInstallment as Installment,FinancialPayment as Payment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Str,Carbon};
use Tests\TestCase;
class ReceivablesTest extends TestCase {
 use RefreshDatabase;
 private Company $company;private User $owner;private Customer $patient;
 protected function setUp():void {parent::setUp();$this->travelTo(Carbon::parse('2026-10-01 01:00:00','UTC'));$this->company=Company::create(['name'=>'Clínica','slug'=>'receivables','booking_timezone'=>'America/Bahia']);$this->owner=$this->user('owner');$this->patient=Customer::create(['company_id'=>$this->company->id,'name'=>'Paciente teste','active'=>true]);}
 private function user(string $role,?Company $c=null):User{return User::create(['company_id'=>($c??$this->company)->id,'name'=>'Usuário','email'=>Str::uuid().'@example.test','password'=>'Test-Password-2026','role'=>$role,'active'=>true]);}
 private function installment(string $due='2026-09-30',int $amount=10000,string $status='approved',string $name='Paciente teste'):Installment {$q=Quote::create(['company_id'=>$this->company->id,'customer_id'=>$this->patient->id,'created_by'=>$this->owner->id,'customer_name'=>$name,'description'=>'Descrição privada','gross_cents'=>$amount,'total_cents'=>$amount,'status'=>$status,'request_key'=>hash('sha256',Str::uuid())]);return Installment::create(['company_id'=>$this->company->id,'quote_id'=>$q->id,'sequence'=>1,'is_deposit'=>false,'due_on'=>$due,'amount_cents'=>$amount]);}
 private function pay(Installment $i,int $amount,bool $reversed=false):Payment{return Payment::create(['company_id'=>$this->company->id,'installment_id'=>$i->id,'created_by'=>$this->owner->id,'amount_cents'=>$amount,'paid_on'=>'2026-09-01','method'=>'pix','request_key'=>hash('sha256',Str::uuid()),'reversed_at'=>$reversed?now():null]);}
 private function url(array $q=[]):string{return route('finance.receivables',$q);}
 public function test_balances_exclude_paid_draft_cancelled_and_reversed_payments():void {
  $a=$this->installment('2026-09-29');$this->pay($a,2000);$this->pay($a,3000,true);$this->installment();$this->installment('2026-10-01');$paid=$this->installment();$this->pay($paid,10000);$this->installment('2026-09-01',10000,'draft');$this->installment('2026-09-01',10000,'cancelled');
  $this->actingAs($this->owner)->get($this->url())->assertOk()->assertHeader('Cache-Control','no-store, private')->assertHeader('Referrer-Policy','no-referrer')->assertViewHas('today','2026-09-30')->assertViewHas('stats',fn($s)=>(int)$s->count===3&&(int)$s->balance===28000&&(int)$s->overdue===8000&&(int)$s->due_today===10000)->assertDontSee('Descrição privada');
 }
 public function test_status_partial_dates_and_literal_name_filters():void {
  $a=$this->installment('2026-09-29',10000,'approved','Maria 100%_!');$this->pay($a,2000);$this->installment();$this->installment('2026-10-01');$this->actingAs($this->owner);
  foreach(['overdue'=>8000,'today'=>10000,'future'=>10000] as $status=>$value)$this->get($this->url(['status'=>$status]))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->count===1&&(int)$s->balance===$value);
  $this->get($this->url(['partial'=>1]))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->balance===8000);
  $this->get($this->url(['q'=>'%_!']))->assertOk()->assertSee('Maria 100%_!')->assertViewHas('stats',fn($s)=>(int)$s->count===1);
  $this->get($this->url(['from'=>'2026-09-30','to'=>'2026-09-30']))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->count===1);
  $this->get($this->url(['to'=>'2026-09-29']))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->balance===8000);
  $this->get($this->url(['from'=>'2026-10-01']))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->balance===10000);
 }
 public function test_access_and_company_isolation():void {
  $this->installment('2026-09-30',10000,'approved','Paciente exclusivo');$this->get($this->url())->assertRedirect(route('login'));
  $this->actingAs($this->user('professional'))->get($this->url())->assertForbidden();$this->flushSession();$this->actingAs($this->user('reception'))->get($this->url())->assertOk()->assertSee('Paciente exclusivo');
  $other=Company::create(['name'=>'Outra','slug'=>'other-receivables']);$this->flushSession();$this->actingAs($this->user('owner',$other))->get($this->url())->assertOk()->assertDontSee('Paciente exclusivo')->assertViewHas('stats',fn($s)=>(int)$s->count===0);
 }
 public function test_summary_covers_all_pages_and_empty_table_has_no_header():void {
  for($i=0;$i<26;$i++)$this->installment();$this->actingAs($this->owner)->get($this->url())->assertOk()->assertViewHas('installments',fn($r)=>$r->count()===25&&$r->total()===26)->assertViewHas('stats',fn($s)=>(int)$s->balance===260000);
  $this->get($this->url(['page'=>2]))->assertOk()->assertViewHas('installments',fn($r)=>$r->count()===1)->assertViewHas('stats',fn($s)=>(int)$s->balance===260000);
  $this->get($this->url(['q'=>'inexistente']))->assertOk()->assertSee('Nenhuma parcela pendente')->assertDontSee('<thead>',false);
 }
 public function test_validates_filters_and_archived_patients_remain_in_ledger():void {
  $this->installment();$this->patient->update(['active'=>false]);$this->actingAs($this->owner)->get($this->url())->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->count===1);
  foreach([['status'=>'bad'],['partial'=>'bad'],['from'=>'invalid'],['from'=>'2026-10-01','to'=>'2026-09-01'],['q'=>str_repeat('x',101)]] as $filter)$this->get($this->url($filter))->assertSessionHasErrors();
 }
}
