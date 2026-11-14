<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use BelongsToCompany;
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_RECEPTION = 'reception';
    public const ROLE_PROFESSIONAL = 'professional';

    protected $fillable = [
        'clinical_access', 'professional_registration', 'company_id', 'name', 'email', 'phone', 'role', 'password', 'active', 'must_change_password',
    ];

    protected $attributes = ['clinical_access'=>false,'active'=>true,'must_change_password'=>false,'role'=>'professional'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean','clinical_access'=>'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }

    public function canUseClinical(): bool { return $this->isProfessional() && $this->active && $this->clinical_access; }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isReception(): bool
    {
        return $this->role === self::ROLE_RECEPTION;
    }

    public function isProfessional(): bool
    {
        return $this->role === self::ROLE_PROFESSIONAL;
    }
}
