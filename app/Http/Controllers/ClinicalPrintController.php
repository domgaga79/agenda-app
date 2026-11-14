<?php
namespace App\Http\Controllers;
use App\Models\ClinicalRecord;
use Illuminate\Http\Request;
class ClinicalPrintController extends ClinicalController
{
    public function preview(Request $request, int $id)
    {
        $patient = $this->patient($request, $id);
        $filters = $request->validate([
            'from'=>['nullable','date_format:Y-m-d'],
            'to'=>['nullable','date_format:Y-m-d','before_or_equal:9999-12-30', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            'page'=>['nullable','integer','min:1','max:100000'],
        ]);
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $query = ClinicalRecord::where('company_id', $patient->company_id)->where('customer_id', $patient->id);
        if ($from) $query->where('occurred_at','>=',$from.' 00:00:00');
        if ($to) $query->where('occurred_at','<',\Illuminate\Support\Carbon::parse($to)->addDay()->format('Y-m-d').' 00:00:00');
        $records = $query->orderBy('occurred_at')->orderBy('id')->paginate(25)->withQueryString();
        abort_if($records->currentPage() > $records->lastPage(),404);
        $questions = self::QUESTIONS;
        $company = $request->user()->company;
        $generatedAt = now()->timezone($company->booking_timezone);
        $this->audit($request,'view_clinical_print',$id);
        return $this->response('clinical.print',compact('patient','records','questions','company','generatedAt','from','to'))
            ->header('X-Content-Type-Options','nosniff')
            ->header('X-Frame-Options','DENY')
            ->header('X-Robots-Tag','noindex, nofollow');
    }
}
