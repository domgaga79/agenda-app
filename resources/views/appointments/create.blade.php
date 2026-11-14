@extends('layouts.app')
@section('title','Novo agendamento')
@section('heading','Novo agendamento')
@section('content')
<div class="card form-card"><form method="post" action="{{ route('appointments.store') }}">@csrf
@if($followup ?? null)
<input type="hidden" name="followup_id" value="{{ $followup->id }}"><input type="hidden" name="followup_revision" value="{{ old('followup_revision',$followup->revision) }}">
<div class="alert"><strong>{{ \App\Support\Followups::KINDS[$followup->kind] }} · {{ $followup->customer->name }}</strong><p>Escolha um horário disponível a partir de {{ $followup->from_date->format('d/m/Y') }}@if($followup->to_date) até {{ $followup->to_date->format('d/m/Y') }}@endif. {{ \App\Support\Followups::PERIODS[$followup->period] }}.</p><a href="{{ route('followups.show',$followup->id) }}">Voltar à solicitação</a></div>
@endif
@include('appointments.partials.form')<div class="form-actions"><button class="btn btn-primary">Salvar agendamento</button><a class="btn btn-secondary" href="{{ route('appointments.index') }}">Cancelar</a></div></form></div>
@endsection
