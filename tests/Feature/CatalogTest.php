<?php
namespace Tests\Feature;
use App\Models\{User,Company,Customer,Service,Appointment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class CatalogTest extends TestCase {
 use RefreshDatabase;
 private function user(string $role='owner',?Company $c=null):User {
  $c ??= Company::create(['name'=>'Empresa','slug'=>uniqid('empresa')]);
  return User::create(['company_id'=>$c->id,'name'=>'Pessoa','email'=>uniqid().'@example.test','password'=>'SenhaSegura123','role'=>$role,'active'=>true]);
 }
 private function data(User $p,array $extra=[]):array {return array_merge(['professional_id'=>$p->id,'client_name'=>'Manual','service'=>'Consulta manual','starts_at'=>'2026-10-10T10:00','ends_at'=>'2026-10-10T11:00','status'=>'scheduled'],$extra);}
 public function test_catalog_pages_and_reception_can_manage():void {
  $u=$this->user('reception');$this->actingAs($u);
  foreach(['/clientes','/clientes/novo','/servicos','/servicos/novo'] as $path)$this->get($path)->assertOk();
  $this->post('/clientes',['name'=>'Ana','phone'=>'11999999999','active'=>1,'company_id'=>999])->assertRedirect('/clientes');
  $c=Customer::firstOrFail();$this->assertSame($u->company_id,$c->company_id);
  $this->get('/clientes/'.$c->id.'/editar')->assertOk();$this->get('/clientes/'.$c->id.'/historico')->assertOk();
  $this->post('/servicos',['name'=>'Consulta','duration_minutes'=>45,'price'=>'150,90','active'=>1])->assertRedirect('/servicos');
  $s=Service::firstOrFail();$this->assertEquals(15090,$s->price_cents);$this->get('/servicos/'.$s->id.'/editar')->assertOk();
 }
 public function test_other_tenant_records_cannot_be_read_or_changed():void {
  $a=$this->user();$b=$this->user();$c=Customer::create(['company_id'=>$b->company_id,'name'=>'Segredo']);
  $s=Service::create(['company_id'=>$b->company_id,'name'=>'Outro','duration_minutes'=>30]);
  $this->actingAs($a)->get('/clientes')->assertDontSee('Segredo');
  $this->get('/clientes/'.$c->id.'/historico')->assertNotFound();
  $this->put('/clientes/'.$c->id,['name'=>'Alterado','active'=>1])->assertNotFound();
  $this->get('/servicos/'.$s->id.'/editar')->assertNotFound();
  $this->put('/servicos/'.$s->id,['name'=>'Alterado','active'=>1])->assertNotFound();
 }
 public function test_professional_cannot_access_catalogs():void {
  $p=$this->user('professional');$this->actingAs($p)->get('/clientes')->assertForbidden();
  $this->get('/servicos')->assertForbidden();$this->post('/clientes',['name'=>'Ana','active'=>1])->assertForbidden();
  $this->get('/agenda/novo')->assertOk()->assertDontSee('id="customer_id"',false);
 }
 public function test_catalog_selection_snapshots_and_history():void {
  $u=$this->user();$p=$this->user('professional',$u->company);
  $c=Customer::create(['company_id'=>$u->company_id,'name'=>'Ana Paciente','phone'=>'123']);
  $s=Service::create(['company_id'=>$u->company_id,'name'=>'Consulta clínica','duration_minutes'=>30,'price_cents'=>9900]);
  $data=$this->data($p,['customer_id'=>$c->id,'service_id'=>$s->id]);$this->actingAs($u)->post('/agenda',$data)->assertSessionHasNoErrors();
  $a=Appointment::firstOrFail();$this->assertEquals('Ana Paciente',$a->client_name);$this->assertEquals('Consulta clínica',$a->service);$this->assertEquals(9900,$a->price_cents);
  $this->get('/clientes/'.$c->id.'/historico')->assertSee('Consulta clínica');
  $c->update(['name'=>'Novo nome','active'=>false]);$s->update(['name'=>'Outro nome','price_cents'=>20000,'active'=>false]);
  $this->put('/agenda/'.$a->id,$data)->assertSessionHasNoErrors();
  $this->assertEquals('Ana Paciente',$a->fresh()->client_name);$this->assertEquals(9900,$a->fresh()->price_cents);
  $this->get('/agenda/'.$a->id.'/editar')->assertOk();
 }
 public function test_archived_and_foreign_catalogs_rejected():void {
  $u=$this->user();$p=$this->user('professional',$u->company);$other=$this->user();
  $c=Customer::create(['company_id'=>$u->company_id,'name'=>'Arquivado','active'=>false]);
  $this->actingAs($u)->post('/agenda',$this->data($p,['customer_id'=>$c->id]))->assertSessionHasErrors('customer_id');
  $foreign=Customer::create(['company_id'=>$other->company_id,'name'=>'Outro']);
  $this->post('/agenda',$this->data($p,['customer_id'=>$foreign->id]))->assertNotFound();
  $s=Service::create(['company_id'=>$other->company_id,'name'=>'Outro','duration_minutes'=>30]);
  $this->post('/agenda',$this->data($p,['service_id'=>$s->id]))->assertNotFound();$this->assertDatabaseCount('appointments',0);
 }
 public function test_service_validation_and_archive_filter():void {
  $u=$this->user();$this->actingAs($u)->post('/servicos',['name'=>'X','duration_minutes'=>0,'price'=>'-20','active'=>1])->assertSessionHasErrors(['duration_minutes','price']);
  $c=Customer::create(['company_id'=>$u->company_id,'name'=>'Arquivado','active'=>false]);
  $this->get('/clientes')->assertDontSee('Arquivado</td>',false);$this->get('/clientes?state=archived')->assertSee('Arquivado');
  $this->put('/clientes/'.$c->id,['name'=>'Reativado','active'=>1])->assertRedirect('/clientes');$this->assertTrue($c->fresh()->active);
 }
 public function test_manual_appointments_remain_supported():void {
  $u=$this->user();$p=$this->user('professional',$u->company);
  $this->actingAs($u)->post('/agenda',$this->data($p))->assertSessionHasNoErrors();
  $a=Appointment::firstOrFail();$this->assertNull($a->customer_id);$this->assertNull($a->service_id);
  $this->get('/agenda/'.$a->id.'/editar')->assertOk();
 }
 public function test_professional_cannot_inject_customer_link():void {
  $p=$this->user('professional');$c=Customer::create(['company_id'=>$p->company_id,'name'=>'Ana']);
  $this->actingAs($p)->post('/agenda',$this->data($p,['customer_id'=>$c->id]))->assertForbidden();$this->assertDatabaseCount('appointments',0);
 }
}
