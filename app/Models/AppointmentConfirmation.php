<?php
namespace App\Models;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
class AppointmentConfirmation extends Model {
 use BelongsToCompany;
 protected $guarded=['id'];protected $hidden=['token','token_hash','snapshot','request_key'];
 protected function casts():array{return ['token'=>'encrypted','expires_at'=>'datetime','revoked_at'=>'datetime','responded_at'=>'datetime','sent_at'=>'datetime'];}
 public function appointment(){return $this->belongsTo(Appointment::class);}
 public function creator(){return $this->belongsTo(User::class,'created_by');}
 public function sender(){return $this->belongsTo(User::class,'sent_by');}
}
