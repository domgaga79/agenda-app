@extends('layouts.app')
@section('title','Entrar')
@section('content')
<div class="card auth-card">
    <div class="brand"><span class="brand-mark">B</span><div><strong>Agenda</strong><small>Bacuri Digital</small></div></div>
    <h1>Acesse sua conta</h1><p>Entre com o e-mail cadastrado pela sua empresa.</p>
    <form method="post" action="{{ route('login.attempt') }}">@csrf
        <div class="field"><label for="email">E-mail</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus></div>
        <div class="field"><label for="password">Senha</label><input id="password" type="password" name="password" required></div>
        <label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" name="remember" value="1"> Manter conectado</label>
        <button class="btn btn-primary" type="submit">Entrar</button>
    </form>
</div>
@endsection
