<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['admin', 'vendor', 'customer'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
