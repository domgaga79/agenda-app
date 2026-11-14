<?php
namespace Tests\Feature;
use App\Models\{Company,User,Service,Customer,WorkingPeriod,Appointment,AppointmentConfirmation,ClinicalRecord,ClinicalAttachment,FinancialQuote,FinancialPayment};
use App\Support\Odontogram;
use App\Http\Controllers\ClinicalController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\{Carbon,Str};
use Illuminate\Support\Facades\{DB,Storage,Auth};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class ClinicWorkflowTest extends TestCase {
 use RefreshDatabase;
 public function test_complete_clinic_workflow_from_public_booking_to_partial_payment_and_reversal():void {
  $this->travelTo(Carbon::parse('2026-09-28 08:00','America/Bahia'));
  Storage::fake('clinical_private');
  $company=Company::create(['name'=>'Clínica fictícia integrada','slug'=>'workflow-demo','active'=>true,'booking_enabled'=>true,'booking_timezone'=>'America/Bahia']);
  $makeUser=fn($role)=>User::create(['company_id'=>$company->id,'name'=>'Teste '.$role,'email'=>$role.'@example.test','password'=>'Test-Only-2026','role'=>$role,'active'=>true,'must_change_password'=>false,'clinical_access'=>$role==='professional','professional_registration'=>$role==='professional'?'CRO-BA TESTE':null]);
  $owner=$makeUser('owner');$reception=$makeUser('reception');$dentist=$makeUser('professional');
  $service=Service::create(['company_id'=>$company->id,'name'=>'Consulta fictícia','duration_minutes'=>30,'price_cents'=>10000,'active'=>true]);
  DB::table('professional_services')->insert(['company_id'=>$company->id,'professional_id'=>$dentist->id,'service_id'=>$service->id]);
  WorkingPeriod::create(['company_id'=>$company->id,'professional_id'=>$dentist->id,'weekday'=>1,'start_time'=>'09:00','end_time'=>'12:00']);
  // Public reservation: use the nonce issued by the real page.
  $this->get(route('booking.index',['slug'=>$company->slug,'service_id'=>$service->id,'professional_id'=>$dentist->id,'date'=>'2026-09-28']))->assertOk()->assertSee('10:00');
  $nonce=session('booking_nonce.'.$company->id);
  $this->post(route('booking.store',$company->slug),['professional_id'=>$dentist->id,'service_id'=>$service->id,'starts_at'=>'2026-09-28 10:00','client_name'=>'Paciente fictício integrado','client_phone'=>'71999992222','acknowledge'=>1,'nonce'=>$nonce])->assertSessionHasNoErrors()->assertRedirect(route('booking.done',$company->slug));
  $appointment=Appointment::sole();$this->assertNull($appointment->customer_id);$this->assertSame('10:30',$appointment->ends_at->format('H:i'));
  // Reception creates and links the patient through the application.
  $this->actingAs($reception)->post(route('customers.store'),['name'=>'Paciente fictício integrado','phone'=>'71999992222','active'=>1])->assertSessionHasNoErrors();$patient=Customer::sole();
  $form=['professional_id'=>$dentist->id,'customer_id'=>$patient->id,'service_id'=>$service->id,'client_name'=>$patient->name,'client_phone'=>'71999992222','service'=>$service->name,'starts_at'=>'2026-09-28T10:00','ends_at'=>'2026-09-28T10:30','status'=>'scheduled','notes'=>''];
  $this->put(route('appointments.update',$appointment->id),$form)->assertSessionHasNoErrors();$this->assertEquals($patient->id,$appointment->fresh()->customer_id);
  $this->get(route('clinical.show',$patient->id))->assertForbidden();
  // Reminder issuance and public confirmation do not send WhatsApp messages.
  $this->post(route('reminders.issue',$appointment->id),['request_id'=>(string)Str::uuid(),'acknowledge'=>1])->assertSessionHasNoErrors();$link=AppointmentConfirmation::sole();
  Auth::logout();$this->flushSession();
  $this->get(route('confirmation.show',$link->token))->assertOk();
  $this->post(route('confirmation.respond',$link->token),['choice'=>'confirmed','acknowledge'=>1])->assertRedirect();$this->assertSame('confirmed',$appointment->fresh()->status);
  $this->travelTo(Carbon::parse('2026-09-28 10:30','America/Bahia'));$this->actingAs($dentist);
  $this->get(route('clinical.show',$patient->id))->assertOk();
  $anamnesis=['chief_complaint'=>'Queixa fictícia para teste','informant'=>'Paciente fictício'];foreach(ClinicalController::QUESTIONS as $key=>$label)$anamnesis[$key]=['status'=>'unknown','detail'=>null];
  $this->post(route('clinical.store',$patient->id),['request_id'=>(string)Str::uuid(),'kind'=>'anamnesis','occurred_at'=>'2026-09-28T10:00','clinical'=>$anamnesis])->assertSessionHasNoErrors();
  $this->post(route('clinical.store',$patient->id),['request_id'=>(string)Str::uuid(),'kind'=>'evolution','occurred_at'=>'2026-09-28T10:20','clinical'=>['content'=>'Evolução fictícia integrada']])->assertSessionHasNoErrors();
  $teeth=Odontogram::blank();$teeth[11]=['status'=>'sound','notes'=>null];
  $this->post(route('odontogram.save',$patient->id),['request_id'=>(string)Str::uuid(),'occurred_at'=>'2026-09-28T10:20','clinical'=>['dentition'=>'permanent','teeth'=>$teeth,'notes'=>'Odontograma fictício']])->assertSessionHasNoErrors();
  $this->post(route('treatment.save',$patient->id),['request_id'=>(string)Str::uuid(),'clinical'=>['title'=>'Plano fictício','status'=>'draft','notes'=>'','items'=>[['id'=>(string)Str::uuid(),'service_id'=>$service->id,'description'=>'Consulta fictícia','region'=>'11','quantity'=>1,'unit_price'=>'100,00','status'=>'planned','notes'=>'']]]])->assertSessionHasNoErrors();
  $plan=ClinicalRecord::where('kind','treatment_plan')->sole();
  $pdf="%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n";
  $this->post(route('attachments.store',$patient->id),['request_id'=>(string)Str::uuid(),'clinical'=>['title'=>'Anexo fictício'],'file'=>UploadedFile::fake()->createWithContent('teste.pdf',$pdf),'acknowledge'=>1])->assertSessionHasNoErrors();$attachment=ClinicalAttachment::sole();
  $this->get(route('attachments.download',[$patient->id,$attachment->id]))->assertOk()->assertContent($pdf);
  $this->get(route('clinical.print',$patient->id))->assertOk()->assertSee('Evolução fictícia integrada')->assertSee('Plano fictício');
  $this->post(route('treatment.export',$patient->id),['base_id'=>$plan->id,'commercial_description'=>'Orçamento fictício integrado','confirm'=>1])->assertSessionHasNoErrors();$quote=FinancialQuote::sole();$this->assertSame(10000,$quote->total_cents);
  $form['status']='completed';$this->put(route('appointments.update',$appointment->id),$form)->assertSessionHasNoErrors();$this->assertSame('completed',$appointment->fresh()->status);
  // Reception approves and records a partial payment, then owner reverses it.
  $this->flushSession();$this->actingAs($reception);
  $this->post(route('finance.approve',$quote->id),['confirm'=>1,'revision'=>$quote->revision,'approved_by_name'=>'Paciente fictício','approved_on'=>'2026-09-28','approval_channel'=>'in_person','deposit'=>'0','count'=>1,'first_due'=>'2026-09-28'])->assertSessionHasNoErrors();$installment=$quote->fresh()->installments()->sole();
  $payment=['request_id'=>(string)Str::uuid(),'amount'=>'40,00','paid_on'=>'2026-09-28','method'=>'pix'];
  $this->post(route('finance.pay',$installment->id),$payment)->assertSessionHasNoErrors();$this->post(route('finance.pay',$installment->id),$payment)->assertSessionHasNoErrors();$this->assertDatabaseCount('financial_payments',1);
  $this->get(route('finance.receivables'))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->balance===6000);
  $this->get(route('clinical.show',$patient->id))->assertForbidden();
  $this->get(route('reports.schedule',['from'=>'2026-09-28','to'=>'2026-09-28']))->assertOk()->assertViewHas('report',fn($r)=>$r['totals']['completed']===1);
  $this->get(route('appointments.daily',['date'=>'2026-09-28']))->assertOk()->assertSee('Paciente fictício integrado');
  $this->flushSession();$this->actingAs($owner);
  $this->post(route('finance.reverse',FinancialPayment::sole()->id),['reason'=>'Estorno fictício de conferência'])->assertSessionHasNoErrors();
  $this->get(route('finance.receivables'))->assertOk()->assertViewHas('stats',fn($s)=>(int)$s->balance===10000);
  $this->get(route('clinical.audit'))->assertOk()->assertDontSee('Evolução fictícia integrada');
 }
}
