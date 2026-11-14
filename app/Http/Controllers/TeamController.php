<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TeamController extends Controller
{
    private function owner(Request $request): void { abort_unless($request->user()->isOwner(), 403); }

    public function index(Request $request)
    {
        $this->owner($request);
        $users = User::where('company_id', $request->user()->company_id)->orderBy('name')->get();
        return view('team.index', compact('users'));
    }

    public function create(Request $request) { $this->owner($request); return view('team.create'); }

    public function store(Request $request)
    {
        $this->owner($request);
        $request->merge(['email'=>mb_strtolower(trim((string)$request->input('email')))]);
        $data = $request->validate([
            'name' => ['required','string','max:150'], 'email' => ['required','email','max:150','unique:users,email'],
            'clinical_access'=>['nullable','boolean'], 'professional_registration'=>['nullable','string','max:40','required_if:clinical_access,1'],
            'phone' => ['nullable','string','max:30'], 'role' => ['required', Rule::in([User::ROLE_RECEPTION, User::ROLE_PROFESSIONAL])],
            'password' => ['required', 'max:255', Password::min(12)->letters()->numbers()],
        ]);
        $data['clinical_access']=$request->boolean('clinical_access') && $data['role']===User::ROLE_PROFESSIONAL;
        DB::transaction(function()use($request,$data){
            $member=User::create(array_merge($data, ['company_id' => $request->user()->company_id, 'password' => Hash::make($data['password']), 'active' => true, 'must_change_password' => true]));
            if($member->clinical_access) \App\Models\ClinicalAudit::create(['company_id'=>$member->company_id,'actor_id'=>$request->user()->id,'target_user_id'=>$member->id,'event'=>'grant_access']);
        });
        return redirect()->route('team.index')->with('success', 'Usuário criado. Ele deverá trocar a senha no primeiro acesso.');
    }

    public function edit(Request $request, int $id)
    {
        $this->owner($request);
        $member = User::where('company_id', $request->user()->company_id)->whereKey($id)->firstOrFail();
        abort_if($member->isOwner(), 403);
        return view('team.edit', compact('member'));
    }

    public function update(Request $request, int $id)
    {
        $this->owner($request);
        $member = User::where('company_id', $request->user()->company_id)->whereKey($id)->firstOrFail();
        abort_if($member->isOwner(), 403);
        $request->merge(['email'=>mb_strtolower(trim((string)$request->input('email')))]);
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'email' => ['required','email','max:150', Rule::unique('users','email')->ignore($member->id)],
            'clinical_access'=>['nullable','boolean'], 'professional_registration'=>['nullable','string','max:40','required_if:clinical_access,1'],
            'phone' => ['nullable','string','max:30'], 'role' => ['required', Rule::in([User::ROLE_RECEPTION, User::ROLE_PROFESSIONAL])],
            'active' => ['nullable','boolean'], 'password' => ['nullable', 'max:255', Password::min(12)->letters()->numbers()],
        ]);
        $data['active'] = $request->boolean('active');
        if (!empty($data['password'])) { $data['password'] = Hash::make($data['password']); $data['must_change_password'] = true; } else unset($data['password']);
        $revoke=!$data['active'] || !empty($data['password']);
        $data['clinical_access']=$request->boolean('clinical_access') && $data['role']===User::ROLE_PROFESSIONAL;
        DB::transaction(function()use($request,$member,$data){
            \App\Models\Company::whereKey($request->user()->company_id)->lockForUpdate()->firstOrFail();
            $member->refresh();$changed=$member->clinical_access!==$data['clinical_access'];
            $member->update($data);
            if($changed)\App\Models\ClinicalAudit::create(['company_id'=>$member->company_id,'actor_id'=>$request->user()->id,'target_user_id'=>$member->id,'event'=>$data['clinical_access']?'grant_access':'revoke_access']);
        });
        if ($revoke) {
            $member->remember_token=Str::random(60);$member->save();
            if(config('session.driver')==='database') DB::connection(config('session.connection'))->table(config('session.table','sessions'))->where('user_id',$member->id)->delete();
        }
        return redirect()->route('team.index')->with('success', 'Usuário atualizado.');
    }
}
