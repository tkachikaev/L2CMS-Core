@extends('account.layouts.app')
@section('title', $account->game_login)
@section('content')
<div class="account-page-heading compact">
    <a href="{{ public_route('account') }}">← {{ __('My accounts') }}</a>
    <span class="account-eyebrow">{{ __('Game account') }}</span>
    <div class="account-title-row">
        <h1>{{ $account->game_login }}</h1>
        @if(is_array($summary))
            <span class="account-chip {{ $summary['status'] === 'active' ? 'success' : 'danger' }}">
                {{ $summary['status'] === 'active' ? __('Active') : __('Blocked') }}
            </span>
        @endif
    </div>
    <p>{{ $account->registrationGameServer?->nameFor() ?? $account->loginServer->name }}</p>
</div>

<section class="account-summary-card">
    <dl>
        <div><dt>{{ __('LoginServer') }}</dt><dd>{{ $account->loginServer->name }}</dd></div>
        <div><dt>{{ __('Linked to CMS') }}</dt><dd>{{ $account->created_at?->format('d.m.Y H:i') }}</dd></div>
        <div><dt>{{ __('Created on server') }}</dt><dd>{{ is_array($summary) && $summary['created_at'] ? $summary['created_at'] : '—' }}</dd></div>
        <div><dt>{{ __('Available worlds') }}</dt><dd>{{ count($worlds) }}</dd></div>
    </dl>
    @if($summaryUnavailable)
        <div class="account-inline-warning">{{ __('LoginServer data is temporarily unavailable. The account link remains safe in CMS.') }}</div>
    @endif
</section>

<section class="account-section">
    <div class="account-section-heading"><div><span class="account-eyebrow">{{ __('Characters') }}</span><h2>{{ __('Characters by world') }}</h2></div></div>
    @forelse($worlds as $world)
        <article class="world-card">
            <header><div><h3>{{ $world['server']->nameFor() }}</h3><p>{{ $world['server']->chronicle }} @if($world['server']->rates)· {{ $world['server']->rates }}@endif</p></div><span>{{ count($world['characters']) }}</span></header>
            @if(!$world['available'])
                <div class="world-empty">{{ __('Character data is temporarily unavailable.') }}</div>
            @elseif($world['characters'] === [])
                <div class="world-empty">{{ __('No characters on this world.') }}</div>
            @else
                <div class="character-list">
                    @foreach($world['characters'] as $character)
                        <div class="character-row">
                            <span class="character-avatar">{{ mb_strtoupper(mb_substr($character['name'], 0, 1)) }}</span>
                            <div class="character-main"><strong>{{ $character['name'] }}</strong><small>{{ $character['class_name'] }} @if($character['clan'])· {{ $character['clan'] }}@endif</small></div>
                            <div class="character-level"><span>{{ __('Level') }}</span><strong>{{ $character['level'] }}</strong></div>
                            <span class="online-state {{ $character['online'] ? 'online' : '' }}">{{ $character['online'] ? __('Online') : __('Offline') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </article>
    @empty
        <div class="account-empty small"><h3>{{ __('No available game worlds') }}</h3><p>{{ __('Ask the administrator to configure a GameServer connection.') }}</p></div>
    @endforelse
</section>

<section class="account-section password-section">
    <div class="account-section-heading"><div><span class="account-eyebrow">{{ __('Security') }}</span><h2>{{ __('Change game password') }}</h2></div></div>
    <form class="account-form-card compact" method="POST" action="{{ public_route('game-accounts.password', ['gameAccount' => $account]) }}">
        @csrf
        @method('PUT')
        <label><span>{{ __('Current CMS password') }}</span><input type="password" name="current_password" autocomplete="current-password" required></label>
        <div class="account-form-grid">
            <label><span>{{ __('New game password') }}</span><input type="password" name="game_password" autocomplete="new-password" required></label>
            <label><span>{{ __('Repeat game password') }}</span><input type="password" name="game_password_confirmation" autocomplete="new-password" required></label>
        </div>
        <div class="account-form-actions"><button class="account-button primary" type="submit">{{ __('Change password') }}</button></div>
    </form>
</section>
@endsection
