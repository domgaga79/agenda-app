<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
class ScheduleBlock extends \Illuminate\Database\Eloquent\Model {
 use BelongsToCompany;
 protected $guarded=['id'];
}
