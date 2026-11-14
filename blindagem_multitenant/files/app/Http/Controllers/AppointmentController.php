<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;

class AppointmentController extends Controller
{
    private function scoped(Request $request)
    {
        $q = Appointment::query()->where('company_id', $request->user()->company_id);
        if ($request->user()->isProfessional()) $q->where('professional_id', $request->user()->id);
        return $q;
    }

    private function professionals(Request $request)
    {
        if ($request->user()->isProfessional()) return User::whereKey($request->user()->id)->get();
        return User::where('company_id', $request->user()->company_id)->where('role', User::ROLE_PROFESSIONAL)->where('active', true)->orderBy('name')->get();
    }

    public function index(Request $request)
    {
        $request->validate(['date'=>['nullable','date_format:Y-m-d'],'view'=>['nullable','in:day,week,month'],'professional'=>['nullable','integer','min:1']]);
        $date=$request->filled('date') ? Carbon::createFromFormat('Y-m-d',$request->input('date'))->startOfDay() : now()->startOfDay();
        $mode=$request->input('view','week');
        $rangeStart=$mode==='week' ? $date->copy()->startOfWeek(Carbon::MONDAY) : $date->copy();
        $count=$mode==='week' ? 7 : 1;
        if($mode==='month'){
            $rangeStart=$date->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $rangeEnd=$date->copy()->endOfMonth()->startOfDay()->endOfWeek(Carbon::SUNDAY)->addDay()->startOfDay();
            $count=(int)$rangeStart->diffInDays($rangeEnd);
        }
        $professionals=$this->professionals($request);
        $company=(int)$request->user()->company_id;$query=$this->scoped($request)->with(['professional'=>fn($q)=>$q->where('company_id',$company),'latestConfirmation'=>fn($q)=>$q->where('company_id',$company)])->where('starts_at','<',$rangeStart->copy()->addDays($count))->where('ends_at','>',$rangeStart);
        if($request->filled('professional')){
            abort_unless($professionals->contains('id',(int)$request->input('professional')),404);
            $query->where('professional_id',$request->integer('professional'));
        }
        $appointments=$query->orderBy('starts_at')->get();
        $blocksQuery=\App\Models\ScheduleBlock::where('company_id',$request->user()->company_id)
            ->where('starts_at','<',$rangeStart->copy()->addDays($count))->where('ends_at','>',$rangeStart);
        $filterProfessional=$request->user()->isProfessional() ? $request->user()->id : $request->integer('professional');
        if($filterProfessional)$blocksQuery->where(fn($q)=>$q->whereNull('professional_id')->orWhere('professional_id',$filterProfessional));
        $blocks=$blocksQuery->orderBy('starts_at')->get();
        return view('appointments.index',compact('blocks','date','mode','rangeStart','appointments','professionals')+\App\Support\CalendarLayout::build($appointments,$rangeStart,$count));
    }

    public function create(Request $request) {
        $request->validate(['date'=>['nullable','date_format:Y-m-d'],'time'=>['nullable','date_format:H:i'],'followup_id'=>'nullable|integer|min:1']);
        $followup=null;$appointment=null;
        if($request->filled('followup_id')){
            $service=app(\App\Services\FollowupScheduling::class);
            $followup=$service->find($request,$request->integer('followup_id'));
            $service->schedulable($followup);
            $appointment=new Appointment(['customer_id'=>$followup->customer_id,'professional_id'=>$followup->professional_id,'service_id'=>$followup->service_id,'client_name'=>$followup->customer->name,'client_phone'=>$followup->customer->phone,'client_email'=>$followup->customer->email,'service'=>$followup->service?->name,'status'=>'scheduled']);
        }
        $initialStart=$request->filled('date') ? $request->input('date').'T'.$request->input('time','09:00') : null;
        $initialEnd=$initialStart ? Carbon::parse($initialStart)->addMinutes(30)->format('Y-m-d\TH:i') : null;
        return view('appointments.create',['followup'=>$followup,'appointment'=>$appointment,'professionals'=>$this->professionals($request),'initialStart'=>$initialStart,'initialEnd'=>$initialEnd]+$this->catalogs($request));
    }

