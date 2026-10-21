<?php

namespace Database\Seeders;

use App\Models\Make;
use App\Models\VehicleCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CarReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Hatchback', 'Sedan', 'SUV', 'MUV', 'Coupe', 'Convertible', 'Pickup', 'Van'];
        foreach ($categories as $name) {
            VehicleCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }

        $makes = [
            'Maruti Suzuki', 'Hyundai', 'Tata', 'Mahindra', 'Honda', 'Toyota',
            'Kia', 'Volkswagen', 'Skoda', 'Renault', 'Nissan', 'MG', 'Ford', 'BMW', 'Mercedes-Benz', 'Audi',
        ];
        foreach ($makes as $name) {
            Make::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
