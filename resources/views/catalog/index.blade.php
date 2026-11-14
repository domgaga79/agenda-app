@extends('layouts.app')
@section('heading', $kind==='customers' ? 'Clientes e pacientes' : 'Serviços e procedimentos')
@section('content')
<div class="card catalog-card">
<div class="catalog-toolbar"><a class="btn btn-primary" href="{{ route($kind.'.create') }}">Novo cadastro</a>
<form method="get" class="catalog-toolbar"><input aria-label="Buscar por nome" name="q" value="{{ request('q') }}" placeholder="Buscar por nome" maxlength="100"><select name="state" aria-label="Situação">@foreach(['active'=>'Ativos','archived'=>'Arquivados','all'=>'Todos'] as $value=>$label)<option value="{{ $value }}" @selected(request('state','active')===$value)>{{ $label }}</option>@endforeach</select><button class="btn btn-secondary">Buscar</button></form></div>
@if($records->isNotEmpty())
<div class="table-wrap"><table class="responsive-table table catalog-table"><thead><tr><th>Nome</th><th>{{ $kind==='customers' ? 'Contato' : 'Duração / valor' }}</th><th>Situação</th><th>Ações</th></tr></thead><tbody>
@forelse($records as $record)<tr><td data-label="Nome">{{ $record->name }}</td><td data-label="{{ $kind==='customers' ? 'Contato' : 'Duração / valor' }}">@if($kind==='customers'){{ $record->phone ?: 'Sem telefone' }}<br>{{ $record->email }}@else{{ $record->duration_minutes }} min · {{ $record->price_cents===null ? 'A combinar' : 'R$ '.number_format($record->price_cents/100,2,',','.') }}@endif</td><td data-label="Situação">{{ $record->active ? 'Ativo' : 'Arquivado' }}</td><td data-label="Ações" class="catalog-actions"><a class="btn btn-secondary" href="{{ route($kind.'.edit',$record->id) }}">Editar</a> @if($kind==='customers')<a class="btn btn-secondary" href="{{ route('customers.show',$record->id) }}">Histórico</a>@endif</td></tr>
@empty<tr><td colspan="4">Nenhum cadastro encontrado.</td></tr>@endforelse
</tbody></table></div>@include('catalog.pagination',['page'=>$records])
@else
<div class="catalog-empty"><span class="empty-symbol" aria-hidden="true">{{ $kind==='customers' ? '◎' : '◇' }}</span><h2>{{ request('q') || request('state','active')!=='active' ? 'Nenhum resultado encontrado' : ($kind==='customers' ? 'Seu primeiro cliente começa aqui' : 'Organize seus serviços') }}</h2><p>{{ request('q') || request('state','active')!=='active' ? 'Tente outro nome ou altere o filtro de situação.' : ($kind==='customers' ? 'Cadastre clientes e pacientes para facilitar os próximos agendamentos.' : 'Adicione os serviços, a duração e o valor de cada atendimento.') }}</p><a class="btn btn-primary" href="{{ route($kind.'.create') }}">{{ $kind==='customers' ? 'Cadastrar cliente' : 'Cadastrar serviço' }}</a></div>
@endif
</div>
@endsection
