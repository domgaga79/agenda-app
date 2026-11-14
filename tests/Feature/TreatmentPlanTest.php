<?php
namespace Tests\Feature;
use App\Models\{Company,Customer,User,Appointment,ClinicalRecord,Service};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class TreatmentPlanTest extends TestCase {
 use RefreshDatabase;
 private Company $clinic;private User $dentist;private User $owner;private Customer $patient;private Service $service;
 protected function setUp():void{parent::setUp();$this->clinic=Company::create(['name'=>'Clínica','slug'=>'clinica']);$this->owner=$this->user('owner');$this->dentist=$this->user('professional');$this->patient=Customer::create(['company_id'=>$this->clinic->id,'name'=>'Paciente do plano']);$this->service=Service::create(['company_id'=>$this->clinic->id,'name'=>'Procedimento de exemplo','duration_minutes'=>30,'price_cents'=>15050]);Appointment::create(['company_id'=>$this->clinic->id,'professional_id'=>$this->dentist->id,'customer_id'=>$this->patient->id,'created_by'=>$this->owner->id,'client_name'=>'Paciente do plano','service'=>'Avaliação','starts_at'=>'2026-01-01 10:00','ends_at'=>'2026-01-01 10:30','status'=>'completed']);}
 private function user(string $role,?Company $company=null):User{return User::create(['company_id'=>($company??$this->clinic)->id,'name'=>'Usuário '.$role,'email'=>Str::uuid().'@example.test','password'=>'TestOnly-Password-2026','role'=>$role,'active'=>true,'must_change_password'=>false,'clinical_access'=>$role==='professional','professional_registration'=>'CRO-BA 12345']);}
 private function url():string{return route('treatment.edit',$this->patient->id);}
 private function item(array $changes=[]):array{return array_replace(['id'=>(string)Str::uuid(),'service_id'=>$this->service->id,'description'=>'Procedimento sigiloso','region'=>'36','quantity'=>2,'unit_price'=>'150,50','status'=>'planned','notes'=>'Observação clínica privada'],$changes);}
 private function form(array $items=[]):array{return ['request_id'=>(string)Str::uuid(),'clinical'=>['title'=>'Plano individual','status'=>'draft','notes'=>'Plano confidencial','items'=>$items?:[$this->item()]]];}
 public function test_totals_are_calculated_on_server_and_cancelled_and_unpriced_items_are_distinguished():void {
  $form=$this->form([$this->item(),$this->item(['unit_price'=>'10,01','quantity'=>3,'status'=>'completed']),$this->item(['unit_price'=>null]),$this->item(['unit_price'=>'999,99','status'=>'cancelled'])]);$form['clinical']['totals']=['total_cents'=>1];$form['author_id']=$this->owner->id;
  $this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$record=ClinicalRecord::sole();$totals=$record->payload['totals'];$this->assertSame(33103,$totals['total_cents']);$this->assertSame(3003,$totals['completed_cents']);$this->assertSame(1,$totals['unpriced_items']);$this->assertSame(3,$totals['active_items']);$this->assertSame($this->dentist->id,$record->author_id);$this->assertStringNotContainsString('Plano confidencial',DB::table('clinical_records')->value('payload'));
  $this->get($this->url())->assertOk()->assertSee('331,03')->assertSee('A combinar')->assertSee('Fora do total');
 }
 public function test_versions_are_preserved_and_stale_updates_are_rejected():void {
  $form=$this->form();$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$first=ClinicalRecord::sole();
  $stale=$this->form();$this->post($this->url(),$stale)->assertSessionHasErrors('base_id');
  $form['request_id']=(string)Str::uuid();$form['base_id']=$first->id;$form['clinical']['items'][0]['unit_price']='200,00';$this->post($this->url(),$form)->assertSessionHasNoErrors();$this->assertDatabaseCount('clinical_records',2);$this->assertSame(15050,$first->fresh()->payload['items'][0]['unit_price_cents']);$this->assertSame($first->id,ClinicalRecord::latest('id')->first()->parent_id);
 }
 public function test_repeated_submit_is_idempotent():void {$form=$this->form();$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$this->post($this->url(),$form)->assertSessionHasNoErrors();$this->assertDatabaseCount('clinical_records',1);$this->assertDatabaseCount('clinical_audits',1);}
 public function test_admin_reception_and_unlinked_dentist_do_not_access_plans():void {
  foreach([$this->owner,$this->user('reception')] as $user){$this->flushSession();$this->actingAs($user)->get($this->url())->assertForbidden();$this->post($this->url(),$this->form())->assertForbidden();}
  $this->flushSession();$this->actingAs($this->user('professional'))->get($this->url())->assertNotFound();
 }
 public function test_cross_clinic_patient_and_catalog_references_are_rejected():void {
  $other=Company::create(['name'=>'Outra','slug'=>'outra']);$user=$this->user('professional',$other);$this->actingAs($user)->post($this->url(),$this->form())->assertNotFound();
  $service=Service::create(['company_id'=>$other->id,'name'=>'Catálogo privado','duration_minutes'=>30]);$this->flushSession();$this->actingAs($this->dentist)->post($this->url(),$this->form([$this->item(['service_id'=>$service->id])]))->assertNotFound();$this->assertDatabaseCount('clinical_records',0);
 }
 public function test_archived_service_can_stay_only_in_existing_item_and_price_snapshot_is_preserved():void {
  $form=$this->form();$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasNoErrors();$first=ClinicalRecord::sole();$this->service->update(['price_cents'=>99999,'active'=>false]);$this->get($this->url())->assertOk()->assertSee('150,50');
  $form['request_id']=(string)Str::uuid();$form['base_id']=$first->id;$this->post($this->url(),$form)->assertSessionHasNoErrors();$latest=ClinicalRecord::latest('id')->first();
  $form['request_id']=(string)Str::uuid();$form['base_id']=$latest->id;$form['clinical']['items'][0]['id']=(string)Str::uuid();$this->post($this->url(),$form)->assertSessionHasErrors('clinical.items.0.service_id');$this->assertDatabaseCount('clinical_records',2);
 }
 public function test_completed_plan_requires_all_active_items_completed():void {
  $form=$this->form();$form['clinical']['status']='completed';$this->actingAs($this->dentist)->post($this->url(),$form)->assertSessionHasErrors('clinical.status');
  $form['clinical']['items'][0]['status']='completed';$this->post($this->url(),$form)->assertSessionHasNoErrors();$this->assertSame('completed',ClinicalRecord::sole()->payload['status']);
 }
 public function test_invalid_price_quantity_and_duplicate_item_ids_are_rejected_without_flashing_clinical_data():void {
  $item=$this->item(['unit_price'=>'-1,00','quantity'=>0]);$this->actingAs($this->dentist)->post($this->url(),$this->form([$item,$item]))->assertSessionHasErrors(['clinical.items.0.unit_price','clinical.items.0.quantity','clinical.items.0.id']);$this->assertNull(session('_old_input.clinical'));$this->assertDatabaseCount('clinical_records',0);
 }
 public function test_history_and_audit_render_plan_without_exposing_clinical_content_to_owner():void {
  $this->actingAs($this->dentist)->post($this->url(),$this->form())->assertSessionHasNoErrors();$this->get(route('clinical.show',$this->patient->id))->assertOk()->assertSee('Procedimento sigiloso');$this->get($this->url())->assertOk()->assertHeader('Referrer-Policy','no-referrer');
  $this->flushSession();$this->actingAs($this->owner)->get(route('clinical.audit'))->assertOk()->assertSee('Plano de tratamento registrado')->assertDontSee('Procedimento sigiloso');
 }
 public function test_archived_patient_is_read_only():void {$this->patient->update(['active'=>false]);$this->actingAs($this->dentist)->get($this->url())->assertOk()->assertDontSee('Salvar versão do plano');$this->post($this->url(),$this->form())->assertStatus(422);}
}
