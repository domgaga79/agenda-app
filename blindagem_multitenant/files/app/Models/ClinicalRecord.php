<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class ClinicalRecord extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['payload'=>'encrypted:array','occurred_at'=>'datetime'];}
 protected static function booted():void {static::updating(fn()=>throw new \LogicException('Registros clínicos são preservados; adicione uma nova versão ou adendo.'));static::deleting(fn()=>throw new \LogicException('Registros clínicos não podem ser excluídos pela aplicação.'));}
}
