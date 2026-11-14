<?php
[$script,$package,$target]=$argv;
$manifest=json_decode(file_get_contents($package.'/update-manifest.json'),true,512,JSON_THROW_ON_ERROR);
foreach($manifest as $rel=>$hash){
 $source=$package.'/'.$rel;$dest=$target.'/'.$rel;
 if(!is_file($source)||hash_file('sha256',$source)!==$hash['new']){fwrite(STDERR,"Arquivo inválido no pacote: $rel\n");exit(1);}
 if(is_link($dest)){fwrite(STDERR,"Link inesperado: $rel\n");exit(1);}
 if(is_file($dest)&&!in_array(hash_file('sha256',$dest),[$hash['original'],$hash['new']],true)){
  fwrite(STDERR,"Arquivo diferente da base enviada: $rel. Pare e envie essa informação para preservar suas alterações.\n");exit(1);
 }
}
require $target.'/vendor/autoload.php';
$app=require $target.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('app.debug')||config('app.env')!=='production'||empty(config('app.key'))){fwrite(STDERR,"Configure APP_ENV=production, APP_DEBUG=false e preserve APP_KEY. Execute config:clear.\n");exit(1);}
$schema=Illuminate\Support\Facades\Schema::getFacadeRoot();
if($schema->hasTable('companies')&&(!$schema->hasColumn('companies','slug')||!$schema->hasColumn('companies','active'))){fwrite(STDERR,"O banco não corresponde ao modelo V1 do ZIP. Nenhuma migração foi executada.\n");exit(1);}
if(in_array(config('database.default'),['mysql','mariadb'],true)){
 foreach(Illuminate\Support\Facades\DB::select("SELECT TABLE_NAME AS tab, ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('companies','users','appointments')") as $table){
  if(strtolower((string)$table->engine)!=='innodb'){fwrite(STDERR,"A tabela {$table->tab} precisa usar InnoDB para controle de conflitos.\n");exit(1);}
 }
}
echo "Arquivos compatíveis e configuração verificada.\n";
