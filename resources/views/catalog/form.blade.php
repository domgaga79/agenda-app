@extends('layouts.app')
@section('heading', ($record ? 'Editar ' : 'Cadastrar ').($kind==='customers' ? 'cliente / paciente' : 'serviço / procedimento'))
@section('content')
<div class="card form-card"><form method="post" action="{{ $record ? route($kind.'.update',$record->id) : route($kind.'.store') }}">@csrf @if($record) @method('PUT') @endif
<div class="form-grid">
<div class="field full"><label for="name">Nome</label><input id="name" name="name" maxlength="150" required value="{{ old('name',$record?->name) }}"></div>
@if($kind==='customers')
<div class="field"><label for="phone">Telefone</label><input type="tel" id="phone" name="phone" maxlength="30" value="{{ old('phone',$record?->phone) }}"></div>
<div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email" maxlength="150" value="{{ old('email',$record?->email) }}"></div>
<div class="field"><label for="birth_date">Data de nascimento</label><input id="birth_date" type="date" name="birth_date" max="{{ now()->toDateString() }}" value="{{ old('birth_date',$record?->birth_date?->format('Y-m-d')) }}"></div>
<div class="field"><label for="guardian_name">Nome do responsável</label><input id="guardian_name" name="guardian_name" maxlength="150" value="{{ old('guardian_name',$record?->guardian_name) }}"></div>
<div class="field"><label for="guardian_relationship">Vínculo do responsável</label><input id="guardian_relationship" name="guardian_relationship" maxlength="60" value="{{ old('guardian_relationship',$record?->guardian_relationship) }}"></div>
<div class="field"><label for="guardian_phone">Telefone do responsável</label><input id="guardian_phone" name="guardian_phone" maxlength="30" value="{{ old('guardian_phone',$record?->guardian_phone) }}"></div>
<div class="field"><label for="address">Endereço</label><input id="address" name="address" maxlength="250" value="{{ old('address',$record?->address) }}"></div>
<div class="field"><label for="emergency_contact">Contato de emergência</label><input id="emergency_contact" name="emergency_contact" maxlength="150" value="{{ old('emergency_contact',$record?->emergency_contact) }}"></div>
<div class="field"><label for="emergency_phone">Telefone de emergência</label><input id="emergency_phone" name="emergency_phone" maxlength="30" value="{{ old('emergency_phone',$record?->emergency_phone) }}"></div>
@else
<div class="field"><label for="duration_minutes">Duração sugerida (minutos)</label><input id="duration_minutes" name="duration_minutes" type="number" min="5" max="1440" required value="{{ old('duration_minutes',$record?->duration_minutes ?? 30) }}"></div>
<div class="field"><label for="price">Valor (R$)</label><input id="price" name="price" inputmode="decimal" placeholder="Ex.: 150,00" value="{{ old('price',$record?->price_cents===null ? '' : number_format($record->price_cents/100,2,',','')) }}"><small>Deixe vazio para valor a combinar.</small></div>
@endif
<div class="field"><label for="active">Situação</label><select id="active" name="active"><option value="1" @selected((string)old('active',$record?->active ?? 1)==='1')>Ativo</option><option value="0" @selected((string)old('active',$record?->active ?? 1)==='0')>Arquivado</option></select></div>
</div><p>Arquivar impede novas seleções e mantém os agendamentos existentes.</p><div class="form-actions"><button class="btn btn-primary">Salvar</button><a class="btn btn-secondary" href="{{ route($kind.'.index') }}">Voltar</a></div></form></div>
@endsection
