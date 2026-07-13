@extends('admin.layouts.panel')

@section('title', __('Control panel'))
@section('description', __('One place to access every CMS section.'))

@section('content')
<div class="admin-home-grid">
    <a class="admin-section-card available" href="{{ route('admin.news.index') }}">
        <div><span class="section-status">{{ __('Available') }}</span><h2>{{ __('News') }}</h2><p>{{ __('Create, illustrate and publish website news.') }}</p></div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
    <a class="admin-section-card available" href="{{ route('admin.themes.index') }}">
        <div><span class="section-status">{{ __('Available') }}</span><h2>{{ __('Themes') }}</h2><p>{{ __('Review installed themes and select the public website design.') }}</p></div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
    <a class="admin-section-card available" href="{{ route('admin.settings.general') }}">
        <div><span class="section-status">{{ __('Available') }}</span><h2>{{ __('Settings') }}</h2><p>{{ __('Website text, logo, favicon, time zone, mail and languages.') }}</p></div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
    <a class="admin-section-card available" href="{{ route('admin.users.index') }}">
        <div><span class="section-status">{{ __('Available') }}</span><h2>{{ __('Users') }}</h2><p>{{ __('Search, review activity and manage website accounts.') }}</p></div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
    <article class="admin-section-card planned">
        <div><span class="section-status">{{ __('In development') }}</span><h2>{{ __('Modules') }}</h2><p>{{ __('Manage functional CMS modules.') }}</p></div>
    </article>
    <a class="admin-section-card available" href="{{ route('admin.administrators.index') }}">
        <div><span class="section-status">{{ __('Available') }}</span><h2>{{ __('Administrators') }}</h2><p>{{ __('Create, edit and disable control panel accounts.') }}</p></div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
    <a class="admin-section-card available" href="{{ route('admin.logs.index') }}">
        <div><span class="section-status">{{ __('Available') }}</span><h2>{{ __('Audit log') }}</h2><p>{{ __('Sign-ins, content changes, settings, users, mail and system events.') }}</p></div>
        <span class="section-arrow" aria-hidden="true">→</span>
    </a>
</div>
@endsection
