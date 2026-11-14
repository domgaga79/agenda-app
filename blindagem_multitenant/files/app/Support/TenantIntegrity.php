<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TenantIntegrity
{
    /**
     * Application-level tenant foreign-key guard.
     *
     * The schema uses ordinary foreign keys. This guard adds the missing invariant:
     * every referenced tenant-owned row must have the same company_id as its parent.
     */
    private const REFERENCES = [
        \App\Models\Appointment::class => [
            'professional_id' => 'users',
            'created_by' => 'users',
            'customer_id' => 'customers',
            'service_id' => 'services',
        ],
        \App\Models\AppointmentConfirmation::class => [
            'appointment_id' => 'appointments',
            'created_by' => 'users',
            'sent_by' => 'users',
        ],
        \App\Models\WorkingPeriod::class => [
            'professional_id' => 'users',
        ],
        \App\Models\ScheduleBlock::class => [
            'professional_id' => 'users',
        ],
        \App\Models\ClinicalRecord::class => [
            'customer_id' => 'customers',
            'author_id' => 'users',
            'parent_id' => 'clinical_records',
        ],
        \App\Models\ClinicalAttachment::class => [
            'customer_id' => 'customers',
            'author_id' => 'users',
        ],
        \App\Models\ClinicalAudit::class => [
            'actor_id' => 'users',
            'customer_id' => 'customers',
            'target_user_id' => 'users',
            'attachment_id' => 'clinical_attachments',
        ],
        \App\Models\FinancialQuote::class => [
            'customer_id' => 'customers',
            'created_by' => 'users',
            'source_record_id' => 'clinical_records',
        ],
        \App\Models\FinancialInstallment::class => [
            'quote_id' => 'financial_quotes',
        ],
        \App\Models\FinancialPayment::class => [
            'installment_id' => 'financial_installments',
            'created_by' => 'users',
            'reversed_by' => 'users',
        ],
        \App\Models\FinancialEvent::class => [
            'quote_id' => 'financial_quotes',
            'actor_id' => 'users',
            'payment_id' => 'financial_payments',
        ],
        \App\Models\FinancialExpense::class => [
            'created_by' => 'users',
        ],
        \App\Models\ExpensePayment::class => [
            'expense_id' => 'financial_expenses',
            'created_by' => 'users',
            'reversed_by' => 'users',
        ],
        \App\Models\ExpenseEvent::class => [
            'expense_id' => 'financial_expenses',
            'actor_id' => 'users',
        ],
        \App\Models\PatientFollowup::class => [
            'customer_id' => 'customers',
            'created_by' => 'users',
            'professional_id' => 'users',
            'service_id' => 'services',
            'appointment_id' => 'appointments',
        ],
        \App\Models\FollowupEvent::class => [
            'followup_id' => 'patient_followups',
            'actor_id' => 'users',
        ],
    ];

    public static function boot(): void
    {
        foreach (self::REFERENCES as $modelClass => $references) {
            $modelClass::saving(function (Model $model) use ($references): void {
                self::validate($model, $references);
            });
        }
    }

    /** @param array<string,string> $references */
    private static function validate(Model $model, array $references): void
    {
        $companyId = (int) $model->getAttribute('company_id');
        if ($companyId < 1) {
            throw new LogicException('Registro tenant sem company_id válido.');
        }

        foreach ($references as $column => $table) {
            $id = $model->getAttribute($column);
            if ($id === null || $id === '') {
                continue;
            }


            $valid = DB::table($table)
                ->where('id', $id)
                ->where('company_id', $companyId)
                ->exists();

            if (!$valid) {
                throw new LogicException('Referência entre empresas diferentes bloqueada.');
            }
        }
    }

    /**
     * Definitions reused by the tenant:audit command.
     * @return array<class-string<Model>,array<string,string>>
     */
    public static function references(): array
    {
        return self::REFERENCES;
    }
}