    public function store(Request $request)
    {
        $request->validate(['followup_id'=>'nullable|integer|min:1','followup_revision'=>'required_with:followup_id|nullable|integer|min:1']);
        $data = $this->validated($request);
        DB::transaction(function () use ($request,$data) {
            $this->lockCompany($request);
            $this->authorizeProfessional($request,(int)$data['professional_id']);
            $data=$this->linkCatalogs($request,$data);
            $this->ensureNoConflict($request,$data);
            $appointment=Appointment::create($data + ['company_id'=>$request->user()->company_id,'created_by'=>$request->user()->id]);
            app(\App\Services\FollowupScheduling::class)->book($request,$appointment);
        },3);
        return redirect()->route('appointments.index', ['date' => substr($data['starts_at'],0,10)])->with('success', 'Agendamento criado.');
    }

    public function edit(Request $request, int $id)
    {
        $appointment = $this->scoped($request)->findOrFail($id);
        return view('appointments.edit', ['appointment' => $appointment, 'professionals' => $this->professionals($request)] + $this->catalogs($request,$appointment));
    }

    public function update(Request $request, int $id)
    {
        $this->scoped($request)->findOrFail($id);
        $data=$this->validated($request);
        DB::transaction(function () use ($request,$data,$id) {
            $this->lockCompany($request);
            $appointment=$this->scoped($request)->lockForUpdate()->findOrFail($id);
            $this->authorizeProfessional($request,(int)$data['professional_id']);
            $data=$this->linkCatalogs($request,$data,$appointment);
            $this->ensureNoConflict($request,$data,$appointment->id);
            $appointment->fill($data);
            $scheduleChanged=$appointment->isDirty(['starts_at','ends_at','professional_id','customer_id','client_name','client_phone','client_email','service_id','service']);
            if($scheduleChanged && $appointment->getOriginal('status')==='confirmed' && $appointment->status==='confirmed')$appointment->status='scheduled';
            if($scheduleChanged || $appointment->isDirty('status'))app(\App\Services\AppointmentReminder::class)->invalidate($appointment);
            $appointment->save();
            app(\App\Services\FollowupScheduling::class)->release($appointment,$request->user()->id,'Agendamento cancelado ou paciente alterado pela equipe.');
        },3);
        return redirect()->route('appointments.index', ['date' => substr($data['starts_at'],0,10)])->with('success', 'Agendamento atualizado.');
    }

    public function destroy(Request $request, int $id)
    {
        DB::transaction(function () use ($request,$id) {
            $this->lockCompany($request);
            $appointment=$this->scoped($request)->lockForUpdate()->findOrFail($id);
            app(\App\Services\FollowupScheduling::class)->release($appointment,$request->user()->id,'Agendamento excluído pela equipe.',true);
            $appointment->delete();
        },3);
        return redirect()->route('appointments.index')->with('success', 'Agendamento excluído.');
    }


    private function catalogs(Request $r, ?Appointment $a=null): array {
        $can=$r->user()->isOwner() || $r->user()->isReception();
        return ['catalogAccess'=>$can,
            'customers'=>$can ? Customer::where('company_id',$r->user()->company_id)->where(function($q)use($a){$q->where('active',true);if($a?->customer_id)$q->orWhere('id',$a->customer_id);})->orderBy('name')->get() : collect(),
            'services'=>$can ? Service::where('company_id',$r->user()->company_id)->where(function($q)use($a){$q->where('active',true);if($a?->service_id)$q->orWhere('id',$a->service_id);})->orderBy('name')->get() : collect()];
    }
    private function linkCatalogs(Request $r, array $data, ?Appointment $a=null): array {
        $can=$r->user()->isOwner() || $r->user()->isReception();
        foreach(['customer_id'=>Customer::class,'service_id'=>Service::class] as $field=>$class){
            if(!$can){
                abort_if($r->filled($field) && (int)$r->input($field)!==(int)$a?->$field,403);
                unset($data[$field]); continue;
            }
            $id=$data[$field] ?? null;
            if(!$r->exists($field) && $a)continue;
            $data[$field]=$id;
            if(!$id){if($field==='service_id')$data['price_cents']=null;continue;}
            $item=$class::where('company_id',$r->user()->company_id)->whereKey($id)->firstOrFail();
            $same=$a && (int)$a->$field===(int)$id;
            if(!$item->active && !$same)throw ValidationException::withMessages([$field=>'Este cadastro está arquivado.']);
            if($field==='customer_id'){
                $data['client_name']=$same ? $a->client_name : $item->name;
                $data['client_phone']=$same ? $a->client_phone : $item->phone;
                $data['client_email']=$same ? $a->client_email : $item->email;
            }else{
                $data['service']=$same ? $a->service : $item->name;
                $data['price_cents']=$same ? $a->price_cents : $item->price_cents;
            }
        }
        return $data;
    }

