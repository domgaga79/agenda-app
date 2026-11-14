<?php
namespace Tests\Feature;
use App\Models\{Company,Customer,User,Appointment,ClinicalRecord,ClinicalAudit};
use App\Support\Odontogram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
class ClinicalPrintTest extends TestCase {
 use RefreshDatabase;
 private Company $clinic; private User $dentist; private Customer $patient;
 protected function setUp():void {
  parent::setUp();$this->clinic=Company::create(['name'=>'Clínica teste','slug'=>'print-test','booking_timezone'=>'America/Bahia']);
  $this->dentist=$this->user();$this->patient=Customer::create(['company_id'=>$this->clinic->id,'name'=>'Paciente privado','active'=>true]);
  Appointment::create(['company_id'=>$this->clinic->id,'professional_id'=>$this->dentist->id,'customer_id'=>$this->patient->id,'created_by'=>$this->dentist->id,'client_name'=>$this->patient->name,'service'=>'Consulta','starts_at'=>'2026-01-01 10:00','ends_at'=>'2026-01-01 11:00','status'=>'completed']);
 }
 private function user(string $role='professional',?Company $company=null):User{return User::create(['company_id'=>($company??$this->clinic)->id,'name'=>'Dentista teste','email'=>Str::uuid().'@example.test','password'=>'Test-Password-2026','role'=>$role,'active'=>true,'clinical_access'=>true,'professional_registration'=>'CRO-BA 12345']);}
 private function record(string $kind='evolution',array $payload=['content'=>'Texto clínico sigiloso'],string $date='2026-01-01 10:00',?int $parent=null):ClinicalRecord{return ClinicalRecord::create(['company_id'=>$this->clinic->id,'customer_id'=>$this->patient->id,'author_id'=>$this->dentist->id,'author_name'=>'Autoria preservada','author_registration'=>'CRO-BA 12345','kind'=>$kind,'parent_id'=>$parent,'payload'=>$payload,'occurred_at'=>$date,'request_key'=>hash('sha256',Str::uuid())]);}
 private function url(array $query=[]):string{return route('clinical.print',['id'=>$this->patient->id]+$query);}
 public function test_private_preview_preserves_versions_authorship_and_escapes_text():void {
  $first=$this->record('anamnesis',['chief_complaint'=>'Primeira queixa','informant'=>'Paciente']);
  $this->record('anamnesis',['chief_complaint'=>'Nova versão','informant'=>'Paciente'],'2026-01-02 10:00',$first->id);
  $e=$this->record('evolution',['content'=>'<script>alert(1)</script>']);$this->record('addendum',['content'=>'Complemento'],'2026-01-03 10:00',$e->id);
  $this->patient->update(['active'=>false]);
  $this->actingAs($this->dentist)->get($this->url())->assertOk()->assertHeader('Cache-Control','no-store, private')->assertHeader('Referrer-Policy','no-referrer')->assertHeader('X-Frame-Options','DENY')->assertSee('Primeira queixa')->assertSee('Nova versão')->assertSee('Complemento')->assertSee('Autoria preservada')->assertSee('Cadastro arquivado')->assertSee('Referente ao registro #'.$first->id)->assertDontSee('<script>alert(1)</script>',false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;',false);
  $this->assertDatabaseHas('clinical_audits',['event'=>'view_clinical_print','customer_id'=>$this->patient->id,'actor_id'=>$this->dentist->id]);
 }
 public function test_denies_administrative_unapproved_unlinked_and_other_company_access():void {
  $this->get($this->url())->assertRedirect(route('login'));
  foreach(['owner','reception'] as $role){$this->flushSession();$this->actingAs($this->user($role))->get($this->url())->assertForbidden();}
  $this->flushSession();$this->actingAs($this->user())->get($this->url())->assertNotFound();
  $other=Company::create(['name'=>'Outra clínica','slug'=>'other-print']);$this->flushSession();$this->actingAs($this->user('professional',$other))->get($this->url())->assertNotFound();
  $this->dentist->update(['clinical_access'=>false]);$this->flushSession();$this->actingAs($this->dentist)->get($this->url())->assertForbidden();
  $this->assertDatabaseCount('clinical_audits',0);
 }
 public function test_cancelled_only_link_is_denied_and_revocation_is_respected():void {
  Appointment::query()->update(['status'=>'cancelled']);$this->actingAs($this->dentist)->get($this->url())->assertNotFound();
  Appointment::query()->update(['status'=>'completed']);$this->get($this->url())->assertOk();
  $this->dentist->update(['clinical_access'=>false]);$this->flushSession();$this->actingAs($this->dentist->fresh())->get($this->url())->assertForbidden();
 }
 public function test_dates_include_end_of_day_and_support_single_bound():void {
  $this->record('evolution',['content'=>'Primeiro dia'],'2026-01-01 23:59');$this->record('evolution',['content'=>'Segundo dia'],'2026-01-02 00:00');
  $this->actingAs($this->dentist)->get($this->url(['from'=>'2026-01-01','to'=>'2026-01-01']))->assertOk()->assertSee('Primeiro dia')->assertDontSee('Segundo dia');
  $this->get($this->url(['to'=>'2026-01-01']))->assertOk()->assertSee('Primeiro dia')->assertDontSee('Segundo dia');
  $this->get($this->url(['from'=>'2026-01-02']))->assertOk()->assertSee('Segundo dia')->assertDontSee('Primeiro dia');
  $this->get($this->url(['from'=>'2026-02-01','to'=>'2026-01-01']))->assertSessionHasErrors('to');
  $this->get($this->url(['from'=>'bad']))->assertSessionHasErrors('from');
  $this->get($this->url(['page'=>0]))->assertSessionHasErrors('page');
  $this->get($this->url(['from'=>'2027-01-01']))->assertOk()->assertSee('Nenhum registro clínico');
 }
 public function test_batch_boundaries_have_no_missing_or_duplicate_records_and_keep_filters():void {
  for($i=1;$i<=26;$i++)$this->record('evolution',['content'=>'Registro único '.$i]);
  $this->actingAs($this->dentist)->get($this->url(['from'=>'2026-01-01']))->assertOk()->assertSee('Lote 1 de 2')->assertViewHas('records',fn($r)=>$r->count()===25&&$r->total()===26&&str_contains($r->nextPageUrl(),'from=2026-01-01'))->assertDontSee('Registro único 26');
  $this->get($this->url(['page'=>2]))->assertOk()->assertSee('Lote 2 de 2')->assertSee('Registro único 26')->assertViewHas('records',fn($r)=>$r->count()===1);
  $this->get($this->url(['page'=>3]))->assertNotFound();
 }
 public function test_odontogram_and_plan_are_rendered_and_audit_does_not_show_content():void {
  $teeth=Odontogram::blank();$teeth[11]=['status'=>'restored','notes'=>'Nota clínica do dente'];
  $this->record('odontogram',['dentition'=>'permanent','notes'=>'Observação do odontograma','teeth'=>$teeth]);
  $this->record('treatment_plan',['title'=>'Plano sigiloso','status'=>'draft','notes'=>'Orientações do plano','items'=>[['description'=>'Procedimento sigiloso','region'=>'11','quantity'=>2,'status'=>'planned','notes'=>'Nota do item','unit_price_cents'=>10000]]]);
  $this->actingAs($this->dentist)->get($this->url())->assertOk()->assertSee('Permanente')->assertSee('Nota clínica do dente')->assertSee('Restaurado')->assertSee('Plano sigiloso')->assertSee('200,00')->assertSee('Arquivos de exames não são incorporados');
  $this->flushSession();$this->actingAs($this->user('owner'))->get(route('clinical.audit'))->assertOk()->assertSee('Consulta à impressão do prontuário')->assertDontSee('Plano sigiloso')->assertDontSee('Nota clínica do dente');
 }
}
