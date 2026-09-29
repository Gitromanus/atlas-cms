<?php

namespace App\Services\CommerceML;

use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

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

    public function appendToFile(string $filename, string $contents): void
    {
        $path = $this->safePath($filename);
        File::ensureDirectoryExists(dirname($path));
        File::append($path, $contents);
    }

    /**
     * @param  resource  $stream
     */
    public function appendStream(string $filename, $stream): int
    {
        if (! is_resource($stream)) {
            return 0;
        }

        $path = $this->safePath($filename);
        File::ensureDirectoryExists(dirname($path));

        $out = fopen($path, 'ab');
        if ($out === false) {
            return 0;
        }

        $bytes = stream_copy_to_stream($stream, $out);
        fclose($out);

        return (int) $bytes;
    }

    public function filePath(string $filename): string
    {
        return $this->baseDir().'/'.basename(str_replace(chr(92), '/', $filename));
    }

    public function dirPath(string $filename): string
    {
        return $this->safePath($filename);
    }

    public function safePath(string $filename): string
    {
        $relative = str_replace([chr(92), '..'], ['/', ''], $filename);
        $relative = ltrim($relative, '/');
        $relative = preg_replace('#/+#', '/', $relative) ?: basename($filename);

        if (! str_contains($relative, '/')) {
            return $this->baseDir().'/'.$relative;
        }

        return $this->baseDir().'/'.$relative;
    }

    public function directory(): string
    {
        return $this->baseDir();
    }

    public function extractZipFiles(): int
    {
        $total = 0;

        foreach (File::glob($this->baseDir().'/*.zip') ?: [] as $archive) {
            $total += $this->extractZip(basename($archive));
            File::delete($archive);
        }

        return $total;
    }

    public function extractZip(string $filename): int
    {
        $path = $this->safePath($filename);

        if (! File::exists($path)) {
            $path = $this->filePath($filename);
        }

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
        return File::exists($this->safePath($filename))
            || File::exists($this->filePath($filename));
    }

    public function deleteFile(string $filename): void
    {
        foreach ([$this->safePath($filename), $this->filePath($filename)] as $path) {
            if (File::exists($path) && is_file($path)) {
                File::delete($path);
            }
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
