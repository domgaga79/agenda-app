<?php
namespace App\Http\Controllers;
use App\Models\{ClinicalAttachment as Attachment,ClinicalAudit,Company,User};
use App\Services\ClinicalAttachmentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ClinicalAttachmentController extends ClinicalController {
 private function lockedPatient(Request $r,int $id){Company::whereKey($r->user()->company_id)->lockForUpdate()->firstOrFail();$fresh=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();abort_unless($fresh->canUseClinical()&&$fresh->company_id===$r->user()->company_id,403);return $this->patient($r,$id);}
 private function attachment(Request $r,int $patient,int $id):Attachment{return Attachment::where('company_id',$r->user()->company_id)->where('customer_id',$patient)->findOrFail($id);}
 private function log(Request $r,string $event,int $patient,?int $attachment=null):void{ClinicalAudit::create(['company_id'=>$r->user()->company_id,'actor_id'=>$r->user()->id,'customer_id'=>$patient,'attachment_id'=>$attachment,'event'=>$event]);}
 public function listing(Request $r,int $id){$patient=$this->patient($r,$id);$attachments=Attachment::where('company_id',$patient->company_id)->where('customer_id',$id)->latest('id')->paginate(15);$this->log($r,'view_attachments',$id);return $this->response('attachments.index',compact('patient','attachments'));}
 public function store(Request $r,int $id){
  $patient=$this->patient($r,$id);abort_unless($patient->active,422,'Paciente arquivado. Reative o cadastro antes de enviar arquivos.');
  $d=$r->validate(['request_id'=>'required|uuid','clinical.title'=>'required|string|max:150','clinical.description'=>'nullable|string|max:1000','file'=>'required|file|max:2048|mimetypes:application/pdf,image/jpeg,image/png|extensions:pdf,jpg,jpeg,png','acknowledge'=>'accepted']);
  $file=$r->file('file');$contents=$file->getContent();$hash=hash('sha256',$contents);$key=hash('sha256','attachment|'.$r->user()->company_id.'|'.$r->user()->id.'|'.$d['request_id']);
  $name=mb_substr(preg_replace('/[\x00-\x1F\x7F]/u','',basename(str_replace('\\','/',$file->getClientOriginalName()))),0,190);
  $storage=app(ClinicalAttachmentStorage::class);$newPath=null;$kept=false;
  try{
   // Do not retry a transaction containing a filesystem write. A retry must be a new HTTP request.
   DB::transaction(function()use($r,$id,$d,$contents,$hash,$key,$file,$name,$storage,&$newPath,&$kept){
    $patient=$this->lockedPatient($r,$id);abort_unless($patient->active,422);
    if($existing=Attachment::where('request_key',$key)->first()){abort_unless($existing->customer_id===$id&&hash_equals($existing->sha256,$hash),409,'Este envio já foi usado para outro arquivo. Reabra a página.');return;}
    $newPath=$storage->write($patient->company_id,$contents);
    $attachment=Attachment::create(['company_id'=>$patient->company_id,'customer_id'=>$id,'author_id'=>$r->user()->id,'author_name'=>$r->user()->name,'author_registration'=>$r->user()->professional_registration??'','title'=>$d['clinical']['title'],'description'=>$d['clinical']['description']??null,'original_name'=>$name,'mime'=>$file->getMimeType(),'bytes'=>strlen($contents),'sha256'=>$hash,'path'=>$newPath,'request_key'=>$key]);
    $this->log($r,'create_attachment',$id,$attachment->id);
   });
   $kept=true;
  }finally{if(!$kept&&$newPath)$storage->removeUncommitted($newPath);}
  return redirect()->route('attachments.index',$id)->with('success','Anexo salvo no prontuário. O arquivo original foi preservado.');
 }
 public function download(Request $r,int $id,int $attachment){
  $result=DB::transaction(function()use($r,$id,$attachment){$this->lockedPatient($r,$id);$item=$this->attachment($r,$id,$attachment);$content=app(ClinicalAttachmentStorage::class)->read($item);$this->log($r,'download_attachment',$id,$item->id);return [$item,$content];});
  [$item,$content]=$result;$extension=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'][$item->mime];$filename='anexo-'.$item->id.'.'.$extension;
  return response($content)->header('Content-Type',$item->mime)->header('Content-Disposition',HeaderUtils::makeDisposition('attachment',$filename))->header('Cache-Control','no-store, private')->header('Referrer-Policy','no-referrer')->header('X-Content-Type-Options','nosniff')->header('Content-Security-Policy',"default-src 'none'; sandbox");
 }
 public function disregard(Request $r,int $id,int $attachment){
  $this->patient($r,$id);$d=$r->validate(['clinical.reason'=>'required|string|min:5|max:1000','acknowledge'=>'accepted']);
  DB::transaction(function()use($r,$id,$attachment,$d){$patient=$this->lockedPatient($r,$id);abort_unless($patient->active,422);$item=$this->attachment($r,$id,$attachment);abort_unless($item->author_id===$r->user()->id,403,'Somente o autor pode desconsiderar este anexo.');if($item->disregarded_at)return;$item->update(['disregarded_at'=>now(),'disregard_reason'=>$d['clinical']['reason']]);$this->log($r,'disregard_attachment',$id,$item->id);});
  return redirect()->route('attachments.index',$id)->with('success','Anexo marcado como desconsiderado. O arquivo e o histórico permanecem disponíveis.');
 }
}
