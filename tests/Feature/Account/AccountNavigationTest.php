<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Support\Themes\AccountThemeManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_account_uses_a_persisted_livewire_shell(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->get('/account');

        $response
            ->assertOk()
            ->assertSee('data-account-sidebar', false)
            ->assertSee('data-account-topbar', false)
            ->assertSee('wire:navigate', false)
            ->assertSee('data-navigate-track', false)
            ->assertSee('data-navigate-once="true"', false)
            ->assertSee('account-themes/luxury/assets/js/navigation.js', false)
            ->assertSee('livewire.js?id=', false);

        $themePath = app(AccountThemeManager::class)->themePath();
        $layout = file_get_contents($themePath.'/views/layouts/app.blade.php');
        $navigation = file_get_contents($themePath.'/views/partials/navigation.blade.php');
        $script = file_get_contents(public_path('account-themes/luxury/assets/js/navigation.js'));
        $styles = file_get_contents(public_path('account-themes/luxury/assets/css/app.css'));

        $this->assertIsString($layout);
        $this->assertIsString($navigation);
        $this->assertIsString($script);
        $this->assertIsString($styles);
        $this->assertStringContainsString("@persist('account-sidebar')", $layout);
        $this->assertStringContainsString("@persist('account-topbar')", $layout);
        $this->assertStringContainsString('wire:navigate:scroll', $layout);
        $this->assertStringContainsString('account_theme_asset', $layout);
        $this->assertStringContainsString('wire:navigate.hover', $navigation);
        $this->assertStringContainsString('wire:current.exact="active"', $navigation);
        $this->assertStringContainsString('livewire:navigate', $script);
        $this->assertStringContainsString('livewire:navigated', $script);
        $this->assertStringContainsString('account-is-navigating', $script);
        $this->assertStringContainsString('html.account-is-navigating .account-content', $styles);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $styles);
    }

    public function test_game_account_index_is_available_on_default_and_localized_routes(): void
    {
        $this->get('/account/game-accounts')->assertRedirect(route('login'));

        $user = $this->user();

        $this->actingAs($user)
            ->get('/account/game-accounts')
            ->assertOk()
            ->assertSee('Мои аккаунты')
            ->assertSee('wire:current="active"', false);

        $this->actingAs($user)
            ->get('/ru/account/game-accounts')
            ->assertOk()
            ->assertSee('Мои аккаунты');
    }

    public function test_account_theme_views_use_livewire_navigation_links(): void
    {
        $themePath = base_path('account-themes/luxury/views');
        $views = [
            $themePath.'/dashboard.blade.php',
            $themePath.'/game-accounts/index.blade.php',
            $themePath.'/game-accounts/create.blade.php',
            $themePath.'/game-accounts/show.blade.php',
            $themePath.'/livewire/character-directory.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($view);

            $this->assertIsString($contents);
            $this->assertStringContainsString('wire:navigate', $contents, $view);
        }
    }

    public function test_legacy_core_account_views_and_assets_are_not_used(): void
    {
        $this->assertDirectoryDoesNotExist(resource_path('views/account'));
        $this->assertDirectoryDoesNotExist(resource_path('views/livewire/account'));
        $this->assertDirectoryDoesNotExist(public_path('assets/account'));
    }

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Player Navigation',
            'email' => 'player-navigation@example.test',
            'locale' => 'ru',
        ]);
    }
}
