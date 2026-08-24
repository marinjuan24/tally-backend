<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Card;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request): JsonResponse
    {
        
        
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'phone'    => ['nullable', 'string', 'max:20'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['account_number'] = User::generateAccountNumber();

        $user = User::create($validated);

        // Create a bank account for the new user
        $account = Account::create([
            'user_id'        => $user->id,
            'account_number' => $user->account_number,
            'balance'        => 0.00,
            'currency'       => 'USD',
        ]);

        // Create a debit card for the new account
        $card = Card::create([
            'account_id'  => $account->id,
            'card_number' => Card::generateCardNumber(),
            'cvv'         => Card::generateCvv(),
            'card_holder' => strtoupper($user->name),
            'expiry_date' => Card::generateExpiryDate(),
            'card_type'   => 'debit',
            'is_active'   => true,
        ]);

        // Give 50 USD registration reward from Tally
        $tallyUser = User::where('email', 'tally@bancoficticio.com')->first();
        if ($tallyUser && $tallyUser->account) {
            DB::transaction(function () use ($user, $account, $tallyUser) {
                // Deduct from Tally
                $tallyUser->account->decrement('balance', 50.00);
                // Add to new user
                $account->increment('balance', 50.00);
                // Create transaction record for Tally (sender side)
                Transaction::create([
                    'account_id' => $tallyUser->account->id,
                    'type'       => 'retiro',
                    'motive'     => 'recompensa',
                    'sender'     => 'Tally',
                    'amount'     => 50.00,
                    'reference'  => Transaction::generateReference(),
                ]);
                // Create transaction record for new user (receiver side)
                Transaction::create([
                    'account_id' => $account->id,
                    'type'       => 'abono',
                    'motive'     => 'registro',
                    'sender'     => 'Tally',
                    'amount'     => 50.00,
                    'reference'  => Transaction::generateReference(),
                ]);
            });
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user'    => $user->only('id', 'name', 'email', 'phone', 'account_number'),
            'account' => [
                'id'             => $account->id,
                'account_number' => $account->account_number,
                'balance'        => $account->balance,
                'currency'       => $account->currency,
            ],
            'card' => [
                'id'            => $card->id,
                'card_number' => $card->card_number,
                'cvv'           => $card->cvv,
                'card_holder'   => $card->card_holder,
                'expiry_date'   => $card->expiry_date,
                'card_type'     => $card->card_type,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Login with email and password.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Credenciales incorrectas',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'user'    => $user->only('id', 'name', 'email', 'phone', 'photo', 'account_number'),
            'token'   => $token,
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Get authenticated user data.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('account.cards');

        return response()->json([
            'user' => [
                'id'             => $user->id,
                'name'           => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'photo'          => $user->photo,
                'account_number' => $user->account_number,
                'account'        => $user->account ? [
                    'id'             => $user->account->id,
                    'account_number' => $user->account->account_number,
                    'balance'        => $user->account->balance,
                    'currency'       => $user->account->currency,
                    'status'         => $user->account->status,
                    'cards'          => $user->account->cards->map(fn ($card) => [
                        'id'            => $card->id,
                        'card_number' => $card->card_number,
                        'cvv'           => $card->cvv,
                        'card_holder'   => $card->card_holder,
                        'expiry_date'   => $card->expiry_date,
                        'card_type'     => $card->card_type,
                        'is_active'     => $card->is_active,
                    ]),
                ] : null,
            ],
        ]);
    }
}
