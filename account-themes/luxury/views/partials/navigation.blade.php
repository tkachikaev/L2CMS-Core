<nav class="account-nav" aria-label="{{ __('Player account navigation') }}">
    <span class="account-nav-label">{{ __('Main') }}</span>
    <a wire:navigate.hover wire:current.exact="active" href="{{ public_route('account') }}">
        <span class="account-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" role="img"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6h-4v6H5a1 1 0 0 1-1-1z"/></svg>
        </span>
        <span><strong>{{ __('Overview') }}</strong><small>{{ __('Characters and summary') }}</small></span>
    </a>

    <span class="account-nav-label">{{ __('Game') }}</span>
    <a wire:navigate.hover wire:current="active" href="{{ public_route('game-accounts.index') }}">
        <span class="account-nav-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" role="img"><path d="M7 4h10a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Zm1 5v6m-3-3h6m5-3h.01M18 13h.01"/></svg>
        </span>
        <span><strong>{{ __('Game accounts') }}</strong><small>{{ __('Accounts and passwords') }}</small></span>
    </a>
</nav>
