<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'mehulpanchal@gmail.com'],
            [
                'name' => 'Mehul Panchal',
                'password' => Hash::make('password'),
                'status' => 'active',
            ]
        );

        $user->syncRoles('Super Admin');
    }
}
