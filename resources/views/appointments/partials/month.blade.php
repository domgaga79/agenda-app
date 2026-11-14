<div class="month-scroll" tabindex="0" aria-label="Calendário mensal">
<div class="month-grid">
@foreach(['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $weekday)<div class="month-weekday">{{ $weekday }}</div>@endforeach
@foreach($calendarDays as $day)
@php
 $inMonth=$day['date']->format('Y-m')===$date->format('Y-m');
 $dayUrl=route('appointments.index',['view'=>'day','date'=>$day['date']->toDateString(),'professional'=>request('professional')]);
@endphp
<div class="month-cell {{ $inMonth ? '' : 'outside-month' }} {{ $day['date']->isToday() ? 'is-today' : '' }}">
 <div class="month-cell-head"><a class="month-date" href="{{ $dayUrl }}" aria-label="Abrir agenda de {{ $day['date']->format('d/m/Y') }}">{{ $day['date']->format('j') }}@if($day['date']->day===1)<small>{{ $day['date']->translatedFormat('M') }}</small>@endif</a><a class="month-add" href="{{ route('appointments.create',['date'=>$day['date']->toDateString()]) }}" aria-label="Novo agendamento em {{ $day['date']->format('d/m/Y') }}">+</a></div>
 @if(count($day['events']))<a class="month-mobile-count" href="{{ $dayUrl }}" aria-label="{{ count($day['events']) }} atendimento(s) em {{ $day['date']->format('d/m/Y') }}"><span aria-hidden="true">{{ count($day['events']) }}</span><span class="sr-only">atendimento(s)</span></a>@endif
 @foreach(array_slice($day['events'],0,3) as $event)
 @php($a=$event['appointment'])
 <a class="month-event event-{{ $a->status }}" href="{{ route('appointments.edit',$a->id) }}" title="{{ $a->client_name }} · {{ $a->service }} · {{ $a->professional?->name }} · {{ $a->starts_at->format('d/m H:i') }} – {{ $a->ends_at->format('d/m H:i') }} · {{ $a->responseLabel() }}"><time>{{ $a->starts_at->lt($day['date']) ? '↳' : $a->starts_at->format('H:i') }}</time><span>{{ $a->client_name }}</span></a>
 @endforeach
 @if(count($day['events'])>3)<a class="month-more" href="{{ $dayUrl }}">+ {{ count($day['events'])-3 }} atendimento(s)</a>@endif
</div>
@endforeach
</div></div>
