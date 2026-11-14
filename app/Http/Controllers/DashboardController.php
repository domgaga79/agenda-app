<?php

namespace App\Http\Controllers;

use App\Models\{Appointment,PatientFollowup,FinancialQuote,FinancialInstallment,FinancialPayment,FinancialExpense,ExpensePayment};
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $company = $user->company_id;
        $now = app(Availability::class)->localNow($user->company);
        $today = $now->toDateString();
        $start = $now->copy()->startOfDay();
        $end = $start->copy()->addDay();
        $administrative = $user->isOwner() || $user->isReception();
        $query = Appointment::where('company_id', $company);
        if ($user->isProfessional()) $query->where('professional_id', $user->id);

        // A half-open interval excludes tomorrow at midnight and follows the clinic's local day.
        $daily = (clone $query)->where('starts_at', '>=', $start)->where('starts_at', '<', $end);
        $statusCounts = (clone $daily)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $appointmentsToday = (int)$statusCounts->except('cancelled')->sum();
        $unconfirmedToday = (int)($statusCounts['scheduled'] ?? 0);
        $completedToday = (int)($statusCounts['completed'] ?? 0);
        $cancelledToday = (int)($statusCounts['cancelled'] ?? 0);
        $todayAppointments = (clone $daily)->where('status', '!=', 'cancelled')->with('professional')->orderBy('starts_at')->orderBy('id')->limit(8)->get();
        $nextAppointments = (clone $query)->whereIn('status', ['scheduled', 'confirmed'])->where('starts_at', '>=', $end)->with('professional')->orderBy('starts_at')->orderBy('id')->limit(6)->get();

        $followupCounts = null;
        $dueFollowups = collect();
        $receivables = null;
        $payables = null;
        if ($administrative) {
            $followups = PatientFollowup::where('company_id', $company)->whereIn('status', ['pending','contacted'])->whereDate('from_date','<=',$today);
            $followupCounts = (clone $followups)->selectRaw('kind, COUNT(*) AS total')->groupBy('kind')->pluck('total','kind');
            $dueFollowups = (clone $followups)->with(['customer','professional'])->orderBy('from_date')->orderBy('id')->limit(5)->get();

            $paid = FinancialPayment::selectRaw('COALESCE(SUM(amount_cents),0)')->where('company_id',$company)->whereColumn('installment_id','financial_installments.id')->whereNull('reversed_at');
            $installments = FinancialInstallment::where('company_id',$company)->whereIn('quote_id',FinancialQuote::where('company_id',$company)->where('status','approved')->select('id'))->whereDate('due_on','<=',$today)->select(['amount_cents','due_on'])->selectSub($paid,'paid_cents');
            $receivables = $this->balances($installments,$today);

            $expensePaid = ExpensePayment::selectRaw('COALESCE(SUM(amount_cents),0)')->where('company_id',$company)->whereColumn('expense_id','financial_expenses.id')->whereNull('reversed_at');
            $expenses = FinancialExpense::where('company_id',$company)->where('status','active')->whereDate('due_on','<=',$today)->select(['amount_cents','due_on'])->selectSub($expensePaid,'paid_cents');
            $payables = $this->balances($expenses,$today);
        }

        return response()->view('dashboard.index',compact('today','administrative','appointmentsToday','unconfirmedToday','completedToday','cancelledToday','todayAppointments','nextAppointments','followupCounts','dueFollowups','receivables','payables'))
            ->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
    }

    private function balances($query,string $today):object
    {
        return DB::query()->fromSub($query->toBase(),'balances')
            ->selectRaw('COALESCE(SUM(amount_cents-paid_cents),0) AS pending')
            ->selectRaw('COALESCE(SUM(CASE WHEN due_on < ? THEN amount_cents-paid_cents ELSE 0 END),0) AS overdue',[$today])->first();
    }
}
