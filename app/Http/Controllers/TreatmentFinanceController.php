<?php
namespace App\Http\Controllers;
use App\Models\{Company,User,ClinicalRecord,FinancialQuote,FinancialEvent};
use App\Support\TreatmentPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class TreatmentFinanceController extends ClinicalController {
 public function export(Request $r,int $id){
  $this->patient($r,$id);$d=$r->validate(['base_id'=>'required|integer','commercial_description'=>'required|string|max:500','confirm'=>'accepted']);
  DB::transaction(function()use($r,$id,$d){Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();$u=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($u->canUseClinical()&&$u->company_id===$r->user()->company_id,403);$patient=$this->patient($r,$id);abort_unless($patient->active,422);
   $plan=ClinicalRecord::where('company_id',$u->company_id)->where('customer_id',$id)->where('kind','treatment_plan')->latest('id')->firstOrFail();
   if($plan->id!==(int)$d['base_id'])throw ValidationException::withMessages(['base_id'=>'O plano mudou. Reabra a página antes de encaminhar.']);
   if(FinancialQuote::where('source_record_id',$plan->id)->exists())return;
   $totals=TreatmentPlan::totals($plan->payload['items']);if($plan->payload['status']==='cancelled'||$totals['unpriced_items']>0||$totals['total_cents']<1)throw ValidationException::withMessages(['base_id'=>'Defina os preços de todos os itens ativos e um total positivo. Planos cancelados não podem gerar orçamento.']);
   $q=FinancialQuote::create(['company_id'=>$u->company_id,'customer_id'=>$id,'created_by'=>$u->id,'source_record_id'=>$plan->id,'customer_name'=>$patient->name,'description'=>$d['commercial_description'],'gross_cents'=>$totals['total_cents'],'discount_cents'=>0,'total_cents'=>$totals['total_cents'],'request_key'=>hash('sha256','export|'.$u->company_id.'|'.$plan->id)]);
   FinancialEvent::create(['company_id'=>$u->company_id,'quote_id'=>$q->id,'actor_id'=>$u->id,'event'=>'exported','amount_cents'=>$q->total_cents]);$this->audit($r,'export_treatment_budget',$id,$plan->id);
  },3);return redirect()->route('treatment.edit',$id)->with('success','Esta versão já está no Financeiro. A recepção pode revisar o desconto e registrar a aprovação.');
 }
}
