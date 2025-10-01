<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\Hash;
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create default admin company
        $adminCompany = Company::firstOrCreate(
            ['name' => 'Admin Corp'],
            ['email' => 'admin@company.com']
        );

        // Create super admin user
        User::firstOrCreate(
            ['email' => 'admin@greenhaven.com'],
            [
                'first_name' => 'Admin',
                'last_name'=> 'Super',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'company_id' => $adminCompany->id
            ]
        );
    }
}