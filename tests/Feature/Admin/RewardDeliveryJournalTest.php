<?php

namespace Tests\Feature\Admin;

use App\Auth\AdminRole;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RewardDeliveryJournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_and_administrator_can_view_reward_journal_but_editor_cannot(): void
    {
        foreach ([AdminRole::Owner, AdminRole::Administrator] as $role) {
            $admin = Admin::factory()->create(['role' => $role]);
            $this->actingAs($admin, 'admin')
                ->get(route('admin.rewards.index'))
                ->assertOk()
                ->assertSee(__('Reward deliveries'));
        }

        $editor = Admin::factory()->create(['role' => AdminRole::Editor]);
        $this->actingAs($editor, 'admin')
            ->get(route('admin.rewards.index'))
            ->assertForbidden();
    }
}
