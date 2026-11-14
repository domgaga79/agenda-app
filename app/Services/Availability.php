<?php
namespace App\Services;
use App\Models\{Company,User,Service,WorkingPeriod,ScheduleBlock,Appointment};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
class Availability {
 // Existing appointments store local wall-clock values. Never reinterpret old rows as UTC.
 public function localNow(Company $c): Carbon { return Carbon::parse(now($c->booking_timezone)->format('Y-m-d H:i:s')); }
 public function eligible(Company $c,int $professional,int $service): bool {
  return User::where('company_id',$c->id)->whereKey($professional)->where('role','professional')->where('active',true)->exists()
   && Service::where('company_id',$c->id)->whereKey($service)->where('active',true)->exists()
   && DB::table('professional_services')->where('company_id',$c->id)->where('professional_id',$professional)->where('service_id',$service)->exists();
 }
 public function withinHours(int $company,int $professional,Carbon $start,Carbon $end): bool {
  if(!$start->isSameDay($end)||$end->lte($start))return false;
  return WorkingPeriod::where('company_id',$company)->where('professional_id',$professional)->where('weekday',$start->dayOfWeek)
   ->get()->contains(fn($p)=>$start->gte(Carbon::parse($start->toDateString().' '.$p->start_time)) && $end->lte(Carbon::parse($start->toDateString().' '.$p->end_time)));
 }
 public function blocked(int $company,int $professional,string $start,string $end): bool {
  return ScheduleBlock::where('company_id',$company)->where(fn($q)=>$q->whereNull('professional_id')->orWhere('professional_id',$professional))
   ->where('starts_at','<',$end)->where('ends_at','>',$start)->exists();
 }
 public function slots(Company $c,User $p,Service $service,string $date,bool $locking=false): array {
  if(!$c->active||!$c->booking_enabled||!$this->eligible($c,$p->id,$service->id))return [];
  $day=Carbon::parse($date)->startOfDay();$now=$this->localNow($c);$minimum=$now->copy()->addHours((int)$c->booking_notice_hours);
  if($day->lt($now->copy()->startOfDay())||$day->gt($now->copy()->startOfDay()->addDays((int)$c->booking_window_days)))return [];
  $endDay=$day->copy()->addDay();
  $busyQuery=Appointment::where('company_id',$c->id)->where('professional_id',$p->id)->where('status','!=','cancelled')->where('starts_at','<',$endDay)->where('ends_at','>',$day);
  if($locking)$busyQuery->lockForUpdate();$busy=$busyQuery->get(['starts_at','ends_at']);
  $blocks=ScheduleBlock::where('company_id',$c->id)->where(fn($q)=>$q->whereNull('professional_id')->orWhere('professional_id',$p->id))->where('starts_at','<',$endDay)->where('ends_at','>',$day)->get();
  $slots=[];$duration=(int)$service->duration_minutes;if($duration<5||$duration>1440)return [];
  foreach(WorkingPeriod::where('company_id',$c->id)->where('professional_id',$p->id)->where('weekday',$day->dayOfWeek)->orderBy('start_time')->get() as $period){
   $time=Carbon::parse($date.' '.$period->start_time);$limit=Carbon::parse($date.' '.$period->end_time);
   while($time->copy()->addMinutes($duration)->lte($limit)){
    $end=$time->copy()->addMinutes($duration);
    if($time->gte($minimum) && !$busy->contains(fn($a)=>$a->starts_at->lt($end)&&$a->ends_at->gt($time))
      && !$blocks->contains(fn($b)=>Carbon::parse($b->starts_at)->lt($end)&&Carbon::parse($b->ends_at)->gt($time)))$slots[$time->format('Y-m-d H:i')]=$time->format('H:i');
    $time->addMinutes(15);
   }
  }
  ksort($slots);return $slots;
 }
}
