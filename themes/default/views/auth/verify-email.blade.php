@extends('theme::layouts.app')

@section('title', 'Подтверждение email — '.site_name())

@section('content')
<section class="auth-page">
    <div class="panel auth-card auth-message-card">
        <p class="eyebrow">БЕЗОПАСНОСТЬ</p>
        <h1>Подтвердите email</h1>
        <p class="muted">Ссылка подтверждения отправлена на <strong>{{ auth()->user()->email }}</strong>. После перехода по ссылке откроется личный кабинет.</p>

        @if ($mailReady)
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button class="button button-gold" type="submit">Отправить письмо повторно</button>
            </form>
        @else
            <p class="auth-info auth-info-error">Отправка почты временно недоступна. Обратитесь к администрации сайта.</p>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="button button-ghost" type="submit">Выйти</button>
        </form>
    </div>
</section>
@endsection
