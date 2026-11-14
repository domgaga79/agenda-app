<?php
namespace App\Http\Controllers;
use App\Models\{Company,User,Service,WorkingPeriod,ScheduleBlock,Appointment};
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class AvailabilityController extends Controller {
 private function authorizeManager(Request $r):void { abort_unless($r->user()->isOwner()||$r->user()->isReception(),403); }
 private function dentist(Request $r,int $id):User {return User::where('company_id',$r->user()->company_id)->where('role','professional')->findOrFail($id);}
 private function locked(Request $r,callable $callback){return DB::transaction(function()use($r,$callback){$c=Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();return $callback($c);},3);}
 public function index(Request $r){
  $this->authorizeManager($r);$r->validate(['professional'=>['nullable','integer']]);$company=$r->user()->company;
  $professionals=User::where('company_id',$company->id)->where('role','professional')->orderBy('name')->get();
  $professional=$r->filled('professional')?$this->dentist($r,$r->integer('professional')):$professionals->first();
  $periods=$professional?WorkingPeriod::where('company_id',$company->id)->where('professional_id',$professional->id)->orderBy('weekday')->orderBy('start_time')->get():collect();
  $assigned=$professional?DB::table('professional_services')->where('company_id',$company->id)->where('professional_id',$professional->id)->pluck('service_id')->all():[];
  $services=Service::where('company_id',$company->id)->where('active',true)->orderBy('name')->get();
  $blocks=ScheduleBlock::where('company_id',$company->id)->where('ends_at','>',app(Availability::class)->localNow($company))->orderBy('starts_at')->paginate(20);
  return view('availability.index',compact('company','professionals','professional','periods','assigned','services','blocks'));
 }
 public function settings(Request $r){
  $this->authorizeManager($r);abort_unless($r->user()->isOwner(),403);
  $data=$r->validate(['booking_enabled'=>'required|boolean','booking_timezone'=>'required|in:America/Bahia,America/Sao_Paulo,America/Manaus,America/Cuiaba,America/Rio_Branco,America/Noronha','booking_notice_hours'=>'required|integer|min:1|max:168','booking_window_days'=>'required|integer|min:1|max:180','booking_address'=>'nullable|string|max:250','booking_description'=>'nullable|string|max:1000']);
  $this->locked($r,fn($c)=>$c->update($data));return back()->with('success','Página da clínica atualizada.');
 }
 public function services(Request $r){
  $this->authorizeManager($r);$data=$r->validate(['professional_id'=>'required|integer','services'=>'nullable|array|max:200','services.*'=>'integer|distinct']);$p=$this->dentist($r,$data['professional_id']);
  $this->locked($r,function($c)use($data,$p){$ids=$data['services']??[];
   if(Service::where('company_id',$c->id)->where('active',true)->whereIn('id',$ids)->count()!==count($ids))throw ValidationException::withMessages(['services'=>'Selecione apenas procedimentos ativos desta clínica.']);
   DB::table('professional_services')->where('company_id',$c->id)->where('professional_id',$p->id)->delete();
   foreach($ids as $id)DB::table('professional_services')->insert(['company_id'=>$c->id,'professional_id'=>$p->id,'service_id'=>$id]);
  });return back()->with('success','Procedimentos habilitados atualizados.');
 }
 public function period(Request $r){
  $this->authorizeManager($r);$data=$r->validate(['professional_id'=>'required|integer','weekday'=>'required|integer|min:0|max:6','start_time'=>'required|date_format:H:i','end_time'=>'required|date_format:H:i|after:start_time']);$this->dentist($r,$data['professional_id']);
  $this->locked($r,function($c)use($data){
   if(WorkingPeriod::where('company_id',$c->id)->where('professional_id',$data['professional_id'])->where('weekday',$data['weekday'])->where('start_time','<',$data['end_time'])->where('end_time','>',$data['start_time'])->exists())throw ValidationException::withMessages(['start_time'=>'Este período se sobrepõe a outro expediente.']);
   WorkingPeriod::create($data+['company_id'=>$c->id]);
  });return back()->with('success','Expediente adicionado. Atendimentos existentes foram preservados.');
 }
 public function removePeriod(Request $r,int $id){$this->authorizeManager($r);$this->locked($r,fn($c)=>WorkingPeriod::where('company_id',$c->id)->findOrFail($id)->delete());return back()->with('success','Período removido. Os atendimentos já marcados continuam na agenda.');}
 public function block(Request $r){
  $this->authorizeManager($r);$data=$r->validate(['professional_id'=>'nullable|integer','starts_at'=>['required','date_format:Y-m-d\TH:i'],'ends_at'=>['required','date_format:Y-m-d\TH:i','after:starts_at'],'reason'=>'required|string|max:150']);
  if(!empty($data['professional_id']))$this->dentist($r,$data['professional_id']);
  $data['starts_at']=Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s');$data['ends_at']=Carbon::parse($data['ends_at'])->format('Y-m-d H:i:s');
  $this->locked($r,function($c)use($data){
   $q=Appointment::where('company_id',$c->id)->where('status','!=','cancelled')->where('starts_at','<',$data['ends_at'])->where('ends_at','>',$data['starts_at']);
   if(!empty($data['professional_id']))$q->where('professional_id',$data['professional_id']);
   if($q->lockForUpdate()->first())throw ValidationException::withMessages(['starts_at'=>'Há atendimento neste intervalo. Reagende ou cancele antes de bloquear.']);
   ScheduleBlock::create($data+['company_id'=>$c->id]);
  });return back()->with('success','Bloqueio adicionado.');
 }
 public function removeBlock(Request $r,int $id){$this->authorizeManager($r);$this->locked($r,fn($c)=>ScheduleBlock::where('company_id',$c->id)->findOrFail($id)->delete());return back()->with('success','Bloqueio removido.');}
}
