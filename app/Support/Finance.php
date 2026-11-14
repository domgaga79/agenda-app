<?php
namespace App\Support;
use Carbon\Carbon;
class Finance {
 public const METHODS=['pix'=>'Pix','cash'=>'Dinheiro','credit'=>'Cartão de crédito','debit'=>'Cartão de débito','transfer'=>'Transferência'];
 public const STATES=['draft'=>'Aguardando aprovação','approved'=>'Aprovado','cancelled'=>'Cancelado'];
 public const CHANNELS=['in_person'=>'Presencial','phone'=>'Telefone','message'=>'Mensagem'];
 public const EVENTS=['created'=>'Orçamento criado','exported'=>'Orçamento encaminhado pelo dentista','updated'=>'Orçamento revisado','approved'=>'Aprovação e parcelas registradas','paid'=>'Pagamento registrado','reversed'=>'Pagamento estornado','cancelled'=>'Orçamento cancelado'];
 public static function money(int $cents):string{return 'R$ '.number_format($cents/100,2,',','.');}
 public static function schedule(int $total,int $deposit,int $count,string $first,?string $depositDate):array {
  if($total<1||$deposit<0||$deposit>$total||$count<1||$count>24)throw new \InvalidArgumentException('Valores do parcelamento inválidos.');
  $rows=[];$seq=1;if($deposit>0){if(!$depositDate)throw new \InvalidArgumentException('Informe o vencimento da entrada.');$rows[]=['sequence'=>$seq++,'is_deposit'=>true,'due_on'=>$depositDate,'amount_cents'=>$deposit];}
  $rest=$total-$deposit;if($rest===0)return $rows;
  if($rest<$count)throw new \InvalidArgumentException('Cada parcela deve ter pelo menos R$ 0,01.');
  $base=intdiv($rest,$count);$remainder=$rest%$count;$date=Carbon::createFromFormat('!Y-m-d',$first);
  for($i=0;$i<$count;$i++)$rows[]=['sequence'=>$seq++,'is_deposit'=>false,'due_on'=>$date->copy()->addMonthsNoOverflow($i)->format('Y-m-d'),'amount_cents'=>$base+($i<$remainder?1:0)];
  return $rows;
 }
}
