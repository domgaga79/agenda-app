<?php

namespace App\Models;
use App\Models\Concerns\BelongsToCompany;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
 use BelongsToCompany;
    protected $fillable = [
        'source','booking_request_key','contact_acknowledged_at','company_id', 'professional_id', 'created_by', 'client_name', 'client_phone', 'client_email',
        'customer_id', 'service_id', 'price_cents', 'service', 'starts_at', 'ends_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function latestConfirmation(){return $this->hasOne(AppointmentConfirmation::class)->latestOfMany();}
    public function responseLabel():string {
        $link=$this->latestConfirmation;
        if($link && !$link->revoked_at && $link->response===$this->status){
            if($link->response==='confirmed')return 'Confirmado pelo paciente';
            if($link->response==='cancelled')return 'Cancelado pelo paciente';
        }
        return ['scheduled'=>'Aguardando confirmação','confirmed'=>'Confirmado','cancelled'=>'Cancelado','completed'=>'Concluído','no_show'=>'Faltou'][$this->status]??$this->status;
    }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function professional(): BelongsTo { return $this->belongsTo(User::class, 'professional_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
