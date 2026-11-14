<?php
namespace App\Services;
use App\Models\{Appointment,AppointmentConfirmation};
use Illuminate\Support\Carbon;
class AppointmentReminder {
 public function snapshot(Appointment $a):string {return hash('sha256',json_encode([$a->company_id,$a->professional_id,$a->customer_id,$a->client_name,$a->client_phone,$a->client_email,$a->service_id,$a->service,$a->starts_at->format('Y-m-d H:i:s'),$a->ends_at->format('Y-m-d H:i:s'),$a->company->booking_timezone],JSON_THROW_ON_ERROR));}
 public function deadline(Appointment $a):Carbon{return Carbon::parse($a->starts_at->format('Y-m-d H:i:s'),$a->company->booking_timezone)->utc();}
 public function eligible(Appointment $a):bool{return $a->company->active&&$a->professional?->active&&in_array($a->status,['scheduled','confirmed'],true)&&$this->deadline($a)->gt(now());}
 public function valid(AppointmentConfirmation $link,Appointment $a):bool{return !$link->revoked_at&&$link->expires_at->gt(now())&&$a->company->active&&$a->professional?->active&&hash_equals($link->snapshot,$this->snapshot($a));}
 public function phone(?string $value):?string {
  $value=trim($value??'');if($value===''||!preg_match('/^\+?[\d\s().-]+$/',$value))return null;$digits=preg_replace('/\D/','',$value);
  if(str_starts_with($value,'+'))return preg_match('/^[1-9]\d{7,14}$/',$digits)?$digits:null;
  if(preg_match('/^[1-9]\d{9,10}$/',$digits))return '55'.$digits;
  return preg_match('/^55[1-9]\d{9,10}$/',$digits)?$digits:null;
 }
 public function message(Appointment $a,AppointmentConfirmation $link):string{return 'Olá! Você tem um agendamento em '.$a->company->name.' no dia '.$a->starts_at->format('d/m/Y').' às '.$a->starts_at->format('H:i').'. Horário local da clínica ('.$a->company->booking_timezone.'). Confirme ou cancele pelo link: '.route('confirmation.show',$link->token).' Se precisar remarcar, fale com a clínica. Não encaminhe este link a outras pessoas.';}
 public function invalidate(Appointment $a):void {AppointmentConfirmation::where('company_id',$a->company_id)->where('appointment_id',$a->id)->whereNull('revoked_at')->update(['revoked_at'=>now()]);}
}
