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
        // 🧑‍💼 Admin
        $admin = User::create([
            'first_name' => 'Admin',
             'last_name' => 'User',
            'email' => 'admin@example.com',
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);

        // 🏢 Company
        $company = Company::create([
            'name' => 'GreenFarm Ltd',
            'email' => 'greenfarm@example.com',
           
        ]);


        
        $companyUser = User::create([
             'first_name' => 'Company',
        'last_name' => 'User',
        'email' => 'company@example.com',
        'role' => 'company',
        'company_id' => $company->id,
        'password' => bcrypt('password'),
        ]);

        // 👩‍🌾 Normal user
        User::factory()->create([
        'first_name' => 'Company',
        'last_name' => 'User',
        'email' => 'company@example.com',
        'role' => 'company',
        'company_id' => $company->id,
        'password' => bcrypt('password'),
    ]);

        // 🥬 Vegetables
        $tomato = Vegetable::create([
            'name' => 'Tomato',
            'price' => 15,
            'quantity' => 100,
            'description' => 'Fresh local tomatoes',
        ]);

        $pepper = Vegetable::create([
            'name' => 'Pepper',
            'price' => 10,
            'quantity' => 200,
            'description' => 'Hot red pepper',
        ]);

        // 🧾 Order
        $order = Order::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'total_price' => 25,
            'status' => 'confirmed',
        ]);

        // 💵 Payment
        Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 25,
            'status' => 'paid',
            'payment_method' => 'manual',
            'transaction_id' => 'DEMO12345',
            'paid_at' => now(),
        ]);

        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('Admin: admin@example.com / password');
        $this->command->info('Company: company@example.com / password');
        $this->command->info('User: user@example.com / password');
    }
}
