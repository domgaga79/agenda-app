<?php
namespace App\Http\Controllers;
use App\Models\{User,Appointment};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
class DailyScheduleController extends Controller
{
 public const STATUSES=['active'=>'Todos, exceto cancelados','all'=>'Todos, incluindo cancelados','scheduled'=>'Agendados','confirmed'=>'Confirmados','completed'=>'Concluídos','cancelled'=>'Cancelados','no_show'=>'Faltas'];
 public function index(Request $request)
 {
  $user=$request->user();abort_unless($user->active&&($user->isOwner()||$user->isReception()||$user->isProfessional()),403);
  $request->validate(['date'=>'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-01-01','professional_id'=>'nullable|integer|min:1','status'=>['nullable',Rule::in(array_keys(self::STATUSES))],'page'=>'nullable|integer|min:1|max:100000']);
  $company=$user->company;$date=$request->input('date')?:app(\App\Services\Availability::class)->localNow($company)->toDateString();$status=$request->input('status')?:'active';
  $professionals=User::where('company_id',$company->id)->where(fn($q)=>$q->where('role','professional')->orWhereHas('appointments',fn($a)=>$a->where('company_id',$company->id)))->when($user->isProfessional(),fn($q)=>$q->whereKey($user->id))->orderBy('name')->get(['id','name','active']);
  $professional=$request->filled('professional_id')?$request->integer('professional_id'):null;
  if($professional)abort_unless($professionals->contains('id',$professional),404);
  if($user->isProfessional())$professional=$user->id;
  $query=Appointment::where('company_id',$company->id)->where('starts_at','>=',$date.' 00:00:00')->where('starts_at','<',Carbon::parse($date)->addDay()->toDateString().' 00:00:00')->when($professional,fn($q)=>$q->where('professional_id',$professional));
  if($status==='active')$query->where('status','!=','cancelled');elseif($status!=='all')$query->where('status',$status);
  $appointments=$query->select(['id','professional_id','client_name','service','starts_at','ends_at','status'])->with('professional:id,name')->orderBy('starts_at')->orderBy('id')->paginate(50)->withQueryString();
  abort_if($appointments->currentPage()>$appointments->lastPage(),404);
  $generatedAt=now()->timezone($company->booking_timezone);$statuses=self::STATUSES;
  return response()->view('appointments.daily-list',compact('company','date','status','professional','professionals','appointments','generatedAt','statuses'))->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex, nofollow')->header('X-Frame-Options','DENY');
 }
}
