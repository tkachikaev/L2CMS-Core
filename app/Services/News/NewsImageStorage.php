<?php

namespace App\Services\News;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

final class NewsImageStorage
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeCover(UploadedFile $file): string
    {
        return $this->store($file, 'news/covers');
    }

    public function storeContent(UploadedFile $file): string
    {
        return $this->store($file, 'news/content');
    }

    public function deleteCover(?string $path): void
    {
        $this->delete($path, 'news/covers/');
    }

    public function publicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return asset('uploads/'.ltrim(str_replace('\\', '/', $path), '/'));
    }

    public function publicPath(string $path): string
    {
        return '/uploads/'.ltrim(str_replace('\\', '/', $path), '/');
    }

    private function store(UploadedFile $file, string $scope): string
    {
        $mime = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;

        if ($extension === null) {
            throw new RuntimeException('Unsupported image MIME type.');
        }

        $directory = $scope.'/'.now()->format('Y/m');
        $filename = Str::uuid()->toString().'.'.$extension;
        $absoluteDirectory = $this->rootPath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

        File::ensureDirectoryExists($absoluteDirectory, 0755, true);
        $file->move($absoluteDirectory, $filename);

        return $directory.'/'.$filename;
    }

    private function rootPath(): string
    {
        return rtrim((string) config('cms.news.uploads_path', public_path('uploads')), '\\/');
    }

    private function delete(?string $path, string $requiredPrefix): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (! str_starts_with($path, $requiredPrefix) || str_contains($path, '..')) {
            return;
        }

        File::delete($this->rootPath().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
