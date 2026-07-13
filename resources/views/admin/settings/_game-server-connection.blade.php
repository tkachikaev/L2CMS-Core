<details class="settings-connection-placeholder">
    <summary>
        <span><strong>{{ __('Future game database connection') }}</strong><small>{{ __('These fields are not saved or used by the CMS yet.') }}</small></span>
        <span class="settings-coming-soon">{{ __('Coming soon') }}</span>
    </summary>
    <div class="settings-disabled-notice">{{ __('The fields are reserved for a future L2J Mobius connection.') }}</div>
    <fieldset class="settings-disabled-fields" disabled>
        <div class="form-group"><label for="{{ $fieldPrefix }}_database_host">{{ __('Database server address') }}</label><input id="{{ $fieldPrefix }}_database_host" type="text" placeholder="127.0.0.1"></div>
        <div class="form-group"><label for="{{ $fieldPrefix }}_database_port">{{ __('Database port') }}</label><input id="{{ $fieldPrefix }}_database_port" type="text" inputmode="numeric" placeholder="3306"></div>
        <div class="form-group"><label for="{{ $fieldPrefix }}_database_name">{{ __('Game database name') }}</label><input id="{{ $fieldPrefix }}_database_name" type="text"></div>
        <div class="form-group"><label for="{{ $fieldPrefix }}_database_username">{{ __('Game database user') }}</label><input id="{{ $fieldPrefix }}_database_username" type="text"></div>
        <div class="form-group settings-disabled-full"><label for="{{ $fieldPrefix }}_database_password">{{ __('Game database password') }}</label><input id="{{ $fieldPrefix }}_database_password" type="password"></div>
    </fieldset>
    <small class="settings-security-note">{{ __('Secure credential storage will be implemented with the game server adapter.') }}</small>
</details>
