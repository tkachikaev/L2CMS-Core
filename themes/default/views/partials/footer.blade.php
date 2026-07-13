<footer class="site-footer">
    <div class="container footer-grid">
        <div>
            <div class="brand footer-brand">
                @if (site_logo_url())
                    <img class="brand-logo footer-brand-logo" src="{{ site_logo_url() }}" alt="{{ site_name() }}">
                @else
                    <span class="brand-mark">L2</span><span><strong>{{ site_name() }}</strong><small>OPEN SOURCE CMS</small></span>
                @endif
            </div>
            @if (site_description() !== '')
                <p>{{ site_description() }}</p>
            @endif
        </div>
        <div><h3>{{ __('Navigation') }}</h3><a href="{{ public_route('news.index') }}">{{ __('News') }}</a><a href="{{ public_route('downloads') }}">{{ __('Download client') }}</a><a href="{{ public_route('about') }}">{{ __('Server description') }}</a></div>
        <div><h3>{{ __('Documents') }}</h3><a href="#">{{ __('Rules') }}</a><a href="#">{{ __('Privacy') }}</a><a href="#">{{ __('Contacts') }}</a></div>
        <div><h3>{{ __('Community') }}</h3><div class="socials"><a href="#">VK</a><a href="#">Discord</a><a href="#">Telegram</a></div></div>
    </div>
    <div class="container footer-bottom">
        <span>{{ site_footer_text() }}</span>
        <span>{{ __('Lineage II is a trademark of its respective owners.') }}</span>
    </div>
</footer>
