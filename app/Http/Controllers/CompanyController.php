<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Models\CompanySlugReservation;

class CompanyController extends Controller
{
    private function owner(Request $request): void { abort_unless($request->user()->isOwner(), 403); }
    public function edit(Request $request) { $this->owner($request); return view('company.edit', ['company' => $request->user()->company]); }
    public function update(Request $request)
    {
        $this->owner($request);
        $company = $request->user()->company;
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'slug' => ['required','alpha_dash','max:100', Rule::unique('companies','slug')->ignore($company->id), Rule::unique('company_slug_reservations','slug')->ignore($company->id,'company_id')],
            'phone' => ['nullable','string','max:30'],
            'email' => ['nullable','email','max:150'],
        ]);
        DB::transaction(function () use ($company, $data): void {
            $company->update($data);
            CompanySlugReservation::firstOrCreate(['slug'=>$company->slug], ['company_id'=>$company->id,'first_seen_at'=>now()]);
        }, 3);
        return back()->with('success', 'Empresa atualizada.');
    }
}
