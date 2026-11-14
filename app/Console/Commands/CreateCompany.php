<?php
namespace App\Console\Commands;
use App\Models\Company;
use App\Models\User;
use App\Models\CompanySlugReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
class CreateCompany extends Command {
 protected $signature='company:create {name} {email} {--slug=}';
 protected $description='Cria uma empresa e seu proprietário, solicitando a senha em particular';
 public function handle(): int {
  $data=['name'=>$this->argument('name'),'email'=>mb_strtolower(trim($this->argument('email'))),
   'slug'=>$this->option('slug') ?: Str::slug($this->argument('name')).'-'.Str::lower(Str::random(6)),
   'password'=>$this->secret('Senha provisória (12 caracteres, letras e números)'),
   'password_confirmation'=>$this->secret('Confirme a senha')];
  $v=Validator::make($data,['name'=>'required|string|max:150','email'=>'required|email|max:150|unique:users,email','slug'=>'required|alpha_dash|max:100|unique:companies,slug|unique:company_slug_reservations,slug','password'=>['required','confirmed','max:255',Password::min(12)->letters()->numbers()]]);
  if($v->fails()){foreach($v->errors()->all() as $error)$this->error($error);return self::FAILURE;}
  DB::transaction(function()use($data){
   $c=Company::create(['name'=>$data['name'],'slug'=>$data['slug'],'email'=>$data['email'],'active'=>true]);
   CompanySlugReservation::create(['company_id'=>$c->id,'slug'=>$c->slug,'first_seen_at'=>now()]);
   User::create(['company_id'=>$c->id,'name'=>'Proprietário','email'=>$data['email'],'role'=>User::ROLE_OWNER,'password'=>$data['password'],'active'=>true,'must_change_password'=>true]);
  });
  $this->info('Empresa criada. Acesse '.rtrim(config('app.url'),'/').'/login com o e-mail informado.');return self::SUCCESS;
 }
}
