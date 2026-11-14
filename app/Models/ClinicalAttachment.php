<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class ClinicalAttachment extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['title'=>'encrypted','original_name'=>'encrypted','description'=>'encrypted','disregard_reason'=>'encrypted','bytes'=>'integer','disregarded_at'=>'datetime'];}
 protected static function booted():void {
  static::deleting(fn()=>throw new \LogicException('Os anexos são preservados. Registre a desconsideração.'));
  static::updating(function($model){foreach(array_keys($model->getDirty()) as $key)if(!in_array($key,['disregarded_at','disregard_reason','updated_at'],true))throw new \LogicException('O conteúdo do anexo é preservado. Envie uma nova versão.');if($model->getOriginal('disregarded_at'))throw new \LogicException('Anexo já desconsiderado.');});
 }
}
