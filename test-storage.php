<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "✅ Laravel bootstrapped successfully!\n\n";

$paths = [
    'storage/framework/cache' => is_writable(storage_path('framework/cache')),
    'storage/framework/sessions' => is_writable(storage_path('framework/sessions')),
    'storage/framework/views' => is_writable(storage_path('framework/views')),
    'storage/logs' => is_writable(storage_path('logs')),
    'bootstrap/cache' => is_writable(base_path('bootstrap/cache')),
];

foreach ($paths as $path => $writable) {
    echo ($writable ? "✅" : "❌") . " $path\n";
}

echo "\n📊 Storage info:\n";
echo "Storage path: " . storage_path() . "\n";
echo "Cache path: " . app()->bootstrapPath('cache') . "\n";
?>
