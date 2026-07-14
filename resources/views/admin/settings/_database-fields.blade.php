@php
    $values = $values ?? [];
    $passwordSaved = $passwordSaved ?? false;
    $disabled = $disabled ?? false;
@endphp

<div class="server-database-fields" @if($disabled) data-server-database-own-fields hidden @endif>
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_database_host">{{ __('Database host') }}</label>
        <input id="{{ $fieldPrefix }}_database_host" name="database_host" type="text" maxlength="255" value="{{ $values['host'] ?? '' }}" placeholder="127.0.0.1" @disabled($disabled)>
    </div>
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_database_port">{{ __('Database port') }}</label>
        <input id="{{ $fieldPrefix }}_database_port" name="database_port" type="number" min="1" max="65535" value="{{ $values['port'] ?? 3306 }}" inputmode="numeric" @disabled($disabled)>
    </div>
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_database_name">{{ __('Database name') }}</label>
        <input id="{{ $fieldPrefix }}_database_name" name="database_name" type="text" maxlength="64" value="{{ $values['name'] ?? '' }}" placeholder="l2jmobius" @disabled($disabled)>
    </div>
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_database_username">{{ __('Database username') }}</label>
        <input id="{{ $fieldPrefix }}_database_username" name="database_username" type="text" maxlength="128" value="{{ $values['username'] ?? '' }}" autocomplete="off" @disabled($disabled)>
    </div>
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_database_password">{{ __('Database password') }}</label>
        <input id="{{ $fieldPrefix }}_database_password" name="database_password" type="password" maxlength="1024" value="" autocomplete="new-password" @disabled($disabled)>
        <small>
            @if($passwordSaved)
                {{ __('A database password is saved. Leave the field empty to keep it.') }}
            @else
                {{ __('The password is encrypted with APP_KEY before it is stored.') }}
            @endif
        </small>
    </div>
    <div class="form-group">
        <label for="{{ $fieldPrefix }}_database_charset">{{ __('Database charset') }}</label>
        <select id="{{ $fieldPrefix }}_database_charset" name="database_charset" @disabled($disabled)>
            @foreach(['utf8mb4', 'utf8', 'latin1', 'cp1251'] as $charset)
                <option value="{{ $charset }}" @selected(($values['charset'] ?? 'utf8mb4') === $charset)>{{ $charset }}</option>
            @endforeach
        </select>
    </div>
</div>
