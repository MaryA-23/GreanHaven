<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use App\Models\Vegetable;
use App\Models\Order;
use App\Models\Payment;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Create admin (has a role)
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => 'admin',
                'password' => Hash::make('password'),
            ]
        );

        // ✅ Create a company (no role)
        $company = Company::firstOrCreate([
            'name' => 'GreenHaven Ltd.',
            'email' => 'info@greenhaven.com',
        ]);

        // ✅ Create a company representative (no role)
        $companyUser = User::firstOrCreate(
            ['email' => 'company@example.com'],
            [
                'first_name' => 'Company',
                'last_name' => 'Rep',
                'company_id' => $company->id,
                'password' => Hash::make('password'),
            ]
        );

        // ✅ Create a normal user (no role)
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'first_name' => 'Mary',
                'last_name' => 'Ayivor',
                'password' => Hash::make('password'),
            ]
        );

        // ✅ Add sample vegetables
        $veg1 = Vegetable::firstOrCreate([
            'name' => 'Tomato',
            'category' => 'Vegetable',
            'price' => 12.50,
            'description' => 'Fresh tomatoes from local farms',
        ]);

        $veg2 = Vegetable::firstOrCreate([
            'name' => 'Carrot',
            'category' => 'Vegetable',
            'price' => 9.00,
            'description' => 'Organic carrots rich in vitamin A',
        ]);

        // ✅ Sample order (belongs to Mary)
        $order = Order::firstOrCreate([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'status' => 'confirmed',
        ]);

        // ✅ Sample payment for that order
        Payment::firstOrCreate([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 21.50,
            'status' => 'success',
            'method' => 'paystack',
            'reference' => 'PAY-' . uniqid(),
        ]);
    }
}
