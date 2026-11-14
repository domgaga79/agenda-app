@php($interactive=$interactive??false)
<div class="odonto-chart" aria-label="Odontograma por quadrantes">
@foreach(['permanent'=>'Dentes permanentes','primary'=>'Dentes decíduos'] as $type=>$title)
<section class="odonto-dentition" data-dentition="{{ $type }}" @if($chart['dentition']!=='mixed' && $chart['dentition']!==$type) hidden @endif><h3>{{ $title }}</h3><div class="odonto-quadrants">
@foreach(\App\Support\Odontogram::quadrants() as $quadrant)@if($quadrant['type']===$type)
<div class="odonto-quadrant"><h4>{{ $quadrant['label'] }} <small>do paciente</small></h4><div class="odonto-teeth {{ $type==='primary'?'primary-teeth':'' }}">
@foreach($quadrant['codes'] as $code)@php($tooth=$chart['teeth'][$code])
<button type="button" class="odonto-tooth state-{{ $tooth['status'] }}" data-tooth="{{ $code }}" aria-label="Dente {{ $code }}: {{ \App\Support\Odontogram::STATES[$tooth['status']] }}" title="Dente {{ $code }}: {{ \App\Support\Odontogram::STATES[$tooth['status']] }}" @disabled(!$interactive)><strong>{{ $code }}</strong><svg viewBox="0 0 40 50" width="30" height="38" aria-hidden="true"><path d="M20 7C13 1 4 5 5 17c1 8 5 10 6 19 1 9 5 11 7 0l2-10 2 10c2 11 6 9 7 0 1-9 5-11 6-19C36 5 27 1 20 7Z"/><path d="M13 12q7-4 14 0" class="tooth-detail"/></svg><span class="odonto-symbol" aria-hidden="true">{{ ['unassessed'=>'?','sound'=>'✓','caries'=>'●','restored'=>'■','missing'=>'×','unerupted'=>'…','implant'=>'⊙','other'=>'!'][$tooth['status']] }}</span><span class="odonto-note-dot" @if(empty($tooth['notes'])) hidden @endif title="Possui observação" aria-hidden="true">•</span></button>
@endforeach</div></div>@endif @endforeach</div></section>@endforeach
</div>