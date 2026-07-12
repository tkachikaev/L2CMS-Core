<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminNewsManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $uploadRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadRoot = storage_path('framework/testing/news-uploads');
        File::deleteDirectory($this->uploadRoot);
        config()->set('cms.news.uploads_path', $this->uploadRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->uploadRoot);

        parent::tearDown();
    }

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

    public function test_admin_can_create_formatted_news_and_unsafe_html_is_removed(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/news', [
                'title' => 'Первая новость',
                'excerpt' => '',
                'body' => '<h2 data-align="center">Заголовок</h2><p onclick="alert(1)">Текст <strong>важный</strong><script>alert(1)</script> <span data-color="gold">золотой</span></p><iframe src="https://example.com"></iframe>',
                'published_at' => '2026-07-12 18:00:00',
                'is_published' => '0',
                'remove_cover_image' => '0',
            ])
            ->assertRedirect(route('admin.news.index'))
            ->assertSessionHas('status');

        $createdNews = News::query()->where('title', 'Первая новость')->firstOrFail();

        $this->assertStringContainsString('<strong>важный</strong>', $createdNews->body);
        $this->assertStringContainsString('data-color="gold"', $createdNews->body);
        $this->assertStringNotContainsString('<script', $createdNews->body);
        $this->assertStringNotContainsString('<iframe', $createdNews->body);
        $this->assertStringNotContainsString('onclick', $createdNews->body);
        $this->assertNotSame('', $createdNews->slug);
        $this->assertStringContainsString('Заголовок', $createdNews->excerpt);
    }

    public function test_admin_can_upload_cover_image(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/news', [
                'title' => 'Новость с обложкой',
                'excerpt' => 'Описание',
                'body' => '<p>Текст новости.</p>',
                'cover_image' => $this->pngUpload('cover.png'),
                'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'is_published' => '1',
                'remove_cover_image' => '0',
            ])
            ->assertRedirect(route('admin.news.index'));

        $news = News::query()->where('title', 'Новость с обложкой')->firstOrFail();

        $this->assertNotNull($news->image);
        $this->assertMatchesRegularExpression('~^news/covers/\d{4}/\d{2}/[a-f0-9-]+\.png$~', $news->image);
        $this->assertFileExists($this->uploadRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $news->image));

        $this->get('/news/'.$news->slug)
            ->assertOk()
            ->assertSee('/uploads/'.$news->image, false);
    }

    public function test_admin_can_upload_an_inline_news_image(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'admin')
            ->withHeader('Accept', 'application/json')
            ->post('/admin/news/images', [
                'image' => $this->pngUpload('inside.png'),
            ])
            ->assertCreated()
            ->assertJsonStructure(['url', 'path']);

        $path = $response->json('path');
        $this->assertMatchesRegularExpression('~^news/content/\d{4}/\d{2}/[a-f0-9-]+\.png$~', $path);
        $this->assertSame('/uploads/'.$path, $response->json('url'));
        $this->assertFileExists($this->uploadRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    public function test_svg_upload_is_rejected(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->withHeader('Accept', 'application/json')
            ->post('/admin/news/images', [
                'image' => UploadedFile::fake()->createWithContent(
                    'danger.svg',
                    '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
                ),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_admin_can_edit_and_publish_news(): void
    {
        $admin = $this->createAdmin();
        $news = News::query()->create([
            'title' => 'Черновик',
            'slug' => 'chernovik',
            'excerpt' => 'Черновик',
            'body' => '<p>Текст</p>',
            'is_published' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/news/'.$news->id, [
                'title' => 'Готовая новость',
                'excerpt' => 'Описание',
                'body' => '<p>Готовый <strong>текст</strong> новости.</p>',
                'published_at' => now()->subMinute()->format('Y-m-d H:i:s'),
                'is_published' => '1',
                'remove_cover_image' => '0',
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
            ->assertSee('<strong>текст</strong>', false);
    }

    public function test_cleanup_command_removes_only_old_unreferenced_inline_images(): void
    {
        $directory = $this->uploadRoot.DIRECTORY_SEPARATOR.'news'.DIRECTORY_SEPARATOR.'content'.DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'07';
        File::ensureDirectoryExists($directory);

        $referencedName = '123e4567-e89b-12d3-a456-426614174000.png';
        $orphanName = '223e4567-e89b-12d3-a456-426614174000.png';
        $referencedFile = $directory.DIRECTORY_SEPARATOR.$referencedName;
        $orphanFile = $directory.DIRECTORY_SEPARATOR.$orphanName;

        File::put($referencedFile, 'referenced');
        File::put($orphanFile, 'orphan');
        touch($referencedFile, now()->subDays(2)->getTimestamp());
        touch($orphanFile, now()->subDays(2)->getTimestamp());

        News::query()->create([
            'title' => 'Новость с картинкой',
            'slug' => 'news-with-image',
            'excerpt' => 'Описание',
            'body' => '<p>Текст</p><figure><img src="/uploads/news/content/2026/07/'.$referencedName.'" alt=""></figure>',
            'is_published' => false,
        ]);

        $this->artisan('l2forge:news-media-clean', ['--hours' => 1])
            ->assertSuccessful();

        $this->assertFileExists($referencedFile);
        $this->assertFileDoesNotExist($orphanFile);
    }

    public function test_public_render_sanitizes_body_again_as_defence_in_depth(): void
    {
        News::query()->create([
            'title' => 'Проверка безопасности',
            'slug' => 'security-check',
            'excerpt' => 'Описание',
            'body' => '<p>Безопасный текст</p><script>alert(1)</script><img src="https://example.com/tracker.png" onerror="alert(2)">',
            'is_published' => true,
            'published_at' => now()->subMinute(),
        ]);

        $this->get('/news/security-check')
            ->assertOk()
            ->assertSee('Безопасный текст')
            ->assertDontSee('alert(1)', false)
            ->assertDontSee('tracker.png', false)
            ->assertDontSee('onerror', false);
    }

    public function test_draft_is_not_visible_on_public_site(): void
    {
        News::query()->create([
            'title' => 'Скрытая новость',
            'slug' => 'hidden-news',
            'excerpt' => 'Описание',
            'body' => '<p>Текст</p>',
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

    private function pngUpload(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2V1sAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png ?: '');
    }
}
