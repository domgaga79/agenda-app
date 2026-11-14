<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
class WorkingPeriod extends \Illuminate\Database\Eloquent\Model {
 use BelongsToCompany;
 protected $guarded=['id'];
}
