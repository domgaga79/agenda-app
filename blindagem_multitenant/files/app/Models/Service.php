<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class Service extends Model {
 use BelongsToCompany;
 protected $fillable=['company_id','name','duration_minutes','price_cents','active'];
 protected $attributes=['active'=>true];
 protected function casts(): array { return ['active'=>'boolean']; }
}
