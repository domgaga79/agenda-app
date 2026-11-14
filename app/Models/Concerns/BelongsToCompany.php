<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('company_id'), $companyId);
    }

    public function belongsToCompany(int $companyId): bool
    {
        return (int) $this->getAttribute('company_id') === $companyId;
    }
}
