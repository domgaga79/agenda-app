<?php
namespace App\Services;
use App\Models\{PatientFollowup,FollowupEvent,Appointment,User,Customer,Service};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class FollowupScheduling {
 public function authorize(Request $r):void {abort_unless($r->user()->active&&($r->user()->isOwner()||$r->user()->isReception()),403);}
 public function find(Request $r,int $id):PatientFollowup {$this->authorize($r);$company=(int)$r->user()->company_id;return PatientFollowup::forCompany($company)->with(['customer'=>fn($q)=>$q->where('company_id',$company),'professional'=>fn($q)=>$q->where('company_id',$company),'service'=>fn($q)=>$q->where('company_id',$company)])->findOrFail($id);}
 public function schedulable(PatientFollowup $f):void {$customer=Customer::forCompany((int)$f->company_id)->find($f->customer_id);$professional=$f->professional_id?User::forCompany((int)$f->company_id)->find($f->professional_id):null;$service=$f->service_id?Service::forCompany((int)$f->company_id)->find($f->service_id):null;if(!$f->isOpen()||!$customer?->active||($f->professional_id&&!$professional?->active)||($f->service_id&&!$service?->active))throw ValidationException::withMessages(['followup_id'=>'A solicitação não está disponível para agendar. Confira a situação e os cadastros vinculados.']);}
 public function book(Request $r,Appointment $a):void {
  if(!$r->filled('followup_id'))return;
  $u=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($u->active&&$u->company_id===$a->company_id&&($u->isOwner()||$u->isReception()),403);
  $f=$this->find($r,$r->integer('followup_id'));$this->schedulable($f);
  if($f->revision!==$r->integer('followup_revision'))$this->fail('A solicitação mudou. Reabra o agendamento a partir da lista.');
  if((int)$a->customer_id!==(int)$f->customer_id||($f->professional_id&&(int)$a->professional_id!==(int)$f->professional_id)||($f->service_id&&(int)$a->service_id!==(int)$f->service_id))$this->fail('Paciente, profissional e procedimento precisam corresponder à solicitação. Revise as preferências antes de agendar.');
  if(!in_array($a->status,['scheduled','confirmed'],true)||$a->starts_at->lte(app(Availability::class)->localNow($a->company)))$this->fail('Escolha um horário futuro e a situação Agendado ou Confirmado.');
  $date=$a->starts_at->format('Y-m-d');$hour=$a->starts_at->hour;
  if($date<$f->from_date->format('Y-m-d')||($f->to_date&&$date>$f->to_date->format('Y-m-d'))||($f->period==='morning'&&$hour>=12)||($f->period==='afternoon'&&($hour<12||$hour>=18))||($f->period==='evening'&&$hour<18))$this->fail('O início do atendimento deve respeitar as datas e o período preferido. Revise as preferências se necessário.');
  $f->update(['status'=>'scheduled','appointment_id'=>$a->id,'revision'=>$f->revision+1]);FollowupEvent::create(['company_id'=>$f->company_id,'followup_id'=>$f->id,'actor_id'=>$u->id,'event'=>'scheduled','payload'=>['appointment_id'=>$a->id,'starts_at'=>$a->starts_at->format('Y-m-d H:i')]]);
 }
 private function fail(string $message):never {throw ValidationException::withMessages(['followup_id'=>$message]);}
 // Caller holds the company lock used by every scheduling mutation.
 public function release(Appointment $a,?int $actor,string $reason,bool $force=false):void {
  $entries=PatientFollowup::where('company_id',$a->company_id)->where('appointment_id',$a->id)->where('status','scheduled')->get();
  foreach($entries as $f){if(!$force&&$a->status!=='cancelled'&&(int)$a->customer_id===(int)$f->customer_id)continue;$f->update(['status'=>'pending','appointment_id'=>null,'revision'=>$f->revision+1]);FollowupEvent::create(['company_id'=>$f->company_id,'followup_id'=>$f->id,'actor_id'=>$actor,'event'=>'reopened','payload'=>['reason'=>$reason,'appointment_id'=>$a->id]]);}
 }
}
