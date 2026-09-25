<?php

/**
 * Сборка production-архива AtlasCMS (atlas.zip) для деплоя на виртуальный хостинг.
 *
 * Запуск: php scripts/build-archive.php
 * Результат: deploy/atlas.zip — один файл, который затем загружается по FTP
 * и распаковывается на сервере (scripts/unpack.php).
 *
 * В архив НЕ попадают: .env*, .git, storage (на сервере свои файлы/загрузки),
 * тесты, документация, исходники фронтенда, кэш bootstrap.
 * vendor/ — ВХОДИТ (на хостинге нет Composer).
 */

$root = dirname(__DIR__);
$outDir = $root.'/deploy';
$outFile = $outDir.'/atlas.zip';

if (! is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

@unlink($outFile);

$zip = new ZipArchive();

if ($zip->open($outFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Не удалось создать архив: {$outFile}\n");
    exit(1);
}

// Папки, которые полностью исключаем
$skipDirs = [
    '/.git',
    '/storage',
    '/tests',
    '/docs',
    '/node_modules',
    '/.github',
    '/scripts',
    '/deploy',
    '/bootstrap/cache',
    '/resources/js',
    '/resources/css',
    '/build',
    '/plans',
    '/screenshots',
];

// Файлы в корне, которые не нужны в production
$skipRootFiles = [
    '.env',
    '.env.example',
    '.env.production',
    '.env.backup',
    '.editorconfig',
    '.gitattributes',
    '.gitignore',
    'package.json',
    'package-lock.json',
    'vite.config.js',
    'phpunit.xml',
    'README.md',
    'LICENSE',
];

$addDir = function (string $base, string $relative) use (&$addDir, $zip, $skipDirs, $skipRootFiles): void {
    $full = $base.'/'.$relative;

    $items = scandir($full);

    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $rel = ltrim($relative.'/'.$item, '/');
        $path = $full.'/'.$item;

        // Корневые скрытые файлы (кроме .htaccess) пропускаем
        if ($relative === '' && str_starts_with($item, '.') && $item !== '.htaccess') {
            continue;
        }

        if (is_dir($path)) {
            if (in_array('/'.$rel, $skipDirs, true)) {
                continue;
            }

            $addDir($base, $rel);

            continue;
        }

        if ($relative === '' && in_array($item, $skipRootFiles, true)) {
            continue;
        }

        $zip->addFile($path, $rel);
    }
};

$addDir($root, '');

$zip->close();

$sizeMb = round(filesize($outFile) / 1024 / 1024, 1);

echo "Архив собран: {$outFile} ({$sizeMb} МБ)\n";