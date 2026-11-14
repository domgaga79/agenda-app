<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySlugReservation extends Model
{
    public $timestamps = false;
    protected $fillable = ['company_id','slug','first_seen_at'];
    protected function casts(): array { return ['first_seen_at'=>'datetime']; }
    public function company(){ return $this->belongsTo(Company::class); }
}
