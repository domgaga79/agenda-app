@extends('layouts.app')
@section('title','Empresa')
@section('heading','Empresa')
@section('content')
<div class="card form-card"><div class="section-head"><h2>Dados da empresa</h2></div>
<form method="post" action="{{ route('company.update') }}">@csrf @method('PUT')
<div class="form-grid">
<div class="field"><label for="name">Nome</label><input id="name" name="name" value="{{ old('name',$company->name) }}" required></div>
<div class="field"><label for="slug">Identificador</label><input id="slug" name="slug" value="{{ old('slug',$company->slug) }}" required></div>
<div class="field"><label for="phone">Telefone</label><input type="tel" id="phone" name="phone" value="{{ old('phone',$company->phone) }}"></div>
<div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" value="{{ old('email',$company->email) }}"></div>
</div><div class="form-actions"><button class="btn btn-primary">Salvar alterações</button></div></form></div>
@endsection
