@extends('layouts.app')
@section('title','Painel')
@section('heading','Painel da clínica')
@section('content')
@php use App\Support\{Finance,Followups}; $dayLink=['date'=>$today,'view'=>'day']; @endphp
<div class="clinic-dashboard finance-stack">
<div class="finance-heading"><p class="muted">{{ \Carbon\Carbon::parse($today)->format('d/m/Y') }} · {{ auth()->user()->company->booking_timezone }}@if(!$administrative) · Sua agenda @endif</p><a class="btn" href="{{ route('appointments.create') }}">Novo agendamento</a></div>
<div class="dashboard-metrics">
<a class="dashboard-metric" href="{{ route('appointments.index',$dayLink) }}"><span>Atendimentos hoje</span><strong>{{ $appointmentsToday }}</strong><small>Inclui concluídos e faltas · {{ $cancelledToday }} cancelado(s) fora deste total</small></a>
<a class="dashboard-metric" href="{{ route('reminders.index',['from'=>$today,'to'=>$today]) }}"><span>Aguardando confirmação</span><strong>{{ $unconfirmedToday }}</strong><small>Atendimentos de hoje com situação Agendado</small></a>
<a class="dashboard-metric" href="{{ route('appointments.index',$dayLink) }}"><span>Concluídos hoje</span><strong>{{ $completedToday }}</strong><small>Atendimentos de hoje marcados como Concluído</small></a>
</div>
@if($administrative)
<div class="dashboard-metrics dashboard-admin-metrics">
<a class="dashboard-metric" href="{{ route('followups.index',['kind'=>'recall','state'=>'open','until'=>$today]) }}"><span>Retornos a acompanhar</span><strong>{{ (int)($followupCounts['recall']??0) }}</strong><small>Em aberto, com data inicial até hoje</small></a>
<a class="dashboard-metric" href="{{ route('followups.index',['kind'=>'waitlist','state'=>'open','until'=>$today]) }}"><span>Lista de espera</span><strong>{{ (int)($followupCounts['waitlist']??0) }}</strong><small>Em aberto, com data inicial até hoje</small></a>
<a class="dashboard-metric" href="{{ route('finance.index',['from'=>'1900-01-01','to'=>$today,'status'=>'approved']) }}"><span>A receber até hoje</span><strong class="dashboard-money">{{ Finance::money((int)$receivables->pending) }}</strong><small>{{ Finance::money((int)$receivables->overdue) }} em atraso</small></a>
<a class="dashboard-metric" href="{{ route('expenses.index',['from'=>'1900-01-01','to'=>$today,'status'=>'open']) }}"><span>A pagar até hoje</span><strong class="dashboard-money">{{ Finance::money((int)$payables->pending) }}</strong><small>{{ Finance::money((int)$payables->overdue) }} em atraso</small></a>
</div><p class="dashboard-caption muted">Valores representam saldos ainda em aberto, incluindo vencimentos anteriores a hoje e descontando pagamentos válidos. Clique em um cartão para abrir os registros.</p>
@endif
<section class="card finance-card"><div class="finance-heading"><h2>Agenda de hoje</h2><a class="btn secondary" href="{{ route('appointments.index',$dayLink) }}">Ver dia completo</a></div>@include('dashboard.appointments',['items'=>$todayAppointments,'emptyMessage'=>'Nenhum atendimento não cancelado para hoje.'])@if($appointmentsToday>8)<p class="muted">Exibindo os 8 primeiros de {{ $appointmentsToday }} atendimentos. Abra o dia completo para ver todos.</p>@endif</section>
@if($administrative)
<section class="card finance-card"><div class="finance-heading"><h2>Espera e retornos para acompanhar</h2><a class="btn secondary" href="{{ route('followups.index',['state'=>'open','until'=>$today]) }}">Ver lista</a></div>
@if($dueFollowups->isEmpty())<p class="finance-empty">Nenhuma solicitação em aberto com data inicial até hoje.</p>@else<ul class="dashboard-followups">@foreach($dueFollowups as $entry)<li><div><strong>{{ $entry->customer->name }}</strong><span>{{ Followups::KINDS[$entry->kind] }} · {{ $entry->from_date->format('d/m/Y') }} · {{ $entry->professional?->name??'Qualquer dentista' }}</span></div><a class="btn secondary" href="{{ route('followups.show',$entry->id) }}">Acompanhar</a></li>@endforeach</ul>@if($followupCounts->sum()>5)<p class="muted">Exibindo as 5 solicitações com data inicial mais antiga. Abra a lista para ver todas.</p>@endif @endif
</section>
@endif
<section class="card finance-card"><div class="finance-heading"><h2>Próximos dias</h2><a class="btn secondary" href="{{ route('appointments.index',['date'=>$today,'view'=>'week']) }}">Abrir calendário</a></div>@include('dashboard.appointments',['items'=>$nextAppointments,'emptyMessage'=>'Nenhum atendimento agendado ou confirmado a partir de amanhã.'])<p class="muted">Até 6 atendimentos agendados ou confirmados a partir de amanhã.</p></section>
</div>
@endsection
