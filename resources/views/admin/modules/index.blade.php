@extends('admin.layouts.panel')

@section('title', __('Modules'))
@section('description', __('Extensions discovered in the modules directory. Only trusted modules should be enabled.'))

@section('content')
<div class="admin-overview content-toolbar modules-toolbar">
    <div class="admin-overview-stat content-stat"><span>{{ __('Discovered') }}</span><strong>{{ count($modules) }}</strong></div>
    <div class="admin-overview-stat content-stat"><span>{{ __('Enabled') }}</span><strong>{{ $enabledCount }}</strong></div>
    <div class="admin-overview-stat content-stat"><span>{{ __('Problems') }}</span><strong>{{ $problemCount }}</strong></div>
    <p class="admin-overview-copy">{{ __('Modules execute trusted PHP code after activation. Copy modules to the modules directory only from sources you trust.') }}</p>
</div>

<div class="notice notice-info module-foundation-notice">
    <strong>{{ __('Module foundation') }}</strong>
    <span>{{ __('KaevCMS validates manifests, compatibility and paths before loading a module. Browser ZIP installation and automatic data removal are intentionally unavailable in this release.') }}</span>
</div>

@if ($modules === [])
    <div class="admin-empty-state empty-box">
        {!! __('No modules found. Copy a module directory containing <code>module.json</code> into the <code>modules</code> directory.') !!}
    </div>
@else
    <div class="admin-card-grid theme-grid module-grid">
        @foreach ($modules as $module)
            <article @class([
                'admin-card-row',
                'theme-card',
                'module-card',
                'active' => $module['enabled'],
                'invalid' => in_array($module['status'], ['invalid', 'missing'], true),
            ])>
                <div class="theme-preview module-mark" aria-hidden="true">
                    <div class="theme-preview-placeholder"><span>{{ mb_strtoupper(mb_substr($module['name'], 0, 1)) }}</span></div>
                </div>

                <div class="theme-card-body">
                    <div class="admin-card-heading theme-card-heading">
                        <div>
                            <h2>{{ $module['name'] }}</h2>
                            <p>{{ $module['description'] ?: __('No module description.') }}</p>
                        </div>

                        @switch($module['status'])
                            @case('enabled')<span class="theme-state active">{{ __('Enabled') }}</span>@break
                            @case('disabled')<span class="theme-state ready">{{ __('Disabled') }}</span>@break
                            @case('update_pending')<span class="theme-state ready">{{ __('Update approval required') }}</span>@break
                            @case('runtime_error')<span class="theme-state error">{{ __('Runtime error') }}</span>@break
                            @case('incompatible')<span class="theme-state error">{{ __('Incompatible') }}</span>@break
                            @case('missing')<span class="theme-state error">{{ __('Files missing') }}</span>@break
                            @default<span class="theme-state error">{{ __('Damaged') }}</span>
                        @endswitch
                    </div>

                    <div class="theme-meta module-meta">
                        <span>{{ __('Version :version', ['version' => $module['version']]) }}</span>
                        <span>{{ __('Author: :author', ['author' => $module['author']]) }}</span>
                        <span>{{ __('Identifier: :id', ['id' => $module['id']]) }}</span>
                        <span>{{ __('CMS compatibility: :range', ['range' => ($module['cms_min'] ?: '—').' — '.($module['cms_max'] ?: '∞')]) }}</span>
                    </div>

                    @if ($module['capabilities'] !== [])
                        <div class="module-capabilities" aria-label="{{ __('Module capabilities') }}">
                            @foreach ($module['capabilities'] as $capability)
                                <span>{{ match($capability) {
                                    'autoload' => __('PSR-4 autoload'),
                                    'bootstrap' => __('Bootstrap services'),
                                    'views' => __('Views'),
                                    'lang' => __('Translations'),
                                    'web_routes' => __('Public routes'),
                                    'admin_routes' => __('Administrator routes'),
                                    default => $capability,
                                } }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if ($module['update_available'])
                        <div class="notice notice-warning"><p>{{ __('Module files contain version :available, while version :stored was last enabled.', ['available' => $module['version'], 'stored' => $module['stored_version']]) }}</p></div>
                    @endif

                    @if ($module['last_error'])
                        <div class="notice notice-error"><p>{{ __('Module loading failed: :error. KaevCMS remained available and will retry automatically.', ['error' => $module['last_error']]) }}</p></div>
                    @endif

                    @if ($module['errors'] !== [])
                        <div class="notice notice-error">
                            @foreach ($module['errors'] as $error)<p>{{ $error }}</p>@endforeach
                        </div>
                    @endif

                    <div class="admin-row-actions theme-actions module-actions">
                        @if ($module['can_enable'])
                            <form method="POST" action="{{ route('admin.modules.enable', ['module' => $module['id']]) }}">
                                @csrf
                                <button class="button button-primary" type="submit">{{ $module['update_available'] ? __('Approve update') : __('Enable') }}</button>
                            </form>
                        @endif

                        @if ($module['can_disable'])
                            <form method="POST" action="{{ route('admin.modules.disable', ['module' => $module['id']]) }}">
                                @csrf
                                @method('DELETE')
                                <button class="button button-danger" type="submit">{{ __('Disable') }}</button>
                            </form>
                        @endif

                        @if (! $module['can_enable'] && ! $module['can_disable'])
                            <button class="button button-secondary" type="button" disabled>{{ __('Unavailable') }}</button>
                        @endif
                    </div>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
