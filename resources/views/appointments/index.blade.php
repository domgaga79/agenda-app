@extends('layouts.app')
@section('title','Agenda')
@section('heading','Agenda')
@section('content')
@if($blocks->isNotEmpty())
<details class="card dental-card" style="margin-bottom:16px"><summary>{{ $blocks->count() }} bloqueio(s) neste período</summary><div class="period-list">@foreach($blocks as $block)<div><strong>{{ $block->professional_id ? ($professionals->firstWhere('id',$block->professional_id)?->name ?? 'Dentista') : 'Toda a clínica' }}</strong> · {{ \Illuminate\Support\Carbon::parse($block->starts_at)->format('d/m H:i') }} – {{ \Illuminate\Support\Carbon::parse($block->ends_at)->format('d/m H:i') }}<br><small>{{ $block->reason }}</small></div>@endforeach</div></details>
@endif
<div class="calendar-page">
@php
 $params=['view'=>$mode,'professional'=>request('professional')];
 $step=$mode==='week'?7:1;
 $previous=$mode==='month'?$date->copy()->startOfMonth()->subMonth():$date->copy()->subDays($step);
 $next=$mode==='month'?$date->copy()->startOfMonth()->addMonth():$date->copy()->addDays($step);
 $hourHeight=60;
 $labels=['scheduled'=>'Agendado','confirmed'=>'Confirmado','completed'=>'Concluído','cancelled'=>'Cancelado','no_show'=>'Faltou'];
@endphp
<div class="calendar-toolbar"><a class="btn btn-secondary" href="{{ route('appointments.daily',['date'=>$date->toDateString(),'professional_id'=>request('professional')]) }}">Lista diária / imprimir</a><a class="btn btn-secondary" href="{{ route('reminders.index') }}">Lembretes e confirmações</a>
 <div class="calendar-navigation"><a class="btn btn-secondary" href="{{ route('appointments.index',$params+['date'=>now()->toDateString()]) }}">Hoje</a><a class="calendar-arrow" aria-label="Período anterior" href="{{ route('appointments.index',$params+['date'=>$previous->toDateString()]) }}">‹</a><a class="calendar-arrow" aria-label="Próximo período" href="{{ route('appointments.index',$params+['date'=>$next->toDateString()]) }}">›</a><h2>{{ $date->translatedFormat('F \d\e Y') }}</h2></div>
 <a class="btn btn-primary" href="{{ route('appointments.create',['date'=>$date->toDateString()]) }}">+ Novo agendamento</a>
</div>
<div class="calendar-controls">
 <form class="calendar-filter" method="get"><input type="hidden" name="view" value="{{ $mode }}"><label for="calendar-date">Data</label><input id="calendar-date" type="date" name="date" value="{{ $date->toDateString() }}" required>
 @unless(auth()->user()->isProfessional())<label for="calendar-professional" class="sr-only">Profissional</label><select id="calendar-professional" name="professional"><option value="">Todos os profissionais</option>@foreach($professionals as $p)<option value="{{ $p->id }}" @selected((string)request('professional')===(string)$p->id)>{{ $p->name }}</option>@endforeach</select>@endunless<button class="btn btn-secondary">Aplicar</button></form>
 <div class="calendar-switch" aria-label="Visualização">@foreach(['day'=>'Dia','week'=>'Semana','month'=>'Mês'] as $value=>$label)<a class="{{ $mode===$value ? 'selected' : '' }}" href="{{ route('appointments.index',['date'=>$date->toDateString(),'view'=>$value,'professional'=>request('professional')]) }}" @if($mode===$value) aria-current="page" @endif>{{ $label }}</a>@endforeach</div>
</div>
<div class="calendar-meta"><span>{{ $appointments->count() }} atendimento(s) no período</span><span>{{ $mode==='month' ? 'Clique no dia para abrir a agenda ou no atendimento para editar.' : 'Clique em um horário para agendar ou em um atendimento para editar.' }}</span></div>
@if($mode==='month')
@include('appointments.partials.month')
@else
@if($mode==='week')<p class="mobile-calendar-hint">Deslize a grade para os lados ou selecione Dia para ver os horários em uma coluna.</p>@endif
<div class="calendar-scroll" tabindex="0" aria-label="Calendário de atendimentos">
<div class="calendar-grid {{ $mode==='week' ? 'calendar-week' : 'calendar-day' }}" style="--days:{{ count($calendarDays) }}">
 <div class="calendar-header calendar-corner">Horário</div>
 @foreach($calendarDays as $day)<div class="calendar-header {{ $day['date']->isToday() ? 'is-today' : '' }}"><span>{{ $day['date']->translatedFormat('D') }}</span><a href="{{ route('appointments.index',['view'=>'day','professional'=>request('professional'),'date'=>$day['date']->toDateString()]) }}" class="day-number">{{ $day['date']->format('d') }}</a></div>@endforeach
 <div class="calendar-hours" style="height:{{ ($lastHour-$firstHour)*$hourHeight }}px">@for($h=$firstHour;$h<$lastHour;$h++)<span style="top:{{ ($h-$firstHour)*$hourHeight }}px">{{ sprintf('%02d:00',$h) }}</span>@endfor</div>
 @foreach($calendarDays as $day)
 <div class="calendar-column {{ $day['date']->isToday() ? 'is-today' : '' }}" style="height:{{ ($lastHour-$firstHour)*$hourHeight }}px">
 @for($h=$firstHour;$h<$lastHour;$h++)<a class="calendar-slot" style="top:{{ ($h-$firstHour)*$hourHeight }}px" href="{{ route('appointments.create',['date'=>$day['date']->toDateString(),'time'=>sprintf('%02d:00',$h)]) }}" aria-label="Agendar {{ $day['date']->format('d/m') }} às {{ sprintf('%02d:00',$h) }}"><span>+</span></a>@endfor
 @foreach($day['events'] as $event)
 @php($a=$event['appointment'])
 <a class="calendar-event event-{{ $a->status }}" style="top:{{ min(($event['from']-$firstHour*60)*($hourHeight/60),($lastHour-$firstHour)*$hourHeight-20) }}px;height:{{ ($event['visualEnd']-$event['from'])*($hourHeight/60) }}px;left:calc({{ $event['lane']*100/$event['lanes'] }}% + 3px);width:calc({{ 100/$event['lanes'] }}% - 6px)" href="{{ route('appointments.edit',$a->id) }}" title="{{ $a->client_name }} · {{ $a->service }} · {{ $a->professional?->name }} · {{ $a->starts_at->format('d/m H:i') }} – {{ $a->ends_at->format('d/m H:i') }} · {{ $a->responseLabel() }}">
 <strong>{{ $a->client_name }}</strong><span>{{ $a->starts_at->format('H:i') }} – {{ $a->ends_at->format('H:i') }} · {{ $a->service }}</span><small>{{ $a->professional?->name }} · {{ $a->responseLabel() }}</small></a>
 @endforeach
 </div>
 @endforeach
</div></div>
@endif
<div class="calendar-legend">@foreach($labels as $value=>$label)<span><i class="legend-{{ $value }}"></i>{{ $label }}</span>@endforeach</div>
</div>
@endsection
