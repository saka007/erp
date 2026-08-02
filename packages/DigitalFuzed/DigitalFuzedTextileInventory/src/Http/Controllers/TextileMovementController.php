<?php

namespace DigitalFuzed\TextileInventory\Http\Controllers;

use DigitalFuzed\TextileInventory\Http\Requests\StoreTextileMovementRequest;
use DigitalFuzed\TextileInventory\Services\TextileMovementService;
use Illuminate\Routing\Controller;

class TextileMovementController extends Controller
{
    public function __construct(protected TextileMovementService $service)
    {
    }

    public function store(StoreTextileMovementRequest $request)
    {
        $movement = $this->service->createMovement($request->validated());

        return response()->json([
            'success' => true,
            'data' => $movement,
        ], 201);
    }
}
