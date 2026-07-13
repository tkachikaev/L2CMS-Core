@extends('admin.layouts.app')

@section('body')
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="admin-brand-mark">L2</span>
            <span>
                <strong>{{ config('app.name') }}</strong>
                <small>Панель управления</small>
            </span>
        </a>

        @include('admin.partials.navigation')

        <div class="admin-sidebar-footer">
            <a href="{{ route('home') }}" target="_blank" rel="noopener">Открыть сайт <span aria-hidden="true">↗</span></a>
            <span>Версия {{ cms_version() }}</span>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div>
                <h1>@yield('title', 'Панель управления')</h1>
                @hasSection('description')
                    <p>@yield('description')</p>
                @endif
            </div>

            <div class="admin-user">
                <div>
                    <strong>{{ auth('admin')->user()->name }}</strong>
                    <span>{{ auth('admin')->user()->email }}</span>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">Выйти</button>
                </form>
            </div>
        </header>

        @if (session('status'))
            <div class="notice notice-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="notice notice-error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <section class="admin-content">
            @yield('content')
        </section>
    </main>
</div>
@endsection
