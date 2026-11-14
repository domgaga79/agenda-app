<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class ExpensePayment extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['amount_cents'=>'integer','paid_on'=>'date','reversed_at'=>'datetime','reversal_reason'=>'encrypted'];}
 public function expense(){return $this->belongsTo(FinancialExpense::class,'expense_id');}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
}
