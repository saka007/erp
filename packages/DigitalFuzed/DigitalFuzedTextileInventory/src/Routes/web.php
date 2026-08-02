<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web'])->group(function () {
    Route::get('/textile-inventory', function () {
        return response()->json([
            'message' => 'TextileInventory package is ready',
            'module' => 'TextileInventory',
        ]);
    });
});
