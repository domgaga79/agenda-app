<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class FinancialExpense extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['description'=>'encrypted','supplier'=>'encrypted','cancel_reason'=>'encrypted','amount_cents'=>'integer','revision'=>'integer','due_on'=>'date'];}
 public function payments(){return $this->hasMany(ExpensePayment::class,'expense_id')->orderByDesc('id');}
 public function validPayments(){return $this->hasMany(ExpensePayment::class,'expense_id')->whereNull('reversed_at');}
 public function events(){return $this->hasMany(ExpenseEvent::class,'expense_id')->orderByDesc('id');}
 public function paidCents():int{return (int)$this->payments->whereNull('reversed_at')->sum('amount_cents');}
 public function balanceCents():int{return $this->amount_cents-$this->paidCents();}
}
