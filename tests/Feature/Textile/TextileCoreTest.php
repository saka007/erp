<?php

namespace Tests\Feature\Textile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use DigitalFuzed\TextileCore\Models\TextileSpecification;

class TextileCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_textile_specification(): void
    {
        $specification = TextileSpecification::create([
            'name' => 'Cotton Poplin',
            'code' => 'CP-001',
            'family' => 'Fabric',
            'composition' => '100% Cotton',
            'construction' => '40x40',
            'width' => '58"',
            'gsm' => '140',
            'shade' => 'White',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('textile_specifications', [
            'id' => $specification->id,
            'name' => 'Cotton Poplin',
            'code' => 'CP-001',
        ]);
    }
}
