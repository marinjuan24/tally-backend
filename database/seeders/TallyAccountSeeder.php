<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TallyAccountSeeder extends Seeder
{
    public function run(): void
    {
        $tally = User::firstOrCreate(
            ['email' => 'tally@bancoficticio.com'],
            [
                'name'           => 'Tally',
                'password'       => Hash::make('tally-secure-password-2024'),
                'phone'          => '+1-800-TALLY',
                'account_number' => User::generateAccountNumber(),
            ]
        );

        Account::firstOrCreate(
            ['user_id' => $tally->id],
            [
                'account_number' => $tally->account_number,
                'balance'        => 100000.00,
                'currency'       => 'USD',
                'status'         => 'active',
            ]
        );
    }
}
