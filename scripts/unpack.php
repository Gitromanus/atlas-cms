<?php

/**
 * Временный распаковщик деплоя AtlasCMS.
 *
 * Вызывается один раз после загрузки atlas.zip по FTP:
 *   http://site/_unpack.php?key=__UNPACK_KEY__
 *
 * - проверяет ключ (CI подставляет реальное значение вместо __UNPACK_KEY__);
 * - распаковывает atlas.zip в текущую папку (корень сайта = public_html);
 * - создаёт структуру storage/ (если её ещё нет);
 * - удаляет стартовую страницу хостинга index.html/index.htm;
 * - удаляет сам скрипт и архив.
 *
 * Никакого вывода в HTML — только plain text.
 */

header('Content-Type: text/plain; charset=utf-8');

$expectedKey = '__UNPACK_KEY__';
$providedKey = (string) ($_GET['key'] ?? '');

if ($expectedKey === '__UNPACK_KEY__' || ! hash_equals($expectedKey, $providedKey)) {
    http_response_code(403);
    echo "forbidden\n";
    exit;
}

$dir = __DIR__;
$archive = $dir.'/atlas.zip';
$log = [];

if (! class_exists('ZipArchive')) {
    http_response_code(500);
    echo "zip extension is not available\n";
    exit;
}

if (! is_file($archive)) {
    http_response_code(500);
    echo "atlas.zip not found in ".$dir."\n";
    exit;
}

$zip = new ZipArchive();

if ($zip->open($archive) !== true) {
    http_response_code(500);
    echo "cannot open archive\n";
    exit;
}

if (! $zip->extractTo($dir)) {
    http_response_code(500);
    echo "extract failed\n";
    exit;
}

$zip->close();

// Структура storage/, которую Laravel требует на запись
foreach ([
    'storage/app/public',
    'storage/app/1c',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $path) {
    $full = $dir.'/'.$path;

    if (! is_dir($full)) {
        @mkdir($full, 0775, true);
    }

    @chmod($full, 0775);
}

// Удаляем стартовую страницу хостинга, если она осталась
foreach (['index.html', 'index.htm', 'default.php'] as $stub) {
    $path = $dir.'/'.$stub;

    if (is_file($path)) {
        @unlink($path);
        $log[] = 'removed '.$stub;
    }
}

@unlink($archive);
@unlink(__FILE__);

echo "ok\n";
echo implode("\n", $log);