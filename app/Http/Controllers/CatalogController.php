<?php
namespace App\Http\Controllers;
use App\Models\{Customer,Service,Appointment};
use Illuminate\Http\Request;
class CatalogController extends Controller {
 private function kind(Request $r): string { return str_starts_with($r->route()->getName(),'customers.') ? 'customers' : 'services'; }
 private function query(Request $r) {
  abort_unless($r->user()->isOwner() || $r->user()->isReception(),403);
  $class=$this->kind($r)==='customers' ? Customer::class : Service::class;
  return $class::where('company_id',$r->user()->company_id);
 }
 public function index(Request $r) {
  $query=$this->query($r);$r->validate(['q'=>'nullable|string|max:100','state'=>'nullable|in:active,archived,all']);
  if($r->filled('q')) { $term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$r->input('q')).'%'; $query->whereRaw("name LIKE ? ESCAPE '!'",[$term]); }
  if($r->input('state','active')!=='all')$query->where('active',$r->input('state','active')==='active');
  return view('catalog.index',['kind'=>$this->kind($r),'records'=>$query->orderBy('name')->paginate(20)->withQueryString()]);
 }
 public function create(Request $r) { $this->query($r);return view('catalog.form',['kind'=>$this->kind($r),'record'=>null]); }
 public function edit(Request $r,int $id) { return view('catalog.form',['kind'=>$this->kind($r),'record'=>$this->query($r)->findOrFail($id)]); }
 private function data(Request $r): array {
  $rules=['name'=>'required|string|max:150','active'=>'required|boolean'];
  if($this->kind($r)==='customers')$rules+=['phone'=>'nullable|string|max:30','email'=>'nullable|email|max:150','birth_date'=>'nullable|date_format:Y-m-d|before_or_equal:today','guardian_name'=>'nullable|string|max:150','guardian_relationship'=>'nullable|string|max:60','guardian_phone'=>'nullable|string|max:30','address'=>'nullable|string|max:250','emergency_contact'=>'nullable|string|max:150','emergency_phone'=>'nullable|string|max:30'];
  else $rules+=['duration_minutes'=>'required|integer|min:5|max:1440','price'=>'nullable|regex:/^\d{1,6}([.,]\d{1,2})?$/'];
  $data=$r->validate($rules);
  if($this->kind($r)==='services'){
   $price=$data['price'] ?? null; unset($data['price']);
   $parts=explode('.',str_replace(',','.',(string)$price));
   $data['price_cents']=$price===null || $price==='' ? null : ((int)$parts[0]*100+(int)str_pad($parts[1] ?? '',2,'0'));
  }
  return $data;
 }
 public function store(Request $r) {
  $query=$this->query($r);$data=$this->data($r);
  $query->create($data+['company_id'=>$r->user()->company_id]);
  return redirect()->route($this->kind($r).'.index')->with('success','Cadastro criado.');
 }
 public function update(Request $r,int $id) {
  $record=$this->query($r)->findOrFail($id);$record->update($this->data($r));
  return redirect()->route($this->kind($r).'.index')->with('success','Cadastro atualizado.');
 }
 public function show(Request $r,int $id) {
  $record=$this->query($r)->findOrFail($id);
  $appointments=Appointment::where('company_id',$r->user()->company_id)->where('customer_id',$record->id)->with('professional')->orderByDesc('starts_at')->paginate(20);
  return view('catalog.history',compact('record','appointments'));
 }
}
