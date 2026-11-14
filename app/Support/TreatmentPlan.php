<?php
namespace App\Support;
class TreatmentPlan {
 public const STATES=['draft'=>'Rascunho','active'=>'Em andamento','completed'=>'Concluído','cancelled'=>'Cancelado'];
 public const ITEM_STATES=['planned'=>'Planejado','in_progress'=>'Em andamento','completed'=>'Concluído','cancelled'=>'Cancelado'];
 public static function cents(?string $value):?int {
  if($value===null||$value==='')return null;
  $parts=explode('.',str_replace(',','.',$value));return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
 }
 public static function totals(array $items):array {
  $total=0;$completed=0;$pendingPrices=0;$active=0;$done=0;
  foreach($items as $item){if($item['status']==='cancelled')continue;$active++;
   if($item['status']==='completed')$done++;
   if($item['unit_price_cents']===null){$pendingPrices++;continue;}
   $line=$item['unit_price_cents']*$item['quantity'];$total+=$line;if($item['status']==='completed')$completed+=$line;
  }
  return ['total_cents'=>$total,'completed_cents'=>$completed,'unpriced_items'=>$pendingPrices,'active_items'=>$active,'completed_items'=>$done];
 }
}
