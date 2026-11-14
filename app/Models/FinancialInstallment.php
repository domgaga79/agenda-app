<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class FinancialInstallment extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['due_on'=>'date','amount_cents'=>'integer','is_deposit'=>'boolean'];}
 public function quote(){return $this->belongsTo(FinancialQuote::class,'quote_id');}
 public function payments(){return $this->hasMany(FinancialPayment::class,'installment_id')->orderByDesc('id');}
 public function paidCents():int{return (int)$this->payments->whereNull('reversed_at')->sum('amount_cents');}
 public function balanceCents():int{return $this->amount_cents-$this->paidCents();}
}
