<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class ClinicalAudit extends Model {
 use BelongsToCompany;
 public $timestamps=false;protected $guarded=['id'];
 protected function casts():array{return ['created_at'=>'datetime'];}
 protected static function booted():void {static::updating(fn()=>throw new \LogicException('Auditoria é somente de acréscimo.'));static::deleting(fn()=>throw new \LogicException('Auditoria não pode ser excluída pela aplicação.'));}
 public function actor(){return $this->belongsTo(User::class,'actor_id');}
 public function patient(){return $this->belongsTo(Customer::class,'customer_id');}
 public function target(){return $this->belongsTo(User::class,'target_user_id');}
}
