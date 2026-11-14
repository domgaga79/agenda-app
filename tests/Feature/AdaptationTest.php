<?php
namespace Tests\Feature;
use App\Models\{User,Company,Appointment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
class AdaptationTest extends TestCase {
 use RefreshDatabase;
 private function person(string $role='owner',?Company $company=null):User {
  $c=$company ?? Company::create(['name'=>'Clínica Teste','slug'=>uniqid('empresa')]);
  return User::create(['company_id'=>$c->id,'name'=>'Pessoa Teste','email'=>uniqid().'@teste.com','role'=>$role,'password'=>'SenhaSegura123','active'=>true,'must_change_password'=>false]);
 }
 private function data(User $pro,array $extra=[]):array {return array_merge(['professional_id'=>$pro->id,'client_name'=>'Paciente Exemplo','service'=>'Consulta','starts_at'=>'2026-10-01T10:00','ends_at'=>'2026-10-01T11:00','status'=>'scheduled'],$extra);}
 public function test_all_owner_pages_render():void {
  $u=$this->person();$this->get('/login')->assertOk();
  foreach(['/dashboard','/empresa','/equipe','/equipe/novo','/agenda','/agenda/novo','/alterar-senha'] as $url)$this->actingAs($u)->get($url)->assertOk();
 }
 public function test_login_logout_and_rate_limit():void {
  $u=$this->person();
  for($i=0;$i<5;$i++)$this->post('/login',['email'=>$u->email,'password'=>'errada'])->assertSessionHasErrors('email');
  $this->post('/login',['email'=>$u->email,'password'=>'SenhaSegura123'])->assertSessionHasErrors('email');$this->assertGuest();
 }
 public function test_valid_login():void {
  $u=$this->person();$this->post('/login',['email'=>strtoupper($u->email),'password'=>'SenhaSegura123'])->assertRedirect('/dashboard');$this->assertAuthenticatedAs($u);
  $this->post('/logout')->assertRedirect('/login');$this->assertGuest();
 }
 public function test_adjacent_slots_and_cancelled_slots_are_available():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$this->actingAs($u);
  $this->post('/agenda',$this->data($p))->assertSessionHasNoErrors();
  $this->post('/agenda',$this->data($p,['starts_at'=>'2026-10-01T11:00','ends_at'=>'2026-10-01T12:00']))->assertSessionHasNoErrors();
  $this->post('/agenda',$this->data($p,['status'=>'cancelled']))->assertSessionHasNoErrors();
  $this->assertDatabaseCount('appointments',3);
 }
 public function test_update_conflict_is_rejected_but_unchanged_slot_allowed():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$this->actingAs($u);
  $this->post('/agenda',$this->data($p));$a=Appointment::firstOrFail();
  $this->put('/agenda/'.$a->id,$this->data($p,['status'=>'confirmed']))->assertSessionHasNoErrors();
  $this->post('/agenda',$this->data($p,['starts_at'=>'2026-10-01T12:00','ends_at'=>'2026-10-01T13:00']));
  $this->put('/agenda/'.$a->id,$this->data($p,['starts_at'=>'2026-10-01T12:30','ends_at'=>'2026-10-01T13:30']))->assertSessionHasErrors('starts_at');
 }
 public function test_cross_company_create_update_delete_blocked():void {
  $u=$this->person();$p=$this->person('professional');
  $a=Appointment::create($this->data($p)+['company_id'=>$p->company_id,'created_by'=>$p->id]);
  $this->actingAs($u)->post('/agenda',$this->data($p))->assertNotFound();
  $this->put('/agenda/'.$a->id,$this->data($p))->assertNotFound();$this->delete('/agenda/'.$a->id)->assertNotFound();
  $this->assertDatabaseHas('appointments',['id'=>$a->id]);
 }
 public function test_tenant_injection_ignored_and_professional_sees_own_schedule():void {
  $u=$this->person();$p=$this->person('professional',$u->company);$other=$this->person('professional',$u->company);
  $this->actingAs($p)->post('/agenda',$this->data($p,['company_id'=>999,'created_by'=>999]))->assertSessionHasNoErrors();
  $this->assertDatabaseHas('appointments',['company_id'=>$u->company_id,'created_by'=>$p->id]);
  $this->post('/agenda',$this->data($other))->assertForbidden();
 }
 public function test_invalid_date_is_validation_error():void {
  $u=$this->person();$this->actingAs($u)->get('/agenda?date=nao-e-data')->assertSessionHasErrors('date');
 }
 public function test_password_requires_current_and_changes_hash():void {
  $u=$this->person();$this->actingAs($u)->put('/alterar-senha',['password'=>'OutraSenha123','password_confirmation'=>'OutraSenha123'])->assertSessionHasErrors('current_password');
  $this->put('/alterar-senha',['current_password'=>'SenhaSegura123','password'=>'OutraSenha123','password_confirmation'=>'OutraSenha123'])->assertRedirect('/dashboard');
  $this->assertTrue(Hash::check('OutraSenha123',$u->fresh()->password));
 }
 public function test_company_disabled_blocks_login():void {
  $u=$this->person();$u->company->update(['active'=>false]);
  $this->post('/login',['email'=>$u->email,'password'=>'SenhaSegura123'])->assertSessionHasErrors('email');$this->assertGuest();
 }
 public function test_owner_cannot_edit_another_owner():void {
  $u=$this->person();$other=$this->person('owner',$u->company);
  $this->actingAs($u)->get('/equipe/'.$other->id.'/editar')->assertForbidden();
  $this->put('/equipe/'.$other->id,[])->assertForbidden();
 }
 public function test_team_creation_requires_strong_password_and_is_scoped():void {
  $u=$this->person();$data=['name'=>'Dentista','email'=>'dentista@teste.com','role'=>'professional','password'=>'Curta1','company_id'=>999];
  $this->actingAs($u)->post('/equipe',$data)->assertSessionHasErrors('password');
  $data['password']='Provisoria12345';$this->post('/equipe',$data)->assertRedirect('/equipe');
  $this->assertDatabaseHas('users',['email'=>$data['email'],'company_id'=>$u->company_id,'must_change_password'=>1]);
 }
}
