<?php
namespace Tests\Feature;
use App\Models\{Company,Customer,User,Appointment,ClinicalRecord};
use App\Support\Odontogram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class OdontogramTest extends TestCase {
 use RefreshDatabase;
 private Company $clinic;private User $owner;private User $dentist;private Customer $patient;
 protected function setUp():void{parent::setUp();$this->clinic=Company::create(['name'=>'Clínica','slug'=>'clinica']);$this->owner=$this->user('owner');$this->dentist=$this->user('professional');$this->patient=Customer::create(['company_id'=>$this->clinic->id,'name'=>'Paciente odontograma']);$this->link($this->patient);}
 private function user(string $role,?Company $company=null):User{return User::create(['company_id'=>($company??$this->clinic)->id,'name'=>'Pessoa '.$role,'email'=>Str::uuid().'@example.test','password'=>'TestOnly-Password-2026','role'=>$role,'active'=>true,'must_change_password'=>false,'clinical_access'=>$role==='professional','professional_registration'=>'CRO-BA 12345']);}
 private function link(Customer $patient):void{Appointment::create(['company_id'=>$this->clinic->id,'customer_id'=>$patient->id,'professional_id'=>$this->dentist->id,'created_by'=>$this->owner->id,'client_name'=>$patient->name,'service'=>'Avaliação','starts_at'=>'2026-01-01 09:00','ends_at'=>'2026-01-01 09:30','status'=>'completed']);}
 private function url(?int $id=null):string{return route('odontogram.edit',$id??$this->patient->id);}
 private function form():array{return ['request_id'=>(string)Str::uuid(),'occurred_at'=>'2026-01-01T09:00','clinical'=>['dentition'=>'mixed','notes'=>'Observação privada do odontograma','teeth'=>Odontogram::blank()]];}
 public function test_fdi_numbering_and_initial_state_are_explicit():void {
  $q=Odontogram::quadrants();$this->assertSame([18,17,16,15,14,13,12,11],$q[0]['codes']);$this->assertSame([21,22,23,24,25,26,27,28],$q[1]['codes']);$this->assertSame([48,47,46,45,44,43,42,41],$q[2]['codes']);$this->assertSame([31,32,33,34,35,36,37,38],$q[3]['codes']);$this->assertSame([55,54,53,52,51],$q[4]['codes']);$this->assertSame([61,62,63,64,65],$q[5]['codes']);$this->assertSame([85,84,83,82,81],$q[6]['codes']);$this->assertSame([71,72,73,74,75],$q[7]['codes']);$this->assertCount(52,array_unique(Odontogram::codes()));$this->assertSame(0,Odontogram::counts(Odontogram::blank())['assessed']);
  $this->actingAs($this->dentist)->get($this->url())->assertOk()->assertSee('Dente 11: Não avaliado')->assertSee('Dente 85: Não avaliado');
 }
 public function test_save_keeps_author_and_encrypted_content_and_renders_history():void {
  $form=$this->form();$form['clinical']['teeth'][36]=['status'=>'caries','notes'=>'Observação clínica confidencial'];$form['author_id']=$this->owner->id;
  $this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$r=ClinicalRecord::sole();$this->assertSame($this->dentist->id,$r->author_id);$this->assertSame('CRO-BA 12345',$r->author_registration);$this->assertSame('caries',$r->payload['teeth'][36]['status']);$this->assertStringNotContainsString('confidencial',DB::table('clinical_records')->value('payload'));
  $this->get($this->url())->assertOk()->assertSee('Dente 36: Cárie');$this->get(route('clinical.show',$this->patient->id))->assertOk()->assertSee('Observação clínica confidencial');$this->get(route('odontogram.version',[$this->patient->id,$r->id]))->assertOk()->assertSee('Observação clínica confidencial')->assertHeader('Referrer-Policy','no-referrer');
 }
 public function test_switching_dentition_keeps_both_sets_of_teeth():void {
  $form=$this->form();$form['clinical']['teeth'][55]=['status'=>'restored','notes'=>'Nota decídua'];$form['clinical']['teeth'][11]['status']='sound';
  $this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$first=ClinicalRecord::sole();$form['request_id']=(string)Str::uuid();$form['base_id']=$first->id;$form['clinical']['dentition']='permanent';$this->post($this->url(),$form)->assertSessionHasNoErrors();$last=ClinicalRecord::latest('id')->first();$this->assertSame('restored',$last->payload['teeth'][55]['status']);$this->assertSame('sound',$last->payload['teeth'][11]['status']);$this->assertSame($first->id,$last->parent_id);$this->assertSame('mixed',$first->fresh()->payload['dentition']);
 }
 public function test_incomplete_and_invalid_numbering_are_rejected():void {
  $form=$this->form();unset($form['clinical']['teeth'][11]);$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasErrors('clinical.teeth');$form['clinical']['teeth'][99]=['status'=>'sound'];$this->post($this->url(),$form)->assertSessionHasErrors('clinical.teeth');$this->assertDatabaseCount('clinical_records',0);
 }
 public function test_invalid_condition_and_future_date_do_not_flash_clinical_data():void {
  $form=$this->form();$form['clinical']['teeth'][11]['status']='unexpected';$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasErrors('clinical.teeth.11.status');$this->assertNull(session('_old_input.clinical'));
  $form=$this->form();$form['occurred_at']='2099-01-01T10:00';$this->post($this->url(),$form)->assertSessionHasErrors('occurred_at');$this->assertDatabaseCount('clinical_records',0);
 }
 public function test_duplicate_submission_is_idempotent_and_stale_revision_is_rejected():void {
  $form=$this->form();$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$this->post($this->url(),$form)->assertSessionHasNoErrors();$this->assertDatabaseCount('clinical_records',1);$this->post($this->url(),$this->form())->assertSessionHasErrors('base_id');$this->assertDatabaseCount('clinical_records',1);
 }
 public function test_permissions_and_cross_clinic_isolation():void {
  foreach([$this->owner,$this->user('reception')] as $user){$this->flushSession();$this->actingAs($user)->get($this->url())->assertForbidden();$this->post($this->url(),$this->form())->assertForbidden();}
  $this->flushSession();$this->actingAs($this->user('professional'))->get($this->url())->assertNotFound();
  $other=Company::create(['name'=>'Outra','slug'=>'outra']);$this->flushSession();$this->actingAs($this->user('professional',$other))->post($this->url(),$this->form())->assertNotFound();
 }
 public function test_version_must_belong_to_patient_and_have_correct_record_kind():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$record=ClinicalRecord::sole();$p=Customer::create(['company_id'=>$this->clinic->id,'name'=>'Outro paciente']);$this->link($p);
  $this->get(route('odontogram.version',[$p->id,$record->id]))->assertNotFound();
  $other=ClinicalRecord::create(['company_id'=>$this->clinic->id,'customer_id'=>$this->patient->id,'author_id'=>$this->dentist->id,'author_name'=>$this->dentist->name,'author_registration'=>'CRO-BA 12345','kind'=>'evolution','payload'=>['content'=>'Evolução'],'occurred_at'=>'2026-01-01 10:00','request_key'=>hash('sha256','other')]);
  $this->get(route('odontogram.version',[$this->patient->id,$other->id]))->assertNotFound();
 }
 public function test_archived_patient_is_read_only_and_revocation_blocks_access():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->patient->update(['active'=>false]);$this->get($this->url())->assertOk()->assertDontSee('Salvar versão do odontograma');$this->post($this->url(),$this->form())->assertStatus(422);$this->dentist->update(['clinical_access'=>false]);$this->actingAs($this->dentist->fresh())->get($this->url())->assertForbidden();
 }
 public function test_owner_audit_does_not_expose_tooth_notes():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->flushSession();$this->actingAs($this->owner)->get(route('clinical.audit'))->assertOk()->assertSee('Odontograma registrado')->assertDontSee('Observação privada do odontograma');
 }
}
