<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $attributes = ['active'=>true,'booking_enabled'=>false,'booking_timezone'=>'America/Bahia','booking_notice_hours'=>2,'booking_window_days'=>60];
    protected $fillable = ['name', 'slug', 'phone', 'email', 'active', 'booking_enabled','booking_timezone','booking_notice_hours','booking_window_days','booking_address','booking_description'];

    protected function casts(): array
    {
        return ['active' => 'boolean','booking_enabled'=>'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
