<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\{Availability,ScheduleReport};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ScheduleReportController extends Controller
{
    private function data(Request $request):array
    {
        $user = $request->user();
        abort_unless($user->active&&($user->isOwner()||$user->isReception()||$user->isProfessional()),403);
        $request->validate(['from'=>'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-01-01',
            'to'=>'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-01-01','professional_id'=>'nullable|integer|min:1']);
        $now = app(Availability::class)->localNow($user->company);
        $from = $request->input('from') ?: $now->copy()->startOfMonth()->toDateString();
        $to = $request->input('to') ?: $now->copy()->endOfMonth()->toDateString();
        if ($to<$from || Carbon::parse($from)->diffInDays(Carbon::parse($to))>366)
            throw ValidationException::withMessages(['to'=>'Selecione um período de até 367 dias, com a data final igual ou posterior à inicial.']);
        $professionals = User::where('company_id',$user->company_id)
            ->where(fn($q)=>$q->where('role','professional')->orWhereHas('appointments',fn($a)=>$a->where('company_id',$user->company_id)))
            ->when($user->isProfessional(),fn($q)=>$q->whereKey($user->id))->orderBy('name')->get(['id','name','active']);
        $professional = $request->filled('professional_id') ? $request->integer('professional_id') : null;
        if ($professional) abort_unless($professionals->contains('id',$professional),404);
        if ($user->isProfessional()) $professional = $user->id;
        $report = app(ScheduleReport::class)->build($user->company_id,$from,$to,$professional,$professionals);
        return compact('from','to','professional','professionals','report');
    }

    public function index(Request $request)
    {
        return response()->view('reports.schedule',$this->data($request))
            ->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
    }

    public function export(Request $request)
    {
        $data = $this->data($request);
        return response()->streamDownload(function()use($data){
            $out = fopen('php://output','w');
            fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,['Data','Dentista',...array_values(ScheduleReport::STATUSES),'Total'],';','"','');
            foreach($data['report']['export'] as $row) {
                fputcsv($out,[Carbon::parse($row['day'])->format('d/m/Y'),app(ScheduleReport::class)->csvText($row['professional']),...array_values($row['counts']),array_sum($row['counts'])],';','"','');
            }
            fclose($out);
        },'agenda-resumo-'.$data['from'].'-a-'.$data['to'].'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'no-store, private','Referrer-Policy'=>'no-referrer','X-Content-Type-Options'=>'nosniff']);
    }
}
