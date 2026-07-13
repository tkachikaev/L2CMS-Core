@extends('admin.layouts.app')

@section('body')
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="{{ route('admin.dashboard') }}">
            <span class="admin-brand-mark">L2</span>
            <span>
                <strong>{{ config('app.name') }}</strong>
                <small>{{ __('Control panel') }}</small>
            </span>
        </a>

        @include('admin.partials.navigation')

        <div class="admin-sidebar-footer">
            <a href="{{ public_route('home') }}" target="_blank" rel="noopener">{{ __('Open website') }} <span aria-hidden="true">↗</span></a>
            <span>{{ __('Version :version', ['version' => cms_version()]) }}</span>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-header">
            <div>
                <h1>@yield('title', __('Control panel'))</h1>
                @hasSection('description')
                    <p>@yield('description')</p>
                @endif
            </div>

            <div class="admin-user">
                @include('admin.partials.language-switcher')
                <div>
                    <strong>{{ auth('admin')->user()->name }}</strong>
                    <span>{{ auth('admin')->user()->email }}</span>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button class="button button-secondary" type="submit">{{ __('Sign out') }}</button>
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
