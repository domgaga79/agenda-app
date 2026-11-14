<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class FinancialPayment extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['paid_on'=>'date','reversed_at'=>'datetime','amount_cents'=>'integer','reversal_reason'=>'encrypted'];}
 public function installment(){return $this->belongsTo(FinancialInstallment::class,'installment_id');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
}
