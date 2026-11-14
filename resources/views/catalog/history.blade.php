@extends('layouts.app')
@section('heading','Histórico de atendimentos')
@section('content')
<div class="card customer-history"><h2>{{ $record->name }}</h2><p>{{ $record->phone }} · {{ $record->email }}</p><p>São exibidos os agendamentos vinculados a este cadastro. Agendamentos antigos podem ser vinculados ao editar.</p>
<div class="table-wrap"><table class="responsive-table table catalog-table"><thead><tr><th>Data</th><th>Serviço</th><th>Profissional</th><th>Situação</th><th></th></tr></thead><tbody>
@forelse($appointments as $a)<tr><td data-label="Data">{{ $a->starts_at->format('d/m/Y H:i') }}</td><td data-label="Serviço">{{ $a->service }}</td><td data-label="Profissional">{{ $a->professional?->name }}</td><td data-label="Situação">{{ ['scheduled'=>'Agendado','confirmed'=>'Confirmado','completed'=>'Concluído','cancelled'=>'Cancelado','no_show'=>'Faltou'][$a->status] ?? $a->status }}</td><td data-label="Ações"><a class="btn btn-secondary" href="{{ route('appointments.edit',$a->id) }}">Abrir</a></td></tr>@empty<tr><td colspan="5">Nenhum atendimento vinculado.</td></tr>@endforelse</tbody></table></div>
@include('catalog.pagination',['page'=>$appointments])
<a class="btn btn-secondary" href="{{ route('customers.index') }}">Voltar aos clientes</a></div>
@endsection