    private function lockCompany(Request $request): void
    {
        // All schedule writes lock the SAME persistent row, including an empty calendar.
        // Requires InnoDB on MariaDB/MySQL. Avoids the check-then-insert race.
        Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'customer_id'=>['nullable','integer','min:1'], 'service_id'=>['nullable','integer','min:1'],
            'professional_id' => ['required','integer'], 'client_name' => ['required_without:customer_id','nullable','string','max:150'],
            'client_phone' => ['nullable','string','max:30'], 'client_email' => ['nullable','email','max:150'],
            'service' => ['required_without:service_id','nullable','string','max:150'], 'starts_at' => ['required','date','regex:/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/'], 'ends_at' => ['required','date','after:starts_at','regex:/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/'],
            'status' => ['required', Rule::in(['scheduled','confirmed','completed','cancelled','no_show'])], 'notes' => ['nullable','string','max:2000'],
        ]);
        $data['starts_at']=Carbon::parse($data['starts_at'])->format('Y-m-d H:i:s');
        $data['ends_at']=Carbon::parse($data['ends_at'])->format('Y-m-d H:i:s');
        return $data;
    }

    private function authorizeProfessional(Request $request, int $professionalId): void
    {
        $professional = User::where('company_id', $request->user()->company_id)->whereKey($professionalId)->where('role', User::ROLE_PROFESSIONAL)->where('active', true)->firstOrFail();
        if ($request->user()->isProfessional()) abort_unless($professional->id === $request->user()->id, 403);
    }

    private function ensureNoConflict(Request $request, array $data, ?int $ignoreId = null): void
    {
        if ($data['status'] === 'cancelled') return;
        $availability = app(\App\Services\Availability::class);
        $companyId = $request->user()->company_id;
        $previous = $ignoreId ? Appointment::where('company_id',$companyId)->findOrFail($ignoreId) : null;
        // Updating contact/status must not invalidate an unchanged existing reservation.
        $unchanged = $previous && $previous->status !== 'cancelled'
            && (int)$previous->professional_id === (int)$data['professional_id']
            && $previous->starts_at->format('Y-m-d H:i:s') === $data['starts_at']
            && $previous->ends_at->format('Y-m-d H:i:s') === $data['ends_at'];
        if (!$unchanged) {
            if ($availability->blocked($companyId,(int)$data['professional_id'],$data['starts_at'],$data['ends_at']))
                throw ValidationException::withMessages(['starts_at'=>'Este intervalo está bloqueado na disponibilidade.']);
            // Existing installations remain usable until a weekly schedule is configured.
            if (\App\Models\WorkingPeriod::where('company_id',$companyId)->where('professional_id',$data['professional_id'])->exists()
                && !$availability->withinHours($companyId,(int)$data['professional_id'],Carbon::parse($data['starts_at']),Carbon::parse($data['ends_at'])))
                throw ValidationException::withMessages(['starts_at'=>'O atendimento deve caber em um período do expediente do dentista.']);
        }
        $q = Appointment::where('company_id', $request->user()->company_id)
            ->where('professional_id', $data['professional_id'])->where('status', '!=', 'cancelled')
            ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at']);
        if ($ignoreId) $q->whereKeyNot($ignoreId);
        if ($q->lockForUpdate()->first(['id'])) throw ValidationException::withMessages(['starts_at' => 'Já existe um atendimento para este profissional nesse horário.']);
    }
}
