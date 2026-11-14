<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class Customer extends Model {
 use BelongsToCompany;
 protected $fillable=['company_id','name','phone','email','active','birth_date','guardian_name','guardian_relationship','guardian_phone','address','emergency_contact','emergency_phone'];
 protected $attributes=['active'=>true];
 protected function casts(): array { return ['active'=>'boolean','birth_date'=>'date']; }
}
