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

    /**
     * Старт сессии обмена для конкретного типа (catalog|sale).
     *
     * Если для типа уже есть валидная сессия — возвращаем её, чтобы
     * повторные checkauth (1С вызывает их по несколько раз) не ломали обмен.
     */
    public function startSession(string $type = 'catalog'): string
    {
        $existing = $this->get('session_id_'.$type);

        if ($existing !== null && $this->hasValidSession($existing, $type)) {
            return (string) $existing;
        }

        $id = Str::random(32);

        $this->set('session_id_'.$type, $id);
        $this->set('started_at_'.$type, now()->toIso8601String());

        return $id;
    }

    public function hasValidSession(?string $sessionId, string $type = 'catalog'): bool
    {
        return $sessionId !== null && $sessionId === $this->get('session_id_'.$type);
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

    /**
     * Путь к файлу с сохранением структуры (import_files/dd/img.png).
     */
    public function dirPath(string $filename): string
    {
        $relative = str_replace(['..', '\\'], '', ltrim($filename, '/\\'));

        return $this->baseDir().'/'.$relative;
    }

    /**
     * Каталог обмена текущего магазина.
     */
    public function directory(): string
    {
        return $this->baseDir();
    }

    /**
     * Распаковка всех zip-архивов в каталоге обмена (картинки общим архивом).
     */
    public function extractZipFiles(): int
    {
        $total = 0;

        foreach (File::glob($this->baseDir().'/*.zip') ?: [] as $archive) {
            $total += $this->extractZip(basename($archive));
            File::delete($archive);
        }

        return $total;
    }

    /**
     * Распаковка ZIP-архива в каталог обмена (картинки приходят общим архивом).
     */
    public function extractZip(string $filename): int
    {
        $path = $this->filePath($filename);

        if (! File::exists($path)) {
            return 0;
        }

        $extracted = 0;

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive;

            if ($zip->open($path) === true) {
                $extracted = $zip->numFiles;
                $zip->extractTo($this->baseDir());
                $zip->close();

                return $extracted;
            }
        }

        try {
            $phar = new \PharData($path);
            $phar->extractTo($this->baseDir(), null, true);
            $extracted = $phar->count();
        } catch (\Throwable $e) {
            throw new \RuntimeException("Не удалось распаковать архив {$filename}: {$e->getMessage()}");
        }

        return $extracted;
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