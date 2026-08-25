<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Card;
use App\Models\FrequentPayee;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Juan Pérez',
            'email' => 'juan@test.com',
            'password' => bcrypt('password123'),
            'account_number' => '1000000001',
        ]);

        Account::create(['user_id' => $this->user->id, 'account_number' => $this->user->account_number, 'balance' => 5000, 'currency' => 'USD']);

        Card::create([
            'account_id' => $this->user->account->id,
            'card_number' => '4532015112830366',
            'cvv' => '123',
            'card_holder' => 'JUAN PEREZ',
            'expiry_date' => '12/28',
            'card_type' => 'debit',
        ]);

        $this->other = User::create([
            'name' => 'María García',
            'email' => 'maria@test.com',
            'password' => bcrypt('password123'),
            'account_number' => '1000000002',
        ]);

        Account::create(['user_id' => $this->other->id, 'account_number' => $this->other->account_number, 'balance' => 2000, 'currency' => 'USD']);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    // ===== AUTH TESTS =====

    public function test_register_creates_user_with_account(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Ana Martínez',
            'email' => 'ana@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user', 'account', 'token'])
            ->assertJson(['message' => 'Usuario registrado exitosamente']);

        $this->assertDatabaseHas('users', ['email' => 'ana@test.com']);
        $this->assertDatabaseHas('accounts', ['user_id' => $response->json('user.id')]);
    }

    public function test_login_returns_token(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'juan@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token']);
    }

    public function test_login_wrong_password_fails(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'juan@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $response = $this->getJson('/api/me', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('user.email', 'juan@test.com')
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'account']]);
    }

    public function test_logout_revokes_token(): void
    {
        $response = $this->postJson('/api/logout', [], $this->authHeaders());

        $response->assertOk();

        // Token should be deleted from the database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
        ]);
    }

    // ===== ACCOUNT TESTS =====

    public function test_account_shows_balance_and_cards(): void
    {
        $response = $this->getJson('/api/account', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('account.balance', '5000.00')
            ->assertJsonCount(1, 'account.cards');
    }

    // ===== TRANSFER TESTS =====

    public function test_send_transfer_works(): void
    {
        $response = $this->postJson('/api/transfers', [
            'receiver_email' => 'maria@test.com',
            'amount' => 100,
            'concept' => 'Café',
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('transfer.amount', '100.00')
            ->assertJsonPath('transfer.status', 'completed');

        // Verify balances changed
        $this->assertDatabaseHas('accounts', ['user_id' => $this->user->id, 'balance' => 4900.00]);
        $this->assertDatabaseHas('accounts', ['user_id' => $this->other->id, 'balance' => 2100.00]);
    }

    public function test_send_to_self_fails(): void
    {
        $response = $this->postJson('/api/transfers', [
            'receiver_email' => 'juan@test.com',
            'amount' => 100,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('message', 'No puedes enviarte dinero a ti mismo');
    }

    public function test_insufficient_balance_fails(): void
    {
        $response = $this->postJson('/api/transfers', [
            'receiver_email' => 'maria@test.com',
            'amount' => 99999,
        ], $this->authHeaders());

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Saldo insuficiente');
    }

    public function test_transfer_history_shows_transfers(): void
    {
        // Create a transfer first
        Transfer::create([
            'sender_id' => $this->user->id,
            'receiver_id' => $this->other->id,
            'amount' => 50,
            'reference' => 'TXN-TEST001',
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/transfers', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_transfer_not_found_returns_404(): void
    {
        $response = $this->getJson('/api/transfers/9999', $this->authHeaders());

        $response->assertStatus(404);
    }

    // ===== FREQUENT PAYEES TESTS =====

    public function test_add_frequent_payee(): void
    {
        $response = $this->postJson('/api/frequent-payees', [
            'payee_email' => 'maria@test.com',
            'alias' => 'Marí',
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('payee.alias', 'Marí');

        $this->assertDatabaseHas('frequent_payees', [
            'user_id' => $this->user->id,
            'payee_id' => $this->other->id,
        ]);
    }

    public function test_list_frequent_payees(): void
    {
        FrequentPayee::create([
            'user_id' => $this->user->id,
            'payee_id' => $this->other->id,
            'alias' => 'Marí',
        ]);

        $response = $this->getJson('/api/frequent-payees', $this->authHeaders());

        $response->assertOk()
            ->assertJsonCount(1, 'payees');
    }

    public function test_delete_frequent_payee(): void
    {
        $payee = FrequentPayee::create([
            'user_id' => $this->user->id,
            'payee_id' => $this->other->id,
        ]);

        $response = $this->deleteJson("/api/frequent-payees/{$payee->id}", [], $this->authHeaders());

        $response->assertOk();
        $this->assertDatabaseMissing('frequent_payees', ['id' => $payee->id]);
    }

    public function test_add_self_as_payee_fails(): void
    {
        $response = $this->postJson('/api/frequent-payees', [
            'payee_email' => 'juan@test.com',
        ], $this->authHeaders());

        $response->assertStatus(422);
    }

    // ===== PROFILE TESTS =====

    public function test_update_profile(): void
    {
        $response = $this->putJson('/api/profile', [
            'name' => 'Juan P.',
            'phone' => '+52 55 9999 0000',
        ], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('user.name', 'Juan P.');
    }

    // ===== UNAUTHORIZED TESTS =====

    public function test_unauthenticated_requests_fail(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/account')->assertUnauthorized();
        $this->getJson('/api/transfers')->assertUnauthorized();
    }
}
