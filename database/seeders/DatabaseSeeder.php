<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $superAdminId = Role::query()->where('slug', 'super_admin')->value('id');

        if ($superAdminId) {
            $user->roles()->syncWithoutDetaching([$superAdminId]);
        }
    }
}
