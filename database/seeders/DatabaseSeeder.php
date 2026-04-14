<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'superadmin@autoelement.local'],
            [
                'name' => 'Суперадмин',
                'password' => Hash::make('ChangeMe!123'),
                'is_super_admin' => true,
                'branch_id' => null,
                'email_verified_at' => now(),
            ]
        );
    }
}
