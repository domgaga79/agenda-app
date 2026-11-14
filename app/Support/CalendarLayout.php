<?php
namespace App\Support;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
class CalendarLayout {
 public static function build(Collection $appointments, Carbon $start, int $count): array {
  $days=[];$firstHour=7;$lastHour=20;
  for($i=0;$i<$count;$i++){
   $day=$start->copy()->addDays($i);$end=$day->copy()->addDay();$events=[];
   foreach($appointments as $a){
    if($a->starts_at->gte($end)||$a->ends_at->lte($day))continue;
    $from=$a->starts_at->lt($day)?0:(int)$day->diffInMinutes($a->starts_at);
    $to=$a->ends_at->gt($end)?1440:(int)$day->diffInMinutes($a->ends_at);
    $events[]=['appointment'=>$a,'from'=>$from,'to'=>$to,'visualEnd'=>min(1440,max($from+28,$to))];
    $firstHour=min($firstHour,(int)floor($from/60));$lastHour=max($lastHour,(int)ceil($to/60));
   }
   usort($events,fn($a,$b)=>[$a['from'],$a['to'],$a['appointment']->id]<=>[$b['from'],$b['to'],$b['appointment']->id]);
   $groups=[];$group=[];$until=-1;
   foreach($events as $e){
    if($group && $e['from'] >= $until){$groups[]=$group;$group=[];$until=-1;}
    $group[]=$e;$until=max($until,$e['visualEnd']);
   }
   if($group)$groups[]=$group;
   $placed=[];
   foreach($groups as $items){
    $lanes=[];$batch=[];
    foreach($items as $e){$lane=0;while(isset($lanes[$lane]) && $lanes[$lane]>$e['from'])$lane++;$lanes[$lane]=$e['visualEnd'];$e['lane']=$lane;$batch[]=$e;}
    foreach($batch as $e){$e['lanes']=count($lanes);$placed[]=$e;}
   }
   $days[]=['date'=>$day,'events'=>$placed];
  }
  return ['calendarDays'=>$days,'firstHour'=>$firstHour,'lastHour'=>$lastHour];
 }
}
