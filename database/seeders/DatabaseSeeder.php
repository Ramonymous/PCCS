<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create test user and assign admin role
        $user = User::factory()->create([
            'name' => 'Ramonymous',
            'email' => 'me@r-dev.asia',
            'password' => bcrypt('@IPkmqb1V'),
        ]);
        $user->assignRole('admin');
    }
}
