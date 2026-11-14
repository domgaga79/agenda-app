@extends('layouts.app')
@section('heading','Alterar senha')
@section('content')
<div class="card password-card"><form method="post" action="{{ route('password.update') }}">@csrf @method('PUT')
<div class="password-fields"><div class="field full"><label for="current_password">Senha atual</label><input id="current_password" type="password" name="current_password" autocomplete="current-password" required></div><div class="field"><label for="password">Nova senha</label><input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required></div><div class="field"><label for="password_confirmation">Confirmar nova senha</label><input id="password_confirmation" type="password" name="password_confirmation" minlength="12" autocomplete="new-password" required></div></div><p>Use pelo menos 12 caracteres, com letras e números.</p><div class="form-actions"><button class="btn btn-primary">Atualizar senha</button></div></form></div>
@endsection
