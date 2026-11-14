<?php
namespace App\Support;
class Odontogram {
 public const STATES=['unassessed'=>'Não avaliado','sound'=>'Sem alteração registrada','caries'=>'Cárie','restored'=>'Restaurado','missing'=>'Ausente','unerupted'=>'Não erupcionado','implant'=>'Implante','other'=>'Outra condição'];
 public const MODES=['permanent'=>'Permanente','primary'=>'Decídua','mixed'=>'Mista'];
 public static function quadrants():array {return [
  ['type'=>'permanent','label'=>'Superior direito','codes'=>range(18,11)],['type'=>'permanent','label'=>'Superior esquerdo','codes'=>range(21,28)],
  ['type'=>'permanent','label'=>'Inferior direito','codes'=>range(48,41)],['type'=>'permanent','label'=>'Inferior esquerdo','codes'=>range(31,38)],
  ['type'=>'primary','label'=>'Superior direito','codes'=>range(55,51)],['type'=>'primary','label'=>'Superior esquerdo','codes'=>range(61,65)],
  ['type'=>'primary','label'=>'Inferior direito','codes'=>range(85,81)],['type'=>'primary','label'=>'Inferior esquerdo','codes'=>range(71,75)]];}
 public static function codes():array{return array_merge(...array_column(self::quadrants(),'codes'));}
 public static function blank():array{return array_fill_keys(self::codes(),['status'=>'unassessed','notes'=>null]);}
 public static function counts(array $teeth):array{$assessed=0;$notes=0;foreach($teeth as $item){if($item['status']!=='unassessed')$assessed++;if(!empty($item['notes']))$notes++;}return ['assessed'=>$assessed,'notes'=>$notes];}
}
