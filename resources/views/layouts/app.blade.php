<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Agenda') · Bacuri Digital</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=21.3">
<script src="{{ asset('js/mobile-layout.js') }}?v=18" defer></script>
@include('partials.brand-meta')
</head>
<body>
<div class="shell">
    @auth
    <aside class="sidebar">
        <a class="brand" href="{{ url('/') }}" aria-label="Ir para a página inicial"><img class="brand-mark" src="{{ asset('brand/symbol.svg') }}" width="40" height="40" alt=""><div><strong>Agenda</strong><small>Bacuri Digital</small></div></a>
        <button class="mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="sidebar-navigation"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Menu</span></button>
        <nav id="sidebar-navigation" aria-label="Menu principal">
            <a class="{{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg><span>Painel</span></a>
            <a class="{{ request()->routeIs('appointments.*') ? 'active' : '' }}" href="{{ route('appointments.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-13 4h2m4 0h2"/></svg><span>Agenda</span></a>
            <a href="{{ route('reports.schedule') }}" class="{{ request()->routeIs('reports.schedule*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 3v18h17M8 17v-5m5 5V7m5 10V4"/></svg><span>Relatórios da agenda</span></a>
            <a href="{{ route('reminders.index') }}" class="{{ request()->routeIs('reminders.*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span>Lembretes</span></a>
            @if(auth()->user()->canUseClinical())<a href="{{ route('clinical.index') }}" class="{{ request()->routeIs('clinical.index','clinical.show') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 8h8m-8 4h8m-8 4h5"/></svg><span>Prontuários</span></a>@endif
            @if(auth()->user()->isOwner() || auth()->user()->isReception())
            <a href="{{ route('followups.index') }}" class="{{ request()->routeIs('followups.*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6M3 3v6h6M12 7v5l3 2"/></svg><span>Espera e retornos</span></a>
            <a href="{{ route('finance.index') }}" class="{{ request()->routeIs('finance.*','expenses.*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 10h18m-5 5h2M7 5V3"/></svg><span>Financeiro</span></a>
            <a href="{{ route('availability.index') }}" class="{{ request()->routeIs('availability.*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>Disponibilidade</span></a>
            <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2m1-16a3 3 0 0 1 0 6m3 10v-2a6 6 0 0 0-2-4"/></svg><span>Clientes e pacientes</span></a>
            <a href="{{ route('services.index') }}" class="{{ request()->routeIs('services.*') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="7" width="18" height="14" rx="3"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12a24 24 0 0 0 18 0m-9 0v4"/></svg><span>Serviços</span></a>
            @endif
            @if(auth()->user()->isOwner())
                <a href="{{ route('clinical.audit') }}" class="{{ request()->routeIs('clinical.audit') ? 'active' : '' }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6Z"/><path d="m8 12 3 3 5-6"/></svg><span>Auditoria clínica</span></a>
                <a class="{{ request()->routeIs('team.*') ? 'active' : '' }}" href="{{ route('team.index') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3"/><path d="M6 21v-2a6 6 0 0 1 12 0v2M4 5a3 3 0 0 0 0 6m16-6a3 3 0 0 1 0 6M2 14v5m20-5v5"/></svg><span>Equipe</span></a>
                <a class="{{ request()->routeIs('company.*') ? 'active' : '' }}" href="{{ route('company.edit') }}"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 7h1m4 0h1m-6 4h1m4 0h1m-5 10v-6h4v6"/></svg><span>Empresa</span></a>
            @endif
            <a href="{{ route('help.index') }}" class="{{ request()->routeIs('help.*') ? 'active' : '' }}"><span aria-hidden="true">?</span><span>Ajuda e manual</span></a>
        </nav>
        <div class="sidebar-footer"><a href="{{ route('password.change') }}">Alterar senha</a>
            <div class="user-mini"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->company->name }}</span></div>
            <form method="post" action="{{ route('logout') }}">@csrf<button class="link-button" type="submit">Sair</button></form>
        </div>
    </aside>
    @endauth
    <main class="content {{ auth()->check() ? '' : 'content-public' }}">
        @auth
        <header class="topbar">
            <div><span class="eyebrow">{{ auth()->user()->company->name }}</span><h1>@yield('heading', 'Agenda')</h1></div>
            <div class="role-badge">{{ ['owner'=>'Proprietário','reception'=>'Recepção','professional'=>'Profissional'][auth()->user()->role] ?? auth()->user()->role }}</div>
        </header>
        @endauth
        @if(session('success'))<div class="alert success">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert danger"><strong>Confira os dados:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
</body>
</html>
