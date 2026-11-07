<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/{any?}', static function () {
    if (function_exists('view')) {
        return view('app');
    }

    return file_get_contents(base_path('resources/views/app.blade.php'));
})->where('any', '.*');
