<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class ReceivablesController extends Controller
{
 public function index(Request $request)
 {
  abort_unless($request->user()->active&&($request->user()->isOwner()||$request->user()->isReception()),403);
  $filters=$request->validate(['q'=>'nullable|string|max:100','from'=>'nullable|date_format:Y-m-d','to'=>['nullable','date_format:Y-m-d',...($request->filled('from')?['after_or_equal:from']:[])],'status'=>['nullable',Rule::in(['all','overdue','today','future'])],'partial'=>'nullable|boolean']);
  $company=$request->user()->company_id;
  $today=app(\App\Services\Availability::class)->localNow($request->user()->company)->format('Y-m-d');
  $status=$filters['status']??'all';$from=$filters['from']??null;$to=$filters['to']??null;$search=$filters['q']??'';
  $paid=DB::table('financial_payments')->selectRaw('COALESCE(SUM(amount_cents),0)')->whereColumn('installment_id','i.id')->where('company_id',$company)->whereNull('reversed_at');
  $base=DB::table('financial_installments as i')->join('financial_quotes as q','q.id','=','i.quote_id')->where('i.company_id',$company)->where('q.company_id',$company)->where('q.status','approved')->select(['i.id','i.quote_id','i.sequence','i.is_deposit','i.amount_cents','q.customer_name'])->selectRaw('DATE(i.due_on) AS due_on')->selectSub($paid,'paid_cents');
  if($search!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$base->whereRaw("q.customer_name LIKE ? ESCAPE '!'",[$term]);}
  if($from)$base->whereDate('i.due_on','>=',$from);
  if($to)$base->whereDate('i.due_on','<=',$to);
  $query=DB::query()->fromSub($base,'balances')->whereColumn('amount_cents','>','paid_cents');
  if($request->boolean('partial'))$query->where('paid_cents','>',0);
  if($status==='overdue')$query->where('due_on','<',$today);
  if($status==='today')$query->where('due_on','=',$today);
  if($status==='future')$query->where('due_on','>',$today);
  $stats=(clone $query)->selectRaw('COUNT(*) AS count, COALESCE(SUM(amount_cents-paid_cents),0) AS balance')->selectRaw('COALESCE(SUM(CASE WHEN due_on < ? THEN amount_cents-paid_cents ELSE 0 END),0) AS overdue',[$today])->selectRaw('COALESCE(SUM(CASE WHEN due_on = ? THEN amount_cents-paid_cents ELSE 0 END),0) AS due_today',[$today])->first();
  $installments=$query->orderBy('due_on')->orderBy('id')->paginate(25)->withQueryString();
  return response()->view('finance.receivables',compact('installments','stats','today','status','from','to','search'))->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');
 }
}
