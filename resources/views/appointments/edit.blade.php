@extends('layouts.app')
@section('title','Editar agendamento')
@section('heading','Editar agendamento')
@section('content')
<div class="reminder-agenda-link"><a class="btn btn-primary" href="{{ route('reminders.show',$appointment->id) }}">WhatsApp e confirmação</a><span>{{ $appointment->responseLabel() }}</span></div>
@if(auth()->user()->canUseClinical() && $appointment->customer_id && $appointment->status!=='cancelled')<p><a class="btn btn-primary" href="{{ route('clinical.show',$appointment->customer_id) }}">Abrir prontuário do paciente</a></p>@endif
@if($appointment->source==='public')<div class="alert success">Agendamento recebido pela página pública. Confira os dados de contato e vincule o cadastro do paciente, quando necessário.</div>@endif
<div class="card form-card"><form method="post" action="{{ route('appointments.update',$appointment->id) }}">@csrf @method('PUT') @include('appointments.partials.form',['appointment'=>$appointment])<div class="form-actions"><button class="btn btn-primary">Salvar alterações</button><a class="btn btn-secondary" href="{{ route('appointments.index',['date'=>$appointment->starts_at->format('Y-m-d')]) }}">Cancelar</a></div></form><form class="delete-appointment" method="post" action="{{ route('appointments.destroy',$appointment->id) }}" onsubmit="return confirm('Excluir este agendamento? Esta ação não pode ser desfeita.')">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Excluir agendamento</button></form></div>
@endsection
