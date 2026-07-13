<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_login_page_is_available(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Вход администратора')
            ->assertSee('login-brand-top', false)
            ->assertSee('login-brand-copy', false);
    }

    public function test_active_admin_can_login(): void
    {
        $admin = $this->createAdmin();

        $this->post('/admin/login', [
            'email' => 'ADMIN@example.com',
            'password' => 'CorrectPassword123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertDatabaseHas('admin_login_logs', [
            'admin_id' => $admin->id,
            'successful' => true,
        ]);
    }

    public function test_invalid_password_is_rejected_and_logged(): void
    {
        $admin = $this->createAdmin();

        $this->from('/admin/login')->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'WrongPassword123',
        ])->assertRedirect(route('admin.login'))->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertDatabaseHas('admin_login_logs', [
            'admin_id' => $admin->id,
            'successful' => false,
            'failure_reason' => 'invalid_credentials',
        ]);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $admin = $this->createAdmin(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'CorrectPassword123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertDatabaseHas('admin_login_logs', [
            'admin_id' => $admin->id,
            'successful' => false,
            'failure_reason' => 'inactive',
        ]);
    }

    public function test_admin_can_logout(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $admin = $this->createAdmin();

        foreach (range(1, 5) as $attempt) {
            $this->post('/admin/login', [
                'email' => $admin->email,
                'password' => 'WrongPassword123',
            ]);
        }

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'CorrectPassword123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
        $this->assertSame(6, AdminLoginLog::query()->count());
    }

    private function createAdmin(array $attributes = []): Admin
    {
        return Admin::query()->create(array_merge([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ], $attributes));
    }
}
