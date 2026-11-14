<?php

namespace Tests\Feature;

use App\Models\{Appointment, AppointmentConfirmation, Company, Customer, FinancialExpense, ExpensePayment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class TenantHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function user(Company $company, string $role = User::ROLE_OWNER): User
    {
        return User::create([
            'company_id' => $company->id,
            'name' => 'Tenant user',
            'email' => Str::uuid().'@example.test',
            'password' => 'OnlyTest-12345',
            'role' => $role,
            'active' => true,
        ]);
    }

    public function test_model_guard_blocks_cross_tenant_reference(): void
    {
        $a = Company::create(['name'=>'A','slug'=>'tenant-a']);
        $b = Company::create(['name'=>'B','slug'=>'tenant-b']);
        $ownerA = $this->user($a);
        $professionalA = $this->user($a, User::ROLE_PROFESSIONAL);
        $customerB = Customer::create(['company_id'=>$b->id,'name'=>'Paciente B']);

        $this->expectException(LogicException::class);
        Appointment::create([
            'company_id'=>$a->id,
            'professional_id'=>$professionalA->id,
            'created_by'=>$ownerA->id,
            'customer_id'=>$customerB->id,
            'client_name'=>'Paciente',
            'service'=>'Consulta',
            'starts_at'=>now()->addDay(),
            'ends_at'=>now()->addDay()->addHour(),
            'status'=>'scheduled',
        ]);
    }

    public function test_public_confirmation_does_not_follow_cross_tenant_appointment(): void
    {
        $a = Company::create(['name'=>'A','slug'=>'confirm-a']);
        $b = Company::create(['name'=>'B','slug'=>'confirm-b']);
        $ownerA = $this->user($a);
        $professionalB = $this->user($b, User::ROLE_PROFESSIONAL);

        $appointmentB = Appointment::create([
            'company_id'=>$b->id,
            'professional_id'=>$professionalB->id,
            'created_by'=>$professionalB->id,
            'client_name'=>'Paciente B',
            'service'=>'Consulta',
            'starts_at'=>now()->addDay(),
            'ends_at'=>now()->addDay()->addHour(),
            'status'=>'scheduled',
        ]);

        // Simulate legacy/corrupted data by bypassing Eloquent's tenant guard.
        $token = bin2hex(random_bytes(32));
        DB::table('appointment_confirmations')->insert([
            'company_id'=>$a->id,
            'appointment_id'=>$appointmentB->id,
            'created_by'=>$ownerA->id,
            'token_hash'=>hash('sha256',$token),
            'token'=>encrypt($token),
            'snapshot'=>str_repeat('a',64),
            'request_key'=>hash('sha256',(string) Str::uuid()),
            'expires_at'=>now()->addHour(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $this->get(route('confirmation.show',$token))->assertNotFound();
    }

    public function test_expense_reversal_does_not_follow_cross_tenant_parent(): void
    {
        $a = Company::create(['name'=>'A','slug'=>'expense-a']);
        $b = Company::create(['name'=>'B','slug'=>'expense-b']);
        $ownerA = $this->user($a);
        $ownerB = $this->user($b);

        $expenseB = FinancialExpense::create([
            'company_id'=>$b->id,
            'created_by'=>$ownerB->id,
            'description'=>'Despesa B',
            'category'=>'other',
            'due_on'=>now()->toDateString(),
            'amount_cents'=>1000,
            'request_key'=>hash('sha256',(string) Str::uuid()),
        ]);

        // Simulate legacy/corrupted payment: tenant A payment pointing to tenant B expense.
        $paymentId = DB::table('expense_payments')->insertGetId([
            'company_id'=>$a->id,
            'expense_id'=>$expenseB->id,
            'created_by'=>$ownerA->id,
            'amount_cents'=>100,
            'paid_on'=>now()->toDateString(),
            'method'=>'pix',
            'request_key'=>hash('sha256',(string) Str::uuid()),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $this->actingAs($ownerA)
            ->post(route('expenses.reverse',$paymentId),['reason'=>'Teste de isolamento'])
            ->assertNotFound();
    }
}
