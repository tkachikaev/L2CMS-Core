@extends('theme::layouts.app')

@section('title', 'Регистрация отключена — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card auth-message-card">
        <p class="eyebrow">РЕГИСТРАЦИЯ</p>
        <h1>Регистрация отключена</h1>
        <p class="muted">{{ $reason ?? 'Администрация сайта временно закрыла создание новых учётных записей.' }}</p>
        <a class="button button-gold" href="{{ route('home') }}">Вернуться на главную</a>
    </div>
</section>
@endsection
