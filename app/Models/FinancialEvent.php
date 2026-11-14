<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class FinancialEvent extends Model {
 use BelongsToCompany;
 public $timestamps=false;protected $guarded=['id'];
 protected function casts():array{return ['created_at'=>'datetime'];}
 public function actor(){return $this->belongsTo(User::class,'actor_id');}
 protected static function booted():void{static::updating(fn()=>throw new \LogicException('Eventos financeiros não podem ser alterados.'));static::deleting(fn()=>throw new \LogicException('Eventos financeiros não podem ser apagados.'));}
}
