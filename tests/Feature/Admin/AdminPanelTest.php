<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_root_is_the_main_panel_entry_point(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk()
            ->assertSee('Панель управления')
            ->assertSee('Темы')
            ->assertSee('Новости')
            ->assertSee('Журнал действий');
    }

    public function test_old_dashboard_address_redirects_to_admin_root(): void
    {
        $admin = Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/dashboard')
            ->assertRedirect('/admin');
    }
}
