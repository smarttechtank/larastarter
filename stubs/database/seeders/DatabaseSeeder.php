<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the role seeder first
        $this->call(RoleSeeder::class);

        // Get role IDs
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        // Create users with roles
        User::factory()->create([
            'name' => 'John Doe',
            'email' => 'johndoe@example.com',
            'password' => Hash::make('P@$$w0rd'),
            'role_id' => $adminRole->id,
        ]);

        User::factory()->create([
            'name' => 'Jane Doe',
            'email' => 'janedoe@example.com',
            'password' => Hash::make('P@$$w0rd'),
            'role_id' => $adminRole->id,
        ]);

        User::factory()->create([
            'name' => 'Jack Doe',
            'email' => 'jackdoe@example.com',
            'password' => Hash::make('P@$$w0rd'),
            'role_id' => $userRole->id,
        ]);
    }
}
