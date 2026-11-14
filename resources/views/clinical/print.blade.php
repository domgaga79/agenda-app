<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prontuário odontológico · Bacuri Agenda</title><link rel="stylesheet" href="{{ asset('css/clinical-print.css') }}?v=19"><script src="{{ asset('js/clinical-print.js') }}?v=19" defer></script></head>
<body>
<div class="print-toolbar"><a href="{{ route('clinical.show',$patient->id) }}">← Voltar ao prontuário</a><button type="button" data-clinical-print>Imprimir este lote / Salvar PDF</button></div>
@if($errors->any())<div class="print-errors" role="alert"><strong>Confira os filtros:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="print-filters" method="get" action="{{ route('clinical.print',$patient->id) }}"><label>Atendimentos a partir de<input type="date" name="from" value="{{ $from }}"></label><label>Até<input type="date" name="to" value="{{ $to }}"></label><button type="submit">Filtrar período</button><a href="{{ route('clinical.print',$patient->id) }}">Todo o período</a></form>
@if($records->hasPages())<div class="print-navigation">@include('catalog.pagination',['page'=>$records])<p>Imprima cada lote separadamente para obter todos os registros do período. Cada lote contém até 25 registros clínicos.</p></div>@endif
<main class="print-document">
<header class="document-header"><img src="{{ asset('brand/symbol.svg') }}" width="40" height="40" alt="Bacuri Agenda"><div><p class="eyebrow">{{ $company->name }}</p><h1>Prontuário odontológico</h1><p>Paciente: <strong>{{ $patient->name }}</strong> · Cadastro #{{ $patient->id }}</p></div></header>
<div class="document-scope"><strong>Lote {{ $records->currentPage() }} de {{ $records->lastPage() }} · {{ $records->count() }} de {{ $records->total() }} registros do período</strong><p>Atendimentos: {{ $from ? \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') : 'sem limite inicial' }} até {{ $to ? \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') : 'sem limite final' }}.</p><p>Consulta gerada em {{ $generatedAt->format('d/m/Y H:i') }} por {{ auth()->user()->name }} · {{ auth()->user()->professional_registration }}. Fuso: {{ $company->booking_timezone }}.</p><p>Registros ordenados pela data do atendimento. Todas as versões dentro do período são exibidas; referências a registros fora do período ou deste lote conservam seu número original. Arquivos de exames não são incorporados: consulte Anexos e exames no sistema.</p></div>
<section><h2>Identificação do paciente</h2><dl class="patient-facts">@foreach(['Nome'=>$patient->name,'Nascimento'=>$patient->birth_date?->format('d/m/Y'),'Telefone'=>$patient->phone,'E-mail'=>$patient->email,'Endereço'=>$patient->address,'Responsável'=>$patient->guardian_name,'Vínculo'=>$patient->guardian_relationship,'Telefone do responsável'=>$patient->guardian_phone,'Contato de emergência'=>$patient->emergency_contact,'Telefone de emergência'=>$patient->emergency_phone] as $label=>$value)<div><dt>{{ $label }}</dt><dd>{{ $value ?: 'Não informado' }}</dd></div>@endforeach</dl>@if(!$patient->active)<p>Cadastro arquivado; impressão do histórico preservado.</p>@endif</section>
<section><h2>Registros clínicos</h2>
@forelse($records as $record)
<article class="print-record"><header><h3>{{ ['anamnesis'=>'Anamnese','evolution'=>'Evolução','addendum'=>'Adendo','treatment_plan'=>'Plano de tratamento','odontogram'=>'Odontograma'][$record->kind] ?? 'Registro clínico' }} #{{ $record->id }}</h3><p>Atendimento: {{ $record->occurred_at->format('d/m/Y H:i') }}<br>Autor: {{ $record->author_name }} · {{ $record->author_registration }}<br>Registrado em {{ $record->created_at->copy()->timezone($company->booking_timezone)->format('d/m/Y H:i') }}@if($record->parent_id)<br>Referente ao registro #{{ $record->parent_id }}@endif</p></header>
@if($record->kind==='anamnesis')
@include('clinical.anamnesis',['payload'=>$record->payload])
@elseif($record->kind==='treatment_plan')
@include('treatment.summary',['plan'=>$record->payload])
@elseif($record->kind==='odontogram')
<p>Dentição selecionada: {{ \App\Support\Odontogram::MODES[$record->payload['dentition'] ?? ''] ?? 'Não informada' }}. Numeração FDI.</p>
<p>Dentes sem avaliação e sem observações são omitidos desta relação textual; isso não significa ausência de alterações.</p>
@include('odontogram.summary',['chart'=>$record->payload])
@else
<div class="clinical-text">{{ $record->payload['content'] ?? '' }}</div>
@endif
</article>
@empty<p>Nenhum registro clínico no período selecionado.</p>@endforelse
</section>
<footer class="document-footer"><p>Paciente #{{ $patient->id }} · Lote {{ $records->currentPage() }} de {{ $records->lastPage() }}</p><p>Cópia de consulta do Bacuri Agenda. Autoria identificada pelo acesso ao sistema; sem assinatura digital certificada. Guarde a cópia em local protegido.</p></footer>
</main>
</body></html>
