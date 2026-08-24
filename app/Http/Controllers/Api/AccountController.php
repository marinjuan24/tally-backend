<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Get account details with cards.
     */
    public function index(Request $request): JsonResponse
    {
        
        $user = $request->user()->load('account.cards');

        if (!$user->account) {
            return response()->json([
                'message' => 'No se encontró una cuenta bancaria',
            ], 404);
        }

        return response()->json([
            'account' => [
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
            ],
        ]);
    }
}
