<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $output, $status);
    if ($status !== 0) { fwrite(STDERR, implode("\n", $output) . "\n"); exit($status); }
    if ($file->getPathname() === __FILE__) continue;
    $source = (string)file_get_contents($file->getPathname());
    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) continue;
        $forbiddenToken = (defined('T_READONLY') && $token[0] === constant('T_READONLY'))
            || (defined('T_ENUM') && $token[0] === constant('T_ENUM'))
            || ($token[0] === T_STRING && strtolower($token[1]) === 'never');
        if ($forbiddenToken) { fwrite(STDERR, 'PHP 8.0 compatibility violation in ' . $file->getPathname() . "\n"); exit(1); }
    }
}
echo "RetroWEB lint passed.\n";
