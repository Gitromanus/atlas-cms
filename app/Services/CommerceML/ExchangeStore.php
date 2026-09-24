<?php

namespace App\Services\CommerceML;

use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Хранилище файлов и состояния обмена для конкретного магазина.
 *
 * Все файлы CommerceML сохраняются в storage/app/1c/{tenant_slug}/,
 * состояние сессии — в JSON-файле state.json в той же папке.
 */
class ExchangeStore
{
    public function __construct(protected Tenant $tenant) {}

    protected function baseDir(): string
    {
        $dir = storage_path('app/'.config('atlas.onec.storage_dir').'/'.$this->tenant->slug);

        File::ensureDirectoryExists($dir);

        return $dir;
    }

    protected function stateFile(): string
    {
        return $this->baseDir().'/state.json';
    }

    public function startSession(): string
    {
        $id = Str::random(32);

        $this->set('session_id', $id);
        $this->set('started_at', now()->toIso8601String());

        return $id;
    }

    public function hasValidSession(?string $sessionId): bool
    {
        return $sessionId !== null && $sessionId === $this->get('session_id');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! File::exists($this->stateFile())) {
            return $default;
        }

        $data = json_decode((string) File::get($this->stateFile()), true) ?: [];

        return data_get($data, $key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $data = File::exists($this->stateFile())
            ? (json_decode((string) File::get($this->stateFile()), true) ?: [])
            : [];

        data_set($data, $key, $value);

        File::put($this->stateFile(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Дозапись содержимого в файл (файлы от 1С передаются частями).
     */
    public function appendToFile(string $filename, string $contents): void
    {
        File::append($this->filePath($filename), $contents);
    }

    public function filePath(string $filename): string
    {
        return $this->baseDir().'/'.basename($filename);
    }

    public function hasFile(string $filename): bool
    {
        return File::exists($this->filePath($filename));
    }

    public function deleteFile(string $filename): void
    {
        $path = $this->filePath($filename);

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public function clearFiles(): void
    {
        File::cleanDirectory($this->baseDir());
    }

    public function getTenantId(): int
    {
        return $this->tenant->id;
    }
}