@extends('layouts.app')
@section('title','Novo usuário')
@section('heading','Novo usuário')
@section('content')
<div class="card form-card"><form method="post" action="{{ route('team.store') }}">@csrf
@include('team.partials.form',['member'=>null])
<div class="form-actions"><button class="btn btn-primary">Criar usuário</button><a class="btn btn-secondary" href="{{ route('team.index') }}">Cancelar</a></div></form></div>
@endsection
