<?php
namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ScheduleReport
{
    public const STATUSES = ['scheduled'=>'Agendados','confirmed'=>'Confirmados','completed'=>'Concluídos','cancelled'=>'Cancelados','no_show'=>'Faltas'];

    public function build(int $company,string $from,string $to,?int $professional,Collection $professionals):array
    {
        // Aggregate in SQL: no patient names, contacts, notes or clinical data are loaded.
        $groups = Appointment::where('company_id',$company)
            ->where('starts_at','>=',$from.' 00:00:00')
            ->where('starts_at','<',Carbon::parse($to)->addDay()->toDateString().' 00:00:00')
            ->when($professional,fn($q)=>$q->where('professional_id',$professional))
            ->selectRaw('DATE(starts_at) AS day, professional_id, status, COUNT(*) AS total')
            ->groupByRaw('DATE(starts_at), professional_id, status')->orderBy('day')->orderBy('professional_id')->get();
        $totals = $this->emptyCounts();
        $days = [];
        $dentists = [];
        $export = [];
        $names = $professionals->pluck('name','id');
        foreach ($groups as $group) {
            if (!array_key_exists($group->status,self::STATUSES)) continue;
            $id = (int)$group->professional_id;
            $day = $group->day;
            $key = $day.'|'.$id;
            $days[$day] ??= $this->emptyCounts();
            $dentists[$id] ??= ['name'=>$names[$id]??('Profissional #'.$id),'counts'=>$this->emptyCounts()];
            $export[$key] ??= ['day'=>$day,'professional'=>$names[$id]??('Profissional #'.$id),'counts'=>$this->emptyCounts()];
            $count = (int)$group->total;
            $totals[$group->status] += $count;
            $days[$day][$group->status] += $count;
            $dentists[$id]['counts'][$group->status] += $count;
            $export[$key]['counts'][$group->status] += $count;
        }
        uasort($dentists,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));
        $finished = $totals['completed'] + $totals['no_show'];
        return ['totals'=>$totals,'days'=>$days,'dentists'=>$dentists,'export'=>array_values($export),
            'total'=>array_sum($totals),'finished'=>$finished,'absenceRate'=>$finished>0 ? round(100*$totals['no_show']/$finished,1) : null];
    }

    private function emptyCounts():array { return array_fill_keys(array_keys(self::STATUSES),0); }

    public function csvText(string $value):string
    {
        // Treat names as text even if a spreadsheet interprets their prefix as a formula.
        return preg_match('/^[\x00-\x20]*[=+@-]/u',$value) ? "'".$value : $value;
    }
}
