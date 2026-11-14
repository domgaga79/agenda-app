<?php
namespace App\Http\Controllers;
use App\Models\{Company,User,Customer,FinancialQuote as Quote,FinancialInstallment as Installment,FinancialPayment as Payment,FinancialEvent as Event};
use App\Support\{Finance,TreatmentPlan};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class FinanceController extends Controller {
 private function authorize(Request $r):void{abort_unless($r->user()->active&&($r->user()->isOwner()||$r->user()->isReception()),403);}
 private function locked(Request $r):void{Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();$u=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($u->company_id===$r->user()->company_id&&$u->active&&($u->isOwner()||$u->isReception()),403);}
 private function today(Request $r):string{return app(\App\Services\Availability::class)->localNow($r->user()->company)->format('Y-m-d');}
 private function quote(Request $r,int $id):Quote{return Quote::where('company_id',$r->user()->company_id)->findOrFail($id);}
 private function key(Request $r,string $kind,string $uuid):string{return hash('sha256',$kind.'|'.$r->user()->company_id.'|'.$r->user()->id.'|'.$uuid);}
 private function event(Request $r,Quote $q,string $event,?int $amount=null,?int $payment=null):void{Event::create(['company_id'=>$q->company_id,'quote_id'=>$q->id,'actor_id'=>$r->user()->id,'event'=>$event,'amount_cents'=>$amount,'payment_id'=>$payment]);}
 private function view(string $name,array $data){return response()->view('finance.'.$name,$data)->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer');}
 private function fail(string $field,string $text):never{throw ValidationException::withMessages([$field=>$text]);}
 public function index(Request $r){
  $this->authorize($r);$r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from','customer_id'=>'nullable|integer','status'=>['nullable',Rule::in(array_keys(Finance::STATES))]]);
  $today=$this->today($r);$from=$r->input('from')?:substr($today,0,7).'-01';$to=$r->input('to')?:\Carbon\Carbon::parse($today)->endOfMonth()->format('Y-m-d');if($to<$from)$this->fail('to','O fim do período deve ser igual ou posterior ao início.');
  $company=$r->user()->company_id;$customers=Customer::where('company_id',$company)->orderBy('name')->get(['id','name']);
  if($r->filled('customer_id'))Customer::where('company_id',$company)->findOrFail($r->integer('customer_id'));
  $q=Quote::where('company_id',$company);if($r->filled('customer_id'))$q->where('customer_id',$r->integer('customer_id'));
  $quotes=(clone $q)->when($r->filled('status'),fn($query)=>$query->where('status',$r->input('status')))->latest('id')->paginate(15,['*'],'quotes_page')->withQueryString();
  $ids=(clone $q)->where('status','approved')->select('id');
  $due=Installment::where('company_id',$company)->whereIn('quote_id',$ids)->whereDate('due_on','>=',$from)->whereDate('due_on','<=',$to);
  $paidSub=Payment::selectRaw('COALESCE(SUM(amount_cents),0)')->whereColumn('installment_id','financial_installments.id')->whereNull('reversed_at');
  $balances=(clone $due)->select('financial_installments.*')->selectSub($paidSub,'paid_cents');
  // Summaries are computed in SQL, without loading the whole ledger into memory.
  $stats=DB::query()->fromSub($balances->toBase(),'balances')->selectRaw('COALESCE(SUM(amount_cents-paid_cents),0) AS pending')->selectRaw('COALESCE(SUM(CASE WHEN due_on < ? THEN amount_cents-paid_cents ELSE 0 END),0) AS overdue',[$today])->first();
  $installmentIds=Installment::where('company_id',$company)->whereIn('quote_id',$ids)->select('id');
  $received=(int)Payment::where('company_id',$company)->whereIn('installment_id',$installmentIds)->whereNull('reversed_at')->whereDate('paid_on','>=',$from)->whereDate('paid_on','<=',$to)->sum('amount_cents');
  $installments=$due->with(['quote','payments'])->orderBy('due_on')->orderBy('id')->paginate(20,['*'],'dues_page')->withQueryString();
  return $this->view('index',compact('quotes','customers','installments','stats','received','from','to','today'));
 }
 public function create(Request $r){$this->authorize($r);$customers=Customer::where('company_id',$r->user()->company_id)->where('active',true)->orderBy('name')->get();return $this->view('create',compact('customers'));}
 public function store(Request $r){
  $this->authorize($r);$d=$r->validate(['request_id'=>'required|uuid','customer_id'=>'required|integer','description'=>'required|string|max:500','gross'=>['required','regex:/^\d{1,10}([.,]\d{1,2})?$/'],'discount'=>['nullable','regex:/^\d{1,10}([.,]\d{1,2})?$/']]);
  $id=DB::transaction(function()use($r,$d){$this->locked($r);$key=$this->key($r,'quote',$d['request_id']);if($old=Quote::where('request_key',$key)->first()){abort_unless($old->customer_id===(int)$d['customer_id'],409);return $old->id;}
   $c=Customer::where('company_id',$r->user()->company_id)->where('active',true)->findOrFail($d['customer_id']);$gross=TreatmentPlan::cents($d['gross']);$discount=TreatmentPlan::cents($d['discount']??null)??0;if($gross<1||$discount>=$gross)$this->fail('discount','O total após desconto deve ser maior que zero.');
   $q=Quote::create(['company_id'=>$c->company_id,'customer_id'=>$c->id,'created_by'=>$r->user()->id,'customer_name'=>$c->name,'description'=>$d['description'],'gross_cents'=>$gross,'discount_cents'=>$discount,'total_cents'=>$gross-$discount,'request_key'=>$key]);$this->event($r,$q,'created',$q->total_cents);return $q->id;
  },3);return redirect()->route('finance.show',$id)->with('success','Orçamento criado. Confira os valores antes de registrar a aprovação.');
 }
 public function show(Request $r,int $id){$this->authorize($r);$company=(int)$r->user()->company_id;$quote=$this->quote($r,$id)->load(['customer'=>fn($q)=>$q->where('company_id',$company),'installments'=>fn($q)=>$q->where('company_id',$company),'installments.payments'=>fn($q)=>$q->where('company_id',$company),'installments.payments.creator'=>fn($q)=>$q->where('company_id',$company),'events'=>fn($q)=>$q->where('company_id',$company),'events.actor'=>fn($q)=>$q->where('company_id',$company)]);$today=$this->today($r);$paid=$quote->installments->sum(fn($i)=>$i->paidCents());return $this->view('show',compact('quote','today','paid'));}
 public function update(Request $r,int $id){
  $this->authorize($r);$d=$r->validate(['revision'=>'required|integer','description'=>'required|string|max:500','gross'=>['required','regex:/^\d{1,10}([.,]\d{1,2})?$/'],'discount'=>['required','regex:/^\d{1,10}([.,]\d{1,2})?$/']]);
  DB::transaction(function()use($r,$id,$d){$this->locked($r);$q=$this->quote($r,$id);if($q->status!=='draft'||$q->revision!==(int)$d['revision'])$this->fail('revision','O orçamento mudou. Reabra a página antes de editar.');
   $gross=TreatmentPlan::cents($d['gross']);if($q->source_record_id&&$gross!==$q->gross_cents)$this->fail('gross','O valor encaminhado pelo dentista é fixo nesta versão. Ajuste o plano para mudar o valor bruto.');$discount=TreatmentPlan::cents($d['discount']);if($gross<1||$discount>=$gross)$this->fail('discount','O total após desconto deve ser maior que zero.');
   $q->update(['description'=>$d['description'],'gross_cents'=>$gross,'discount_cents'=>$discount,'total_cents'=>$gross-$discount,'revision'=>$q->revision+1]);$this->event($r,$q,'updated',$q->total_cents);
  },3);return back()->with('success','Orçamento revisado.');
 }
 public function approve(Request $r,int $id){
  $this->authorize($r);$d=$r->validate(['confirm'=>'accepted','revision'=>'required|integer','approved_by_name'=>'required|string|max:150','approved_on'=>'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.$this->today($r),'approval_channel'=>['required',Rule::in(array_keys(Finance::CHANNELS))],'deposit'=>['nullable','regex:/^\d{1,10}([.,]\d{1,2})?$/'],'deposit_on'=>'nullable|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2100-01-01','count'=>'required|integer|min:1|max:24','first_due'=>'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:2098-01-01']);
  DB::transaction(function()use($r,$id,$d){$this->locked($r);$q=$this->quote($r,$id);if($q->status!=='draft'||$q->revision!==(int)$d['revision'])$this->fail('revision','O orçamento já foi alterado ou aprovado. Reabra a página.');$customer=Customer::forCompany((int)$q->company_id)->findOrFail($q->customer_id);abort_unless($customer->active,422,'Paciente arquivado. Reative o cadastro antes de aprovar.');
   $deposit=TreatmentPlan::cents($d['deposit']??null)??0;try{$rows=Finance::schedule($q->total_cents,$deposit,(int)$d['count'],$d['first_due'],$d['deposit_on']??null);}catch(\InvalidArgumentException $e){$this->fail('deposit',$e->getMessage());}
   if($deposit>0&&$deposit<$q->total_cents&&$d['deposit_on']>$d['first_due'])$this->fail('deposit_on','A entrada deve vencer até a primeira parcela.');
   foreach($rows as $row)$q->installments()->create($row+['company_id'=>$q->company_id]);$q->update(['status'=>'approved','revision'=>$q->revision+1,'approved_by_name'=>$d['approved_by_name'],'approved_on'=>$d['approved_on'],'approval_channel'=>$d['approval_channel']]);$this->event($r,$q,'approved',$q->total_cents);
  },3);return back()->with('success','Aprovação registrada e parcelas geradas. Registre os pagamentos quando recebidos.');
 }
 public function pay(Request $r,int $id){
  $this->authorize($r);$d=$r->validate(['request_id'=>'required|uuid','amount'=>['required','regex:/^\d{1,10}([.,]\d{1,2})?$/'],'paid_on'=>'required|date_format:Y-m-d|after_or_equal:1900-01-01|before_or_equal:'.$this->today($r),'method'=>['required',Rule::in(array_keys(Finance::METHODS))]]);
  $qid=DB::transaction(function()use($r,$id,$d){$this->locked($r);$i=Installment::where('company_id',$r->user()->company_id)->findOrFail($id);$key=$this->key($r,'payment',$d['request_id']);if($old=Payment::where('request_key',$key)->first()){abort_unless($old->installment_id===$id,409);return $i->quote_id;}
   $q=Quote::forCompany((int)$i->company_id)->findOrFail($i->quote_id);abort_unless($q->status==='approved',422,'Este orçamento não aceita pagamentos.');$amount=TreatmentPlan::cents($d['amount']);if($amount<1||$amount>$i->balanceCents())$this->fail('amount','Informe um valor positivo até o saldo atual da parcela.');
   $p=$i->payments()->create(['company_id'=>$q->company_id,'created_by'=>$r->user()->id,'amount_cents'=>$amount,'paid_on'=>$d['paid_on'],'method'=>$d['method'],'request_key'=>$key]);$this->event($r,$q,'paid',$amount,$p->id);return $q->id;
  },3);return redirect()->route('finance.show',$qid)->with('success','Pagamento registrado. O comprovante interno está disponível no histórico.');
 }
 public function reverse(Request $r,int $id){
  $this->authorize($r);abort_unless($r->user()->isOwner(),403);$d=$r->validate(['reason'=>'required|string|min:5|max:500']);
  $qid=DB::transaction(function()use($r,$id,$d){$this->locked($r);abort_unless(User::findOrFail($r->user()->id)->isOwner(),403);$p=Payment::forCompany((int)$r->user()->company_id)->findOrFail($id);$i=Installment::forCompany((int)$p->company_id)->findOrFail($p->installment_id);$q=Quote::forCompany((int)$p->company_id)->findOrFail($i->quote_id);if(!$p->reversed_at){$p->update(['reversed_at'=>now(),'reversed_by'=>$r->user()->id,'reversal_reason'=>$d['reason']]);$this->event($r,$q,'reversed',$p->amount_cents,$p->id);}return $q->id;},3);
  return redirect()->route('finance.show',$qid)->with('success','Lançamento estornado. O saldo foi reaberto; nenhuma transferência bancária foi realizada.');
 }
 public function cancel(Request $r,int $id){
  $this->authorize($r);$d=$r->validate(['revision'=>'required|integer','reason'=>'required|string|min:5|max:500']);DB::transaction(function()use($r,$id,$d){$this->locked($r);$q=$this->quote($r,$id);if($q->status==='cancelled')return;if($q->revision!==(int)$d['revision'])$this->fail('revision','O orçamento mudou. Reabra a página.');
   if(Payment::whereIn('installment_id',$q->installments()->select('id'))->whereNull('reversed_at')->exists())$this->fail('reason','Há pagamentos válidos. O proprietário precisa conferir e estornar os lançamentos antes do cancelamento.');
   $q->update(['status'=>'cancelled','cancel_reason'=>$d['reason'],'revision'=>$q->revision+1]);$this->event($r,$q,'cancelled',$q->total_cents);
  },3);return back()->with('success','Orçamento cancelado. O histórico foi preservado.');
 }
 public function printQuote(Request $r,int $id){$this->authorize($r);$quote=$this->quote($r,$id)->load('installments.payments');return $this->view('print',compact('quote'));}
 public function receipt(Request $r,int $id){$this->authorize($r);$company=(int)$r->user()->company_id;$payment=Payment::forCompany($company)->with(['installment'=>fn($q)=>$q->where('company_id',$company),'installment.quote'=>fn($q)=>$q->where('company_id',$company),'creator'=>fn($q)=>$q->where('company_id',$company)])->findOrFail($id);abort_unless($payment->installment&&$payment->installment->quote,404);return $this->view('receipt',compact('payment'));}
}
