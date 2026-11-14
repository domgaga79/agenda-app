@extends('layouts.app')
@section('title','Ajuda e manual')
@section('heading','Ajuda e manual')
@section('content')
<link rel="stylesheet" href="{{ asset('css/help.css') }}?v=21.4">
<script src="{{ asset('js/help.js') }}?v=18" defer></script>
<div class="help-root">
<header class="help-intro"><div><span class="eyebrow">BACURI AGENDA · MANUAL V21</span><h2>Como podemos ajudar?</h2><p>Da primeira configuração ao fechamento do dia. Encontre o passo a passo para sua rotina.</p></div><div class="help-actions"><a class="button" href="{{ route('help.download') }}">Baixar manual</a><button type="button" data-help-print>Imprimir / Salvar PDF</button></div></header>
@include('help.controls')
<div class="help-grid"><aside class="help-index"><h2>Assuntos</h2><nav aria-label="Assuntos do manual">@foreach($sections as $section)<a href="#{{ $section['id'] }}" data-help-link="{{ $section['id'] }}">{{ $section['title'] }}</a>@endforeach</nav></aside>
<div class="help-articles">@foreach($sections as $section)<article class="help-article" id="{{ $section['id'] }}" data-help-roles="{{ $section['roles'] }}"><h2>{{ $section['title'] }}</h2>@foreach($section['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach<a class="help-back" href="#help-search">Voltar à busca ↑</a></article>@endforeach<p data-help-empty hidden>Nenhum assunto encontrado. Tente outra palavra ou selecione Todos os perfis.</p></div></div>
</div>
@endsection
