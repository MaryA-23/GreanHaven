<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Vegetable;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ Create admin only if not existing
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'role' => 'admin',
                'password' => Hash::make('password'),
            ]
        );

        // ✅ Create a company
        $company = Company::firstOrCreate([
            'name' => 'GreenHaven Ltd.',
            'email' => 'info@greenhaven.com',
        ]);

        // ✅ Create company user
        $companyUser = User::firstOrCreate(
            ['email' => 'company@example.com'],
            [
                'first_name' => 'Company',
                'last_name' => 'Rep',
                'role' => 'company',
                'company_id' => $company->id,
                'password' => Hash::make('password'),
            ]
        );

        // ✅ Create normal user
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'first_name' => 'Mary',
                'last_name' => 'Ayivor',
                'role' => 'user',
                'password' => Hash::make('password'),
            ]
        );

        // ✅ Add some vegetables
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

        // ✅ Sample orders
        $order = Order::firstOrCreate([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'status' => 'paid',
        ]);

        // ✅ Sample payment
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
