<?php
namespace App\Http\Controllers;
use App\Models\{Customer,User,Company,Appointment,ClinicalRecord,ClinicalAudit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class ClinicalController extends Controller {
 public const QUESTIONS=['allergies'=>'Alergias','medications'=>'Medicamentos em uso','conditions'=>'Condições de saúde relatadas','surgeries'=>'Cirurgias e internações','previous_care'=>'Histórico odontológico','habits'=>'Hábitos relatados','observations'=>'Outras observações'];
 protected function clinician(Request $r):void {abort_unless($r->user()->canUseClinical(),403);}
 protected function patients(Request $r){
  $this->clinician($r);$u=$r->user();return Customer::where('company_id',$u->company_id)->whereIn('id',Appointment::where('company_id',$u->company_id)->where('professional_id',$u->id)->whereNotNull('customer_id')->where('status','!=','cancelled')->select('customer_id'));
 }
 protected function patient(Request $r,int $id):Customer{return $this->patients($r)->findOrFail($id);}
 protected function audit(Request $r,string $event,?int $customer=null,?int $record=null):void {ClinicalAudit::create(['company_id'=>$r->user()->company_id,'actor_id'=>$r->user()->id,'customer_id'=>$customer,'record_id'=>$record,'event'=>$event]);}
 protected function response(string $view,array $data){return response()->view($view,$data)->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');}
 public function index(Request $r){$q=$this->patients($r);$r->validate(['q'=>'nullable|string|max:100']);if($r->filled('q')){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$r->input('q')).'%';$q->whereRaw("name LIKE ? ESCAPE '!'",[$term]);}$patients=$q->orderBy('name')->paginate(20)->withQueryString();$this->audit($r,'list');return $this->response('clinical.index',compact('patients'));}
 public function show(Request $r,int $id){
  $patient=$this->patient($r,$id);$query=ClinicalRecord::where('company_id',$patient->company_id)->where('customer_id',$patient->id);
  $latest=(clone $query)->where('kind','anamnesis')->latest('id')->first();$records=$query->latest('id')->paginate(15);$questions=self::QUESTIONS;
  $localNow=app(\App\Services\Availability::class)->localNow($r->user()->company);$this->audit($r,'view',$id);
  return $this->response('clinical.show',compact('patient','latest','records','questions','localNow'));
 }
 public function store(Request $r,int $id){
  $patient=$this->patient($r,$id);abort_unless($patient->active,422,'Paciente arquivado. A recepção deve reativar o cadastro antes de novos registros.');
  $rules=['kind'=>'required|in:anamnesis,evolution,addendum','request_id'=>'required|uuid','base_id'=>'nullable|integer','parent_id'=>'nullable|integer','occurred_at'=>['required','date_format:Y-m-d\TH:i'],'clinical'=>'required|array'];
  if($r->input('kind')==='anamnesis'){$rules['clinical.chief_complaint']='required|string|max:3000';$rules['clinical.informant']='required|string|max:150';foreach(self::QUESTIONS as $key=>$label){$rules['clinical.'.$key.'.status']='required|in:yes,no,unknown';$rules['clinical.'.$key.'.detail']='nullable|string|max:3000';}}
  else {$rules['clinical.content']='required|string|min:3|max:12000';if($r->input('kind')==='addendum')$rules['parent_id']='required|integer';}
  $data=$r->validate($rules);$occurred=Carbon::parse($data['occurred_at']);$localNow=app(\App\Services\Availability::class)->localNow($r->user()->company);
  if($occurred->gt($localNow)||$occurred->lt(Carbon::parse('1900-01-01')))throw ValidationException::withMessages(['occurred_at'=>'Informe uma data de atendimento válida, sem horário futuro.']);
  $key=hash('sha256',$r->user()->company_id.'|'.$r->user()->id.'|'.$data['request_id']);
  DB::transaction(function()use($r,$id,$data,$occurred,$key){
   Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();
   $fresh=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($fresh->canUseClinical(),403);
   $patient=$this->patient($r,$id);abort_unless($patient->active,422);
   if($existing=ClinicalRecord::where('request_key',$key)->first()){abort_unless($existing->customer_id===$id&&$existing->kind===$data['kind'],409);return;}
   $query=ClinicalRecord::where('company_id',$patient->company_id)->where('customer_id',$id);
   $parent=null;$payload=[];
   if($data['kind']==='anamnesis'){
    $latest=(clone $query)->where('kind','anamnesis')->latest('id')->first();
    if((int)($data['base_id']??0)!==(int)($latest?->id??0))throw ValidationException::withMessages(['base_id'=>'Outra anamnese foi registrada. Reabra a ficha e revise a versão mais recente antes de salvar.']);
    $parent=$latest?->id;$payload=['chief_complaint'=>$data['clinical']['chief_complaint'],'informant'=>$data['clinical']['informant']];
    foreach(self::QUESTIONS as $k=>$label)$payload[$k]=['status'=>$data['clinical'][$k]['status'],'detail'=>$data['clinical'][$k]['detail']??null];
   }else{
    $payload=['content'=>$data['clinical']['content']];
    if($data['kind']==='addendum')$parent=(clone $query)->whereIn('kind',['evolution','addendum'])->findOrFail($data['parent_id'])->id;
   }
   $record=ClinicalRecord::create(['company_id'=>$patient->company_id,'customer_id'=>$id,'author_id'=>$fresh->id,'author_name'=>$fresh->name,'author_registration'=>$fresh->professional_registration??'','kind'=>$data['kind'],'parent_id'=>$parent,'payload'=>$payload,'occurred_at'=>$occurred,'request_key'=>$key]);
   $this->audit($r,'create_'.$data['kind'],$id,$record->id);
  },3);
  return redirect()->route('clinical.show',$id)->with('success','Registro clínico salvo. O conteúdo anterior permanece preservado.');
 }
 public function auditLog(Request $r){abort_unless($r->user()->isOwner(),403);$events=ClinicalAudit::where('company_id',$r->user()->company_id)->with(['actor','patient','target'])->latest('id')->paginate(30);return $this->response('clinical.audit',compact('events'));}
}
