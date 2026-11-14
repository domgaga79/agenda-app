<?php
namespace App\Http\Controllers;
use App\Models\{Company,CompanySlugReservation,User,Service,Appointment};
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
class PublicBookingController extends Controller {
 private function company(string $slug):Company {$company=Company::where('slug',$slug)->where('active',true)->where('booking_enabled',true)->first();if($company)return $company;$reserved=CompanySlugReservation::where('slug',$slug)->firstOrFail();return Company::whereKey($reserved->company_id)->where('active',true)->where('booking_enabled',true)->firstOrFail();}
 public function index(Request $r,string $slug,Availability $availability){
  $company=$this->company($slug);$r->validate(['service_id'=>'nullable|integer','professional_id'=>'nullable|integer','date'=>'nullable|date_format:Y-m-d']);
  $services=Service::where('company_id',$company->id)->where('active',true)->whereIn('id',DB::table('professional_services')->where('company_id',$company->id)->select('service_id'))->orderBy('name')->get();
  $service=$r->filled('service_id')?$services->firstWhere('id',$r->integer('service_id')):null;if($r->filled('service_id'))abort_unless($service,404);
  $professionals=$service?User::where('company_id',$company->id)->where('active',true)->where('role','professional')->whereIn('id',DB::table('professional_services')->where('company_id',$company->id)->where('service_id',$service->id)->select('professional_id'))->orderBy('name')->get():collect();
  $professional=$r->filled('professional_id')?$professionals->firstWhere('id',$r->integer('professional_id')):null;if($r->filled('professional_id'))abort_unless($professional,404);
  $today=$availability->localNow($company)->startOfDay();$date=$r->input('date',$today->toDateString());$maxDate=$today->copy()->addDays((int)$company->booking_window_days)->toDateString();
  $slots=$service&&$professional?$availability->slots($company,$professional,$service,$date):[];
  $sessionKey='booking_nonce.'.$company->id;
  if(!$r->session()->has($sessionKey))$r->session()->put($sessionKey,bin2hex(random_bytes(24)));
  $nonce=$r->session()->get($sessionKey);
  return response()->view('booking.index',compact('company','services','service','professionals','professional','today','date','maxDate','slots','nonce'))->header('Cache-Control','no-store, private');
 }
 public function store(Request $r,string $slug,Availability $availability){
  $company=$this->company($slug);
  $data=$r->validate(['service_id'=>'required|integer','professional_id'=>'required|integer','starts_at'=>'required|date_format:Y-m-d H:i','client_name'=>'required|string|min:2|max:150','client_phone'=>'required|string|max:30','client_email'=>'nullable|email|max:150','acknowledge'=>'accepted','nonce'=>'required|string|size:48','website'=>'nullable|prohibited']);
  abort_unless(hash_equals((string)$r->session()->get('booking_nonce.'.$company->id,''),$data['nonce']),419);
  $phone=preg_replace('/\D/','',$data['client_phone']);if(strlen($phone)<10||strlen($phone)>15)throw ValidationException::withMessages(['client_phone'=>'Informe um telefone com DDD.']);
  $key=hash('sha256',$company->id.'|'.$data['nonce']);
  $appointment=DB::transaction(function()use($company,$data,$phone,$key,$availability){
   $c=Company::whereKey($company->id)->lockForUpdate()->firstOrFail();abort_unless($c->active&&$c->booking_enabled,404);
   if($existing=Appointment::where('company_id',$c->id)->where('booking_request_key',$key)->first())return $existing;
   $p=User::where('company_id',$c->id)->where('active',true)->where('role','professional')->lockForUpdate()->findOrFail($data['professional_id']);
   $service=Service::where('company_id',$c->id)->where('active',true)->lockForUpdate()->findOrFail($data['service_id']);
   if(!array_key_exists($data['starts_at'],$availability->slots($c,$p,$service,substr($data['starts_at'],0,10),true)))throw ValidationException::withMessages(['starts_at'=>'Este horário não está mais disponível. Consulte os horários novamente.']);
   return Appointment::create(['company_id'=>$c->id,'professional_id'=>$p->id,'created_by'=>null,'source'=>'public','booking_request_key'=>$key,'contact_acknowledged_at'=>now(),
    'client_name'=>$data['client_name'],'client_phone'=>$phone,'client_email'=>$data['client_email']??null,'service_id'=>$service->id,'service'=>$service->name,'price_cents'=>$service->price_cents,
    'starts_at'=>Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s'),'ends_at'=>Carbon::parse($data['starts_at'])->addMinutes((int)$service->duration_minutes)->format('Y-m-d H:i:s'),'status'=>'scheduled']);
  },3);
  $r->session()->put('booking_receipt.'.$company->id,$appointment->id);
  return redirect()->route('booking.done',['slug'=>$slug]);
 }
 public function done(Request $r,string $slug){
  $company=$this->company($slug);$id=$r->session()->get('booking_receipt.'.$company->id);abort_unless($id,404);
  $appointment=Appointment::forCompany((int)$company->id)->where('source','public')->with(['professional'=>fn($q)=>$q->where('company_id',$company->id)])->findOrFail($id);
  // Rotate only after confirmation; concurrent repeated POSTs share the old idempotency key.
  $r->session()->put('booking_nonce.'.$company->id,bin2hex(random_bytes(24)));
  return response()->view('booking.done',compact('company','appointment'))->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
 }
}
