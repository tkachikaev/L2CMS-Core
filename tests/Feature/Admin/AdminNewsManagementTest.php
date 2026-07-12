<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNewsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_news_management(): void
    {
        $this->get('/admin/news')
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_sees_empty_news_state(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/news')
            ->assertOk()
            ->assertSee('Новостей пока нет')
            ->assertSee('Создать первую новость');
    }

    public function test_admin_can_create_news_draft(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/news', [
                'title' => 'Первая новость',
                'excerpt' => '',
                'body' => "Первый абзац.\n\nВторой абзац.",
                'published_at' => '2026-07-12 18:00:00',
                'is_published' => '0',
            ])
            ->assertRedirect(route('admin.news.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('news', [
            'title' => 'Первая новость',
            'is_published' => false,
        ]);

        $createdNews = News::query()->where('title', 'Первая новость')->firstOrFail();
        $this->assertNotSame('', $createdNews->slug);
    }

    public function test_admin_can_edit_and_publish_news(): void
    {
        $admin = $this->createAdmin();
        $news = News::query()->create([
            'title' => 'Черновик',
            'slug' => 'chernovik',
            'excerpt' => 'Черновик',
            'body' => 'Текст',
            'is_published' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/news/'.$news->id, [
                'title' => 'Готовая новость',
                'excerpt' => 'Описание',
                'body' => 'Готовый текст новости.',
                'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'is_published' => '1',
            ])
            ->assertRedirect(route('admin.news.index'));

        $this->assertDatabaseHas('news', [
            'id' => $news->id,
            'title' => 'Готовая новость',
            'slug' => 'chernovik',
            'is_published' => true,
        ]);

        $this->get('/news/chernovik')
            ->assertOk()
            ->assertSee('Готовая новость')
            ->assertSee('Готовый текст новости.');
    }

    public function test_draft_is_not_visible_on_public_site(): void
    {
        News::query()->create([
            'title' => 'Скрытая новость',
            'slug' => 'hidden-news',
            'excerpt' => 'Описание',
            'body' => 'Текст',
            'is_published' => false,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/news/hidden-news')->assertNotFound();
        $this->get('/news')->assertDontSee('Скрытая новость');
    }

    private function createAdmin(): Admin
    {
        return Admin::query()->create([
            'name' => 'Main Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('CorrectPassword123'),
            'is_active' => true,
        ]);
    }
}
