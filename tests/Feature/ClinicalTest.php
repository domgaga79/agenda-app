<?php
namespace Tests\Feature;
use App\Models\{Company,Customer,User,Appointment,ClinicalRecord,ClinicalAudit};
use App\Http\Controllers\ClinicalController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class ClinicalTest extends TestCase {
 use RefreshDatabase;
 private Company $clinic;private User $owner;private User $dentist;private Customer $patient;
 protected function setUp():void {parent::setUp();$this->clinic=Company::create(['name'=>'Clínica','slug'=>'clinica']);$this->owner=$this->user('owner');$this->dentist=$this->user('professional');$this->patient=Customer::create(['company_id'=>$this->clinic->id,'name'=>'Paciente restrito','birth_date'=>'1990-05-10']);$this->link($this->dentist,$this->patient);}
 private function user(string $role,?Company $c=null):User{return User::create(['company_id'=>($c??$this->clinic)->id,'name'=>'Pessoa '.$role,'email'=>Str::uuid().'@example.test','password'=>'Test-Password-2026','role'=>$role,'active'=>true,'must_change_password'=>false,'clinical_access'=>$role==='professional','professional_registration'=>'CRO-BA 12345']);}
 private function link(User $u,Customer $p):void{Appointment::create(['company_id'=>$p->company_id,'professional_id'=>$u->id,'customer_id'=>$p->id,'created_by'=>$u->id,'client_name'=>$p->name,'service'=>'Consulta','starts_at'=>'2026-01-01 10:00','ends_at'=>'2026-01-01 11:00','status'=>'completed']);}
 private function form(string $kind='evolution'):array {$payload=['content'=>'Evolução sigilosa registrada pelo dentista.'];if($kind==='anamnesis'){$payload=['chief_complaint'=>'Queixa confidencial','informant'=>'Paciente'];foreach(ClinicalController::QUESTIONS as $key=>$label)$payload[$key]=['status'=>'unknown','detail'=>null];$payload['allergies']=['status'=>'yes','detail'=>'Alergia confidencial'];}return ['kind'=>$kind,'request_id'=>(string)Str::uuid(),'occurred_at'=>'2026-01-01T10:30','clinical'=>$payload];}
 private function url(?int $id=null):string{return route('clinical.show',$id??$this->patient->id);}
 public function test_authorized_dentist_can_read_write_and_payload_is_encrypted():void {
  $this->actingAs($this->dentist)->get(route('clinical.index'))->assertOk()->assertSee($this->patient->name);
  $this->post($this->url(),$this->form())->assertSessionHasNoErrors()->assertRedirect($this->url());$r=ClinicalRecord::sole();
  $this->assertStringNotContainsString('Evolução sigilosa',DB::table('clinical_records')->value('payload'));$this->assertEquals($this->dentist->id,$r->author_id);$this->assertSame('CRO-BA 12345',$r->author_registration);
  $this->get($this->url())->assertOk()->assertSee('Evolução sigilosa')->assertHeader('Referrer-Policy','no-referrer');
  $this->assertDatabaseHas('clinical_audits',['event'=>'create_evolution','actor_id'=>$this->dentist->id,'customer_id'=>$this->patient->id]);$this->assertDatabaseHas('clinical_audits',['event'=>'view']);
 }
 public function test_reception_owner_and_unapproved_dentist_cannot_read_or_write_clinical_data():void {
  foreach([$this->owner,$this->user('reception')] as $u){$this->flushSession();$this->actingAs($u)->get($this->url())->assertForbidden();$this->post($this->url(),$this->form())->assertForbidden();}
  $this->dentist->update(['clinical_access'=>false]);$this->flushSession();$this->actingAs($this->dentist)->get(route('clinical.index'))->assertForbidden();$this->assertDatabaseCount('clinical_records',0);
 }
 public function test_other_dentist_and_other_clinic_cannot_access_patient():void {
  $other=$this->user('professional');$this->actingAs($other)->get($this->url())->assertNotFound();$this->post($this->url(),$this->form())->assertNotFound();$this->get(route('clinical.index'))->assertDontSee('Paciente restrito');
  $company=Company::create(['name'=>'Outra','slug'=>'outra']);$u=$this->user('professional',$company);$this->flushSession();$this->actingAs($u)->get($this->url())->assertNotFound();$this->post($this->url(),$this->form())->assertNotFound();
 }
 public function test_anamnesis_versions_preserve_history_and_reject_stale_forms():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form('anamnesis'))->assertSessionHasNoErrors();$first=ClinicalRecord::sole();
  $this->get($this->url())->assertOk()->assertSee('Alergia confidencial');
  $this->post($this->url(),$this->form('anamnesis'))->assertSessionHasErrors('base_id');
  $new=$this->form('anamnesis');$new['base_id']=$first->id;$new['clinical']['allergies']=['status'=>'no','detail'=>'Nova informação relatada'];
  $this->post($this->url(),$new)->assertSessionHasNoErrors();$this->assertDatabaseCount('clinical_records',2);$this->assertSame('Alergia confidencial',$first->fresh()->payload['allergies']['detail']);$this->assertEquals($first->id,ClinicalRecord::latest('id')->first()->parent_id);
 }
 public function test_repeated_submission_does_not_duplicate_and_forged_author_is_ignored():void {
  $data=$this->form()+['author_id'=>$this->owner->id,'company_id'=>999,'customer_id'=>999,'author_registration'=>'FAKE'];
  $this->actingAs($this->dentist)->post($this->url(),$data)->assertSessionHasNoErrors();$this->post($this->url(),$data)->assertSessionHasNoErrors();$this->assertDatabaseCount('clinical_records',1);$this->assertDatabaseCount('clinical_audits',1);$this->assertEquals($this->dentist->id,ClinicalRecord::sole()->author_id);
 }
 public function test_addendum_keeps_original_and_parent_must_belong_to_same_patient():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$original=ClinicalRecord::sole();
  $form=$this->form('addendum');$form['parent_id']=$original->id;$form['clinical']['content']='Complemento do registro anterior.';$this->post($this->url(),$form)->assertSessionHasNoErrors();$this->assertDatabaseCount('clinical_records',2);$this->assertSame('Evolução sigilosa registrada pelo dentista.',$original->fresh()->payload['content']);
  $p=Customer::create(['company_id'=>$this->clinic->id,'name'=>'Outro paciente']);$this->link($this->dentist,$p);$form['request_id']=(string)Str::uuid();$this->post($this->url($p->id),$form)->assertNotFound();
 }
 public function test_clinical_payload_is_not_flashed_to_session_on_validation_errors():void {
  $data=$this->form();$data['occurred_at']='2099-01-01T10:00';$this->actingAs($this->dentist)->post($this->url(),$data)->assertSessionHasErrors('occurred_at');$this->assertNull(session('_old_input.clinical'));$this->assertDatabaseCount('clinical_records',0);
 }
 public function test_archived_patient_is_read_only_and_cancelled_only_link_is_not_authorized():void {
  $this->patient->update(['active'=>false]);$this->actingAs($this->dentist)->get($this->url())->assertOk()->assertDontSee('Salvar evolução clínica');$this->post($this->url(),$this->form())->assertStatus(422);
  Appointment::query()->update(['status'=>'cancelled']);$this->get($this->url())->assertNotFound();
 }
 public function test_owner_audit_shows_metadata_without_clinical_content():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->get(route('clinical.audit'))->assertForbidden();
  $this->flushSession();$this->actingAs($this->owner)->get(route('clinical.audit'))->assertOk()->assertSee('Evolução registrada')->assertDontSee('Evolução sigilosa');
 }
 public function test_demographic_fields_are_saved_by_reception_without_clinical_access():void {
  $this->actingAs($this->owner)->put(route('customers.update',$this->patient->id),['name'=>'Paciente cadastrado','active'=>1,'birth_date'=>'2000-02-20','guardian_name'=>'Responsável','guardian_relationship'=>'Mãe','guardian_phone'=>'71999999999','address'=>'Rua exemplo','emergency_contact'=>'Familiar','emergency_phone'=>'71888888888'])->assertSessionHasNoErrors();$this->assertSame('Responsável',$this->patient->fresh()->guardian_name);$this->get(route('customers.edit',$this->patient->id))->assertOk()->assertSee('Responsável')->assertDontSee('Evolução sigilosa');
 }
 public function test_team_permission_requires_registration_and_revocation_blocks_next_request():void {
  $data=['name'=>$this->dentist->name,'email'=>$this->dentist->email,'role'=>'professional','active'=>1,'clinical_access'=>1,'professional_registration'=>''];
  $this->actingAs($this->owner)->put(route('team.update',$this->dentist->id),$data)->assertSessionHasErrors('professional_registration');
  $data['clinical_access']=0;$this->put(route('team.update',$this->dentist->id),$data)->assertSessionHasNoErrors();$this->assertDatabaseHas('clinical_audits',['event'=>'revoke_access','target_user_id'=>$this->dentist->id]);
  $this->flushSession();$this->actingAs($this->dentist->fresh())->get($this->url())->assertForbidden();
 }
 public function test_clinical_record_is_append_only_in_application():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->expectException(\LogicException::class);ClinicalRecord::sole()->update(['payload'=>['content'=>'Alterado']]);
 }
}
