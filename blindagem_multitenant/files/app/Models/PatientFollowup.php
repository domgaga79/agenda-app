<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class PatientFollowup extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];
 protected function casts():array{return ['from_date'=>'date','to_date'=>'date','last_contact_at'=>'datetime','note'=>'encrypted','revision'=>'integer'];}
 public function customer(){return $this->belongsTo(Customer::class);}
 public function professional(){return $this->belongsTo(User::class,'professional_id');}
 public function service(){return $this->belongsTo(Service::class);}
 public function appointment(){return $this->belongsTo(Appointment::class);}
 public function events(){return $this->hasMany(FollowupEvent::class,'followup_id')->orderByDesc('id');}
 public function isOpen():bool{return in_array($this->status,['pending','contacted'],true);}
}
