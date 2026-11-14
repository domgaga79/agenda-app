<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_is_blocked(): void
    {
        $c = Company::create(['name'=>'A','slug'=>'a']);
        $u = User::create(['company_id'=>$c->id,'name'=>'Inativo','email'=>'x@teste.com','role'=>'owner','password'=>Hash::make('password123'),'active'=>false,'must_change_password'=>false]);
        $this->actingAs($u)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_temporary_password_forces_change_screen(): void
    {
        $c = Company::create(['name'=>'A','slug'=>'a']);
        $u = User::create(['company_id'=>$c->id,'name'=>'Novo','email'=>'novo@teste.com','role'=>'owner','password'=>Hash::make('password123'),'active'=>true,'must_change_password'=>true]);
        $this->actingAs($u)->get(route('dashboard'))->assertRedirect(route('password.change'));
    }

    public function test_conflicting_appointment_is_rejected(): void
    {
        $c = Company::create(['name'=>'A','slug'=>'a']);
        $owner = User::create(['company_id'=>$c->id,'name'=>'Dono','email'=>'d@teste.com','role'=>'owner','password'=>Hash::make('password123'),'active'=>true,'must_change_password'=>false]);
        $pro = User::create(['company_id'=>$c->id,'name'=>'Pro','email'=>'p@teste.com','role'=>'professional','password'=>Hash::make('password123'),'active'=>true,'must_change_password'=>false]);
        $base=['professional_id'=>$pro->id,'client_name'=>'Cliente 1','service'=>'Serviço','starts_at'=>'2026-09-23 10:00:00','ends_at'=>'2026-09-23 11:00:00','status'=>'scheduled'];
        $this->actingAs($owner)->post(route('appointments.store'),$base)->assertRedirect();
        $this->actingAs($owner)->post(route('appointments.store'),array_merge($base,['client_name'=>'Cliente 2','starts_at'=>'2026-09-23 10:30:00','ends_at'=>'2026-09-23 11:30:00']))->assertSessionHasErrors('starts_at');
    }
}
