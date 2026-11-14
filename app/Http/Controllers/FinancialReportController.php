<?php
namespace App\Http\Controllers;
use App\Models\{FinancialPayment,ExpensePayment};
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class FinancialReportController extends Controller {
 public function index(Request $r){
  abort_unless($r->user()->active&&($r->user()->isOwner()||$r->user()->isReception()),403);
  $r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d']);$today=app(\App\Services\Availability::class)->localNow($r->user()->company);$from=$r->input('from')?:$today->copy()->startOfMonth()->toDateString();$to=$r->input('to')?:$today->copy()->endOfMonth()->toDateString();if($to<$from||\Carbon\Carbon::parse($from)->diffInDays(\Carbon\Carbon::parse($to))>366)throw ValidationException::withMessages(['to'=>'Escolha um período válido de até 367 dias.']);
  $company=$r->user()->company_id;
  $income=FinancialPayment::where('company_id',$company)->whereNull('reversed_at')->whereHas('installment.quote',fn($q)=>$q->where('company_id',$company)->where('status','approved'))->whereDate('paid_on','>=',$from)->whereDate('paid_on','<=',$to);
  $outgoing=ExpensePayment::where('company_id',$company)->whereNull('reversed_at')->whereHas('expense',fn($q)=>$q->where('company_id',$company)->where('status','active'))->whereDate('paid_on','>=',$from)->whereDate('paid_on','<=',$to);
  $received=(int)(clone $income)->sum('amount_cents');$spent=(int)(clone $outgoing)->sum('amount_cents');
  $incomeMethods=(clone $income)->select('method')->selectRaw('SUM(amount_cents) AS total')->groupBy('method')->get()->keyBy('method');$expenseMethods=(clone $outgoing)->select('method')->selectRaw('SUM(amount_cents) AS total')->groupBy('method')->get()->keyBy('method');
  $inDays=(clone $income)->selectRaw('DATE(paid_on) AS day, SUM(amount_cents) AS total')->groupByRaw('DATE(paid_on)')->get()->keyBy('day');$outDays=(clone $outgoing)->selectRaw('DATE(paid_on) AS day, SUM(amount_cents) AS total')->groupByRaw('DATE(paid_on)')->get()->keyBy('day');$days=$inDays->keys()->merge($outDays->keys())->unique()->sort()->values();
  $categories=ExpensePayment::where('expense_payments.company_id',$company)->whereNull('expense_payments.reversed_at')->join('financial_expenses','financial_expenses.id','=','expense_payments.expense_id')->where('financial_expenses.company_id',$company)->where('financial_expenses.status','active')->whereDate('expense_payments.paid_on','>=',$from)->whereDate('expense_payments.paid_on','<=',$to)->select('financial_expenses.category')->selectRaw('SUM(expense_payments.amount_cents) AS total')->groupBy('financial_expenses.category')->orderByDesc('total')->get();
  return response()->view('expenses.report',compact('from','to','received','spent','incomeMethods','expenseMethods','inDays','outDays','days','categories'))->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
 }
}
