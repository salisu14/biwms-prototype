<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    if (! Schema::hasTable('depreciation_books')) {
        echo "depreciation_books table not found\n";
        exit(1);
    }

    if (! Schema::hasColumn('depreciation_books', 'acquisition_cost')) {
        Schema::table('depreciation_books', function (Blueprint $table) {
            $table->decimal('acquisition_cost', 15, 4)->default(0)->after('is_active');
        });
        echo "added acquisition_cost\n";
    } else {
        echo "acquisition_cost exists\n";
    }

    if (! Schema::hasColumn('depreciation_books', 'accumulated_depreciation')) {
        Schema::table('depreciation_books', function (Blueprint $table) {
            $table->decimal('accumulated_depreciation', 15, 4)->default(0)->after('acquisition_cost');
        });
        echo "added accumulated_depreciation\n";
    } else {
        echo "accumulated_depreciation exists\n";
    }

    echo "done\n";
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}
