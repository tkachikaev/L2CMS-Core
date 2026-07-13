<?php

namespace Tests\Feature\Localization;

use App\Models\News;
use App\Models\NewsTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_russian_and_english_public_routes_are_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Главная')
            ->assertSee('Новости');

        $this->get('/en')
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('News')
            ->assertSee('Free open-source CMS for Lineage II servers.');

        $this->get('/en/login')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee('Username or email');
    }

    public function test_language_switch_redirects_to_the_same_localized_path(): void
    {
        $this->get(route('language.switch', [
            'locale' => 'en',
            'return' => '/news?page=2',
        ]))
            ->assertRedirect(url('/en/news?page=2'))
            ->assertSessionHas('locale', 'en');
    }

    public function test_unknown_or_disabled_locales_are_not_exposed(): void
    {
        $this->get('/de')->assertNotFound();
        $this->get('/language/de')->assertNotFound();
    }

    public function test_language_switch_updates_authenticated_user_preference(): void
    {
        $user = User::query()->create([
            'name' => 'localized_player',
            'email' => 'localized@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('Password123'),
            'is_active' => true,
            'locale' => 'ru',
        ]);

        $this->actingAs($user)
            ->get(route('language.switch', ['locale' => 'en', 'return' => '/account']))
            ->assertRedirect(url('/en/account'));

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_news_can_have_different_slugs_and_content_per_language(): void
    {
        $news = News::query()->create([
            'title' => 'Русская новость',
            'slug' => 'russian-news',
            'excerpt' => 'Русское описание',
            'body' => '<p>Русский текст новости.</p>',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        NewsTranslation::query()->create([
            'news_id' => $news->id,
            'locale' => 'ru',
            'title' => 'Русская новость',
            'slug' => 'russian-news',
            'excerpt' => 'Русское описание',
            'body' => '<p>Русский текст новости.</p>',
        ]);
        NewsTranslation::query()->create([
            'news_id' => $news->id,
            'locale' => 'en',
            'title' => 'English news',
            'slug' => 'english-news',
            'excerpt' => 'English description',
            'body' => '<p>English news body.</p>',
        ]);

        $this->get('/ru/news/russian-news')
            ->assertOk()
            ->assertSee('Русская новость')
            ->assertSee('Русский текст новости.');

        $this->get('/en/news/english-news')
            ->assertOk()
            ->assertSee('English news')
            ->assertSee('English news body.')
            ->assertDontSee('Русский текст новости.');

        $this->get('/en/news')
            ->assertOk()
            ->assertSee('/en/news/english-news', false);
    }
}
