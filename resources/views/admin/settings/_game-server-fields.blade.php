@php
    $translationValues = $translationValues ?? [];
    $hasOldInput = $hasOldInput ?? false;
@endphp

<div class="translation-editor compact" data-locale-tabs>
    <div class="translation-tabs" role="tablist" aria-label="{{ __('Server name language') }}">
        @foreach ($languages as $code => $language)
            <button type="button" role="tab" @class(['translation-tab', 'active' => $code === $defaultLocale]) data-locale-tab="{{ $code }}" aria-selected="{{ $code === $defaultLocale ? 'true' : 'false' }}">
                {{ $language['native_name'] }}
                @if ($code === $defaultLocale)<small>{{ __('default') }}</small>@endif
            </button>
        @endforeach
    </div>

    @foreach ($languages as $code => $language)
        @php
            $name = $hasOldInput
                ? old('translations.'.$code.'.name', '')
                : ($translationValues[$code] ?? '');
        @endphp
        <div role="tabpanel" @class(['translation-panel', 'active' => $code === $defaultLocale]) data-locale-panel="{{ $code }}" @if($code !== $defaultLocale) hidden @endif>
            <div class="form-group">
                <label for="{{ $fieldPrefix }}_name_{{ $code }}">{{ __('Server name') }}</label>
                <input
                    id="{{ $fieldPrefix }}_name_{{ $code }}"
                    name="translations[{{ $code }}][name]"
                    type="text"
                    maxlength="100"
                    value="{{ $name }}"
                    placeholder="L2 Eternal x5"
                    @if($code === $defaultLocale) required @endif
                >
                <small>{{ __('The game world name shown to website visitors.') }}</small>
            </div>
        </div>
    @endforeach
</div>

<div class="settings-server-fields">
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_rates">{{ __('Server rates') }}</label>
        <input id="{{ $fieldPrefix }}_rates" name="server_rates" type="text" maxlength="100" value="{{ $values['rates'] }}" placeholder="x5">
        <small>{{ __('Optional. An empty value is hidden in the theme.') }}</small>
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}_chronicle">{{ __('Chronicle') }}</label>
        <input id="{{ $fieldPrefix }}_chronicle" name="server_chronicle" type="text" maxlength="100" value="{{ $values['chronicle'] }}" placeholder="High Five">
        <small>{{ __('Optional. The value is displayed as the game chronicle.') }}</small>
    </div>

    <div class="form-group">
        <label for="{{ $fieldPrefix }}_mode">{{ __('Mode') }}</label>
        <input id="{{ $fieldPrefix }}_mode" name="server_mode" type="text" maxlength="100" value="{{ $values['mode'] }}" placeholder="PvP, PvE, Craft or None">
        <small>{!! __('Enter <strong>None</strong> or leave the field empty to hide the mode.') !!}</small>
    </div>
</div>
