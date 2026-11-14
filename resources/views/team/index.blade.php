@extends('layouts.app')
@section('title','Equipe')
@section('heading','Equipe')
@section('content')
<div class="card"><div class="section-head"><h2>Usuários da empresa</h2><a class="btn btn-primary" href="{{ route('team.create') }}">Adicionar usuário</a></div>
<div class="table-wrap"><table class="responsive-table table"><thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Situação</th><th></th></tr></thead><tbody>
@foreach($users as $member)<tr><td data-label="Nome">{{ $member->name }}</td><td data-label="E-mail">{{ $member->email }}</td><td data-label="Perfil">{{ ['owner'=>'Proprietário','reception'=>'Recepção','professional'=>'Profissional'][$member->role] }}</td><td data-label="Situação"><span class="pill">{{ $member->active ? 'Ativo' : 'Inativo' }}</span></td><td data-label="Ações">@if($member->id !== auth()->id() && !$member->isOwner())<a class="btn btn-secondary" href="{{ route('team.edit',$member->id) }}">Editar</a>@endif</td></tr>@endforeach
</tbody></table></div></div>
@endsection
