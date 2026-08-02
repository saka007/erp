<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/textile-core', function () {
        return response()->json([
            'message' => 'TextileCore package is ready',
            'module' => 'TextileCore',
        ]);
    });
});
