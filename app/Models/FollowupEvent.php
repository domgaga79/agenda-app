<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class FollowupEvent extends Model {
 use BelongsToCompany;
 public $timestamps=false;protected $guarded=['id'];
 protected function casts():array{return ['payload'=>'encrypted:array','created_at'=>'datetime'];}
 public function actor(){return $this->belongsTo(User::class,'actor_id');}
 protected static function booted():void{static::updating(fn()=>throw new \LogicException('Histórico não pode ser alterado.'));static::deleting(fn()=>throw new \LogicException('Histórico não pode ser apagado.'));}
}
