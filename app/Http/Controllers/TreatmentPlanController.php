<?php
namespace App\Http\Controllers;
use App\Models\{Company,User,ClinicalRecord,Service};
use App\Support\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class TreatmentPlanController extends ClinicalController {
 public function editPlan(Request $r,int $id){
  $patient=$this->patient($r,$id);$query=ClinicalRecord::where('company_id',$patient->company_id)->where('customer_id',$id)->where('kind','treatment_plan');
  $latest=(clone $query)->latest('id')->first();$versions=$query->latest('id')->paginate(10);
  $serviceIds=array_filter(array_column($latest?->payload['items']??[],'service_id'));
  $services=Service::where('company_id',$patient->company_id)->where(fn($q)=>$q->where('active',true)->orWhereIn('id',$serviceIds))->orderBy('name')->get();
  $budget=$latest?\App\Models\FinancialQuote::where('company_id',$patient->company_id)->where('source_record_id',$latest->id)->first():null;
  $this->audit($r,'view_treatment_plan',$id);return $this->response('treatment.edit',compact('patient','latest','versions','services','budget'));
 }
 public function savePlan(Request $r,int $id){
  $patient=$this->patient($r,$id);abort_unless($patient->active,422,'Paciente arquivado. Reative o cadastro para novos registros.');
  $data=$r->validate(['request_id'=>'required|uuid','base_id'=>'nullable|integer','clinical'=>'required|array',
   'clinical.title'=>'required|string|max:150','clinical.status'=>['required',Rule::in(array_keys(TreatmentPlan::STATES))],'clinical.notes'=>'nullable|string|max:3000',
   'clinical.items'=>'required|array|min:1|max:50','clinical.items.*'=>'required|array','clinical.items.*.id'=>'required|uuid|distinct',
   'clinical.items.*.service_id'=>'nullable|integer','clinical.items.*.description'=>'required|string|max:150','clinical.items.*.region'=>'nullable|string|max:40',
   'clinical.items.*.quantity'=>'required|integer|min:1|max:100','clinical.items.*.unit_price'=>['nullable','regex:/^\d{1,6}([.,]\d{1,2})?$/'],
   'clinical.items.*.status'=>['required',Rule::in(array_keys(TreatmentPlan::ITEM_STATES))],'clinical.items.*.notes'=>'nullable|string|max:1000']);
  $key=hash('sha256','treatment|'.$r->user()->company_id.'|'.$r->user()->id.'|'.$data['request_id']);
  DB::transaction(function()use($r,$id,$data,$key){
   $company=Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();
   $author=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($author->canUseClinical(),403);
   $patient=$this->patient($r,$id);abort_unless($patient->active,422);
   if($existing=ClinicalRecord::where('request_key',$key)->first()){abort_unless($existing->customer_id===$id&&$existing->kind==='treatment_plan',409);return;}
   $latest=ClinicalRecord::where('company_id',$company->id)->where('customer_id',$id)->where('kind','treatment_plan')->latest('id')->first();
   if((int)($data['base_id']??0)!==(int)($latest?->id??0))throw ValidationException::withMessages(['base_id'=>'O plano foi atualizado em outra janela. Reabra a página para revisar a versão mais recente.']);
   $previous=collect($latest?->payload['items']??[])->keyBy('id');$items=[];
   foreach($data['clinical']['items'] as $index=>$input){
    $service=null;$old=$previous->get($input['id']);
    if(!empty($input['service_id'])){
     $service=Service::where('company_id',$company->id)->findOrFail($input['service_id']);
     if(!$service->active && (!$old || (int)($old['service_id']??0)!==$service->id))throw ValidationException::withMessages(['clinical.items.'.$index.'.service_id'=>'Um procedimento arquivado só pode ser mantido no item em que já estava.']);
    }
    $items[]=['id'=>$input['id'],'service_id'=>$service?->id,'service_name'=>$service?->name,'description'=>$input['description'],'region'=>$input['region']??null,'quantity'=>(int)$input['quantity'],'unit_price_cents'=>TreatmentPlan::cents($input['unit_price']??null),'status'=>$input['status'],'notes'=>$input['notes']??null];
   }
   $totals=TreatmentPlan::totals($items);
   if($data['clinical']['status']==='completed' && (!$totals['active_items'] || $totals['completed_items']!==$totals['active_items']))throw ValidationException::withMessages(['clinical.status'=>'Para concluir o plano, todos os itens não cancelados precisam estar concluídos.']);
   $payload=['title'=>$data['clinical']['title'],'status'=>$data['clinical']['status'],'notes'=>$data['clinical']['notes']??null,'items'=>$items,'totals'=>$totals];
   $record=ClinicalRecord::create(['company_id'=>$company->id,'customer_id'=>$id,'author_id'=>$author->id,'author_name'=>$author->name,'author_registration'=>$author->professional_registration??'','kind'=>'treatment_plan','parent_id'=>$latest?->id,'payload'=>$payload,'occurred_at'=>app(\App\Services\Availability::class)->localNow($company),'request_key'=>$key]);
   $this->audit($r,'create_treatment_plan',$id,$record->id);
  },3);
  return redirect()->route('treatment.edit',$id)->with('success','Versão do plano salva. O histórico anterior foi preservado.');
 }
}
