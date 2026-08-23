<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get transaction history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->account) {
            return response()->json([
                'message' => 'No se encontró una cuenta bancaria',
            ], 404);
        }

        $transactions = Transaction::where('account_id', $user->account->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Get a specific transaction by ID.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('id', $id)
            ->where('account_id', $user->account->id ?? 0)
            ->first();

        if (!$transaction) {
            return response()->json([
                'message' => 'Transacción no encontrada',
            ], 404);
        }

        return response()->json([
            'transaction' => [
                'id'         => $transaction->id,
                'type'       => $transaction->type,
                'motive'     => $transaction->motive,
                'sender'     => $transaction->sender,
                'amount'     => $transaction->amount,
                'reference'  => $transaction->reference,
                'created_at' => $transaction->created_at,
            ],
        ]);
    }
}
