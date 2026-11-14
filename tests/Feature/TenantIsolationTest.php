<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function user(Company $company, string $role, string $email): User
    {
        return User::create(['company_id'=>$company->id,'name'=>$email,'email'=>$email,'role'=>$role,'password'=>Hash::make('password123'),'active'=>true,'must_change_password'=>false]);
    }

    public function test_user_cannot_edit_appointment_from_another_company(): void
    {
        $a = Company::create(['name'=>'A','slug'=>'a']); $b = Company::create(['name'=>'B','slug'=>'b']);
        $ownerA = $this->user($a, User::ROLE_OWNER, 'a@teste.com');
        $proB = $this->user($b, User::ROLE_PROFESSIONAL, 'b@teste.com');
        $appt = Appointment::create(['company_id'=>$b->id,'professional_id'=>$proB->id,'created_by'=>$proB->id,'client_name'=>'Cliente','service'=>'Serviço','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHour(),'status'=>'scheduled']);
        $this->actingAs($ownerA)->get(route('appointments.edit',$appt->id))->assertNotFound();
    }

    public function test_professional_cannot_edit_other_professionals_appointment(): void
    {
        $c = Company::create(['name'=>'A','slug'=>'a']);
        $p1 = $this->user($c, User::ROLE_PROFESSIONAL, 'p1@teste.com'); $p2 = $this->user($c, User::ROLE_PROFESSIONAL, 'p2@teste.com');
        $appt = Appointment::create(['company_id'=>$c->id,'professional_id'=>$p2->id,'created_by'=>$p2->id,'client_name'=>'Cliente','service'=>'Serviço','starts_at'=>now()->addDay(),'ends_at'=>now()->addDay()->addHour(),'status'=>'scheduled']);
        $this->actingAs($p1)->get(route('appointments.edit',$appt->id))->assertNotFound();
    }

    public function test_reception_cannot_access_team_management(): void
    {
        $c = Company::create(['name'=>'A','slug'=>'a']); $r = $this->user($c, User::ROLE_RECEPTION, 'r@teste.com');
        $this->actingAs($r)->get(route('team.index'))->assertForbidden();
    }
}
