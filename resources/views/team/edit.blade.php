@extends('layouts.app')
@section('title','Editar usuário')
@section('heading','Editar usuário')
@section('content')
<div class="card form-card"><form method="post" action="{{ route('team.update',$member->id) }}">@csrf @method('PUT')
@include('team.partials.form',['member'=>$member])
<div class="form-actions"><button class="btn btn-primary">Salvar alterações</button><a class="btn btn-secondary" href="{{ route('team.index') }}">Cancelar</a></div></form></div>
@endsection
