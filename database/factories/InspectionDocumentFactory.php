<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionDocument>
 */
class InspectionDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'path' => fake()->unique()->word().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'type' => 'report',
        ];
    }
}
