<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Mage Admin') · Mage AI Studio</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-left">
            <div class="admin-header-brand">
                <h1 class="admin-brand-title">Mage AI Studio</h1>
                <span class="admin-brand-subtitle">Admin Panel</span>
            </div>
            <nav class="admin-nav">
                <a href="{{ route('admin.files') }}" class="@if(request()->routeIs('admin.files')) active @endif">Files</a>
                <a href="{{ route('admin.beat-match-video') }}" class="@if(request()->routeIs('admin.beat-match-video*')) active @endif">Beat Match</a>
            </nav>
        </div>
        <div class="admin-header-right">
            @php
                $user = auth()->user();
            @endphp
            @if($user)
                <div class="admin-user-info">
                    <span class="admin-user-name">{{ $user->email ?? $user->login ?? 'Admin' }}</span>
                    <a href="#" onclick="event.preventDefault(); document.getElementById('admin-logout-form').submit();" class="admin-logout-btn">Logout</a>
                    <form id="admin-logout-form" method="POST" action="/api/auth/logout" style="display: none;">
                        @csrf
                    </form>
                </div>
            @else
                <a href="/login" class="admin-login-link">Login</a>
            @endif
        </div>
    </header>

    <main class="admin-container">
        @hasSection('breadcrumbs')
            @yield('breadcrumbs')
        @else
            <x-breadcrumbs :items="[
                ['label' => 'Admin', 'url' => route('admin.files')],
                ['label' => $pageTitle ?? 'Dashboard']
            ]" />
        @endif
        @yield('content')
    </main>

    @stack('scripts')
</body>
</html>

