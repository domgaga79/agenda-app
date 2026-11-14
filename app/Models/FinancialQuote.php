<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class FinancialQuote extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['description'=>'encrypted','cancel_reason'=>'encrypted','gross_cents'=>'integer','discount_cents'=>'integer','total_cents'=>'integer','revision'=>'integer','approved_on'=>'date'];}
 public function customer(){return $this->belongsTo(Customer::class);}
 public function installments(){return $this->hasMany(FinancialInstallment::class,'quote_id')->orderBy('sequence');}
 public function events(){return $this->hasMany(FinancialEvent::class,'quote_id')->orderByDesc('id');}
}
