<?php
namespace App\Services;
use App\Models\ClinicalAttachment;
use Illuminate\Support\Facades\{Storage,Crypt};
use Illuminate\Support\Str;
class ClinicalAttachmentStorage {
 public function write(int $company,string $contents):string {
  $path=$company.'/'.Str::uuid().'.enc';
  Storage::disk('clinical_private')->put($path,Crypt::encryptString($contents));
  return $path;
 }
 public function removeUncommitted(string $path):void {Storage::disk('clinical_private')->delete($path);}
 public function read(ClinicalAttachment $attachment):string {
  $disk=Storage::disk('clinical_private');abort_unless($disk->exists($attachment->path),410,'Arquivo indisponível. Solicite a conferência do backup ao administrador.');
  try{$content=Crypt::decryptString($disk->get($attachment->path));}catch(\Illuminate\Contracts\Encryption\DecryptException $e){abort(503,'Não foi possível conferir o arquivo. Solicite a verificação do backup e da chave da aplicação.');}
  abort_unless(strlen($content)===$attachment->bytes&&hash_equals($attachment->sha256,hash('sha256',$content)),503,'Não foi possível conferir a integridade do arquivo.');return $content;
 }
}
