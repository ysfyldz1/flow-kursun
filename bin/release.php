<?php

// Kullanım: php bin/release.php         → dist/flow-<sürüm>.zip: git'teki dosyalar + vendor/ (Git'siz kurulum)
//           php bin/release.php vendor  → dist/flow-vendor.zip: yalnız vendor/ (cPanel Git ile kurulumda bir kez yüklenir)
// config.php ve storage içeriği dahil değildir.

$root = dirname(__DIR__);
chdir($root);
$version = trim((string) file_get_contents('VERSION'));
$vendorOnly = ($argv[1] ?? '') === 'vendor';
$files = [];
if (!$vendorOnly) {
    exec('git ls-files', $files, $code);
    if ($code !== 0) {
        exit("git ls-files çalışmadı\n");
    }
}
if (!is_dir('dist')) {
    mkdir('dist');
}
$target = $vendorOnly ? 'dist/flow-vendor.zip' : "dist/flow-$version.zip";
@unlink($target);

$zip = new ZipArchive();
$zip->open($target, ZipArchive::CREATE);
foreach ($files as $file) {
    $zip->addFile($file, $file);
}
$vendor = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('vendor', FilesystemIterator::SKIP_DOTS));
foreach ($vendor as $file) {
    if ($file->isFile()) {
        $path = str_replace('\\', '/', $file->getPathname());
        $zip->addFile($path, $path);
    }
}
$zip->close();
echo $target . ' (' . round(filesize($target) / 1048576, 1) . " MB)\n";
