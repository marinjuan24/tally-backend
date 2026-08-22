<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Card;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Create test users
        $users = [
            [
                'name'           => 'Juan Pérez',
                'email'          => 'juan@banco.com',
                'phone'          => '+52 55 1234 5678',
                'account_number' => '1000000001',
            ],
            [
                'name'           => 'María García',
                'email'          => 'maria@banco.com',
                'phone'          => '+52 55 8765 4321',
                'account_number' => '1000000002',
            ],
            [
                'name'           => 'Carlos López',
                'email'          => 'carlos@banco.com',
                'phone'          => '+52 55 1111 2222',
                'account_number' => '1000000003',
            ],
        ];

        $createdUsers = [];
        foreach ($users as $userData) {
            $createdUsers[] = User::create([
                ...$userData,
                'password' => Hash::make('password123'),
            ]);
        }

        // Create accounts with balances
        $balances = [5000.00, 12500.50, 800.00];
        foreach ($createdUsers as $i => $user) {
            Account::create([
                'user_id'  => $user->id,
                'balance'  => $balances[$i],
                'currency' => 'USD',
            ]);
        }

        // Create cards
        $cardData = [
            ['card_number' => '4532015112830366', 'card_holder' => 'JUAN PEREZ',    'expiry_date' => '12/28', 'card_type' => 'debit'],
            ['card_number' => '5425233430109903', 'card_holder' => 'MARIA GARCIA',   'expiry_date' => '06/27', 'card_type' => 'debit'],
            ['card_number' => '4916338506082832', 'card_holder' => 'CARLOS LOPEZ',   'expiry_date' => '03/29', 'card_type' => 'credit'],
        ];

        foreach ($createdUsers as $i => $user) {
            $account = $user->account;
            Card::create(array_merge($cardData[$i], ['account_id' => $account->id]));
        }

        // Create a sample transfer
        Transfer::create([
            'sender_id'   => $createdUsers[0]->id,
            'receiver_id' => $createdUsers[1]->id,
            'amount'      => 250.00,
            'concept'     => 'Pago de lunch',
            'reference'   => 'TXN-SEED001',
            'status'      => 'completed',
        ]);

        // Add a frequent payee
        $createdUsers[0]->frequentPayees()->create([
            'payee_id' => $createdUsers[1]->id,
            'alias'    => 'Marí',
        ]);

        $this->command->info('✅ Base de datos sembrada con 3 usuarios de prueba.');
        $this->command->info('📧 Emails: juan@banco.com, maria@banco.com, carlos@banco.com');
        $this->command->info('🔑 Password para todos: password123');
    }
}
