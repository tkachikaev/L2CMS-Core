@extends('theme::layouts.app')

@section('title', 'Личный кабинет — '.site_name())

@section('content')
<section class="page-hero"><div class="container"><p class="eyebrow">УЧЁТНАЯ ЗАПИСЬ САЙТА</p><h1>Личный кабинет</h1></div></section>
<section class="container page-content account-page">
    <div class="panel account-card">
        <div class="account-heading">
            <div>
                <span class="account-label">Пользователь</span>
                <h2>{{ $user->name }}</h2>
            </div>
            <span class="account-status {{ $user->hasVerifiedEmail() ? 'verified' : 'unverified' }}">
                {{ $user->hasVerifiedEmail() ? 'Email подтверждён' : 'Email не подтверждён' }}
            </span>
        </div>

        <dl class="account-details">
            <div><dt>Логин</dt><dd>{{ $user->name }}</dd></div>
            <div><dt>Email</dt><dd>{{ $user->email }}</dd></div>
            <div><dt>Дата регистрации</dt><dd>{{ $user->created_at?->format('d.m.Y H:i') }}</dd></div>
            <div><dt>Игровой аккаунт</dt><dd>Создаётся отдельно</dd></div>
        </dl>

        <div class="account-note">
            Учётная запись сайта не является игровым аккаунтом Lineage II. Раздел игровых аккаунтов будет подключён отдельно.
        </div>
    </div>
</section>
@endsection
