<?php
namespace App\Http\Controllers;
use App\Models\{Company,User,ClinicalRecord};
use App\Support\Odontogram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class OdontogramController extends ClinicalController {
 private function records(int $company,int $patient){return ClinicalRecord::where('company_id',$company)->where('customer_id',$patient)->where('kind','odontogram');}
 public function editChart(Request $r,int $id){
  $patient=$this->patient($r,$id);$query=$this->records($patient->company_id,$id);$latest=(clone $query)->latest('id')->first();$versions=$query->latest('id')->paginate(10);
  $chart=$latest?->payload??['dentition'=>'permanent','notes'=>null,'teeth'=>Odontogram::blank()];$localNow=app(\App\Services\Availability::class)->localNow($r->user()->company);
  $this->audit($r,'view_odontogram',$id);return $this->response('odontogram.edit',compact('patient','latest','chart','versions','localNow'));
 }
 public function version(Request $r,int $id,int $version){
  $patient=$this->patient($r,$id);$record=$this->records($patient->company_id,$id)->findOrFail($version);$chart=$record->payload;
  $this->audit($r,'view_odontogram_version',$id,$record->id);return $this->response('odontogram.version',compact('patient','record','chart'));
 }
 public function saveChart(Request $r,int $id){
  $patient=$this->patient($r,$id);abort_unless($patient->active,422,'Paciente arquivado. Reative o cadastro antes de novos registros.');
  $data=$r->validate(['request_id'=>'required|uuid','base_id'=>'nullable|integer','occurred_at'=>['required','date_format:Y-m-d\TH:i'],'clinical'=>'required|array',
   'clinical.dentition'=>['required',Rule::in(array_keys(Odontogram::MODES))],'clinical.notes'=>'nullable|string|max:3000',
   'clinical.teeth'=>'required|array|size:52','clinical.teeth.*'=>'required|array','clinical.teeth.*.status'=>['required',Rule::in(array_keys(Odontogram::STATES))],'clinical.teeth.*.notes'=>'nullable|string|max:1200']);
  $expected=Odontogram::codes();$actual=array_keys($data['clinical']['teeth']);sort($expected);sort($actual);
  if($actual!==$expected)throw ValidationException::withMessages(['clinical.teeth'=>'A numeração dos dentes está inválida ou incompleta. Reabra o odontograma.']);
  $occurred=Carbon::parse($data['occurred_at']);$now=app(\App\Services\Availability::class)->localNow($r->user()->company);
  if($occurred->gt($now)||$occurred->lt(Carbon::parse('1900-01-01')))throw ValidationException::withMessages(['occurred_at'=>'Informe uma data de avaliação válida, sem horário futuro.']);
  $key=hash('sha256','odontogram|'.$r->user()->company_id.'|'.$r->user()->id.'|'.$data['request_id']);
  DB::transaction(function()use($r,$id,$data,$occurred,$key){
   $company=Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();$author=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($author->canUseClinical(),403);
   $patient=$this->patient($r,$id);abort_unless($patient->active,422);
   if($existing=ClinicalRecord::where('request_key',$key)->first()){abort_unless($existing->customer_id===$id&&$existing->kind==='odontogram',409);return;}
   $latest=$this->records($company->id,$id)->latest('id')->first();
   if((int)($data['base_id']??0)!==(int)($latest?->id??0))throw ValidationException::withMessages(['base_id'=>'O odontograma foi atualizado em outra janela. Reabra a página e revise a versão atual antes de salvar.']);
   $teeth=[];foreach(Odontogram::codes() as $code){$item=$data['clinical']['teeth'][$code];$teeth[$code]=['status'=>$item['status'],'notes'=>$item['notes']??null];}
   $record=ClinicalRecord::create(['company_id'=>$company->id,'customer_id'=>$id,'author_id'=>$author->id,'author_name'=>$author->name,'author_registration'=>$author->professional_registration??'','kind'=>'odontogram','parent_id'=>$latest?->id,'payload'=>['dentition'=>$data['clinical']['dentition'],'notes'=>$data['clinical']['notes']??null,'teeth'=>$teeth],'occurred_at'=>$occurred,'request_key'=>$key]);
   $this->audit($r,'create_odontogram',$id,$record->id);
  },3);
  return redirect()->route('odontogram.edit',$id)->with('success','Versão do odontograma salva. Os registros anteriores foram preservados.');
 }
}
