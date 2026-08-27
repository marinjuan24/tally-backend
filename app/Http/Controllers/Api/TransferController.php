<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    /**
     * Send money to another user by email.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_email' => ['required', 'string', 'email', 'exists:users,email'],
            'amount'         => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'concept'        => ['nullable', 'string', 'max:255'],
        ]);

        $sender = $request->user();
        $receiver = User::where('email', $validated['receiver_email'])->first();

        // Can't send to yourself
        if ($sender->id === $receiver->id) {
            return response()->json([
                'message' => 'No puedes enviarte dinero a ti mismo',
            ], 422);
        }

        // Load accounts
        $sender->load('account');
        $receiver->load('account');

        if (!$sender->account || !$receiver->account) {
            return response()->json([
                'message' => 'Ambos usuarios deben tener una cuenta bancaria',
            ], 422);
        }

        if ($sender->account->status !== 'active') {
            return response()->json([
                'message' => 'Tu cuenta no está activa',
            ], 422);
        }

        if ($sender->account->balance < $validated['amount']) {
            return response()->json([
                'message' => 'Saldo insuficiente',
                'balance' => $sender->account->balance,
            ], 422);
        }

        // Execute transfer in a transaction
        $transfer = DB::transaction(function () use ($sender, $receiver, $validated) {
            // Deduct from sender
            $sender->account->decrement('balance', $validated['amount']);

            // Add to receiver
            $receiver->account->increment('balance', $validated['amount']);

            // Create transfer record
            $transfer = Transfer::create([
                'sender_id'   => $sender->id,
                'receiver_id' => $receiver->id,
                'amount'      => $validated['amount'],
                'concept'     => $validated['concept'] ?? null,
                'reference'   => Transfer::generateReference(),
                'status'      => 'completed',
            ]);

            // Create transaction record for sender (retiro)
            Transaction::create([
                'account_id' => $sender->account->id,
                'type'       => 'retiro',
                'motive'     => 'transferencia_tercero',
                'sender'     => $sender->name,
                'amount'     => $validated['amount'],
                'reference'  => $transfer->reference,
            ]);

            // Create transaction record for receiver (abono)
            Transaction::create([
                'account_id' => $receiver->account->id,
                'type'       => 'abono',
                'motive'     => 'transferencia_tercero',
                'sender'     => $sender->name,
                'amount'     => $validated['amount'],
                'reference'  => $transfer->reference,
            ]);

            return $transfer;
        });

        $transfer->load(['sender', 'receiver']);

        // Crear notificación para el receptor
        Notification::create([
            'user_id' => $receiver->id,
            'type'    => 'transfer_received',
            'title'   => 'Has recibido una transferencia',
            'message' => "{$sender->name} te ha enviado {$validated['amount']} {$sender->account->currency}",
            'data'    => [
                'transfer_id' => $transfer->id,
                'reference'   => $transfer->reference,
                'amount'      => $validated['amount'],
                'currency'    => $sender->account->currency,
                'sender_name' => $sender->name,
                'concept'     => $validated['concept'] ?? null,
            ],
        ]);

        // Crear notificación para el emisor
        Notification::create([
            'user_id' => $sender->id,
            'type'    => 'transfer_sent',
            'title'   => 'Transferencia realizada',
            'message' => "Has enviado {$validated['amount']} {$sender->account->currency} a {$receiver->name}",
            'data'    => [
                'transfer_id'   => $transfer->id,
                'reference'     => $transfer->reference,
                'amount'        => $validated['amount'],
                'currency'      => $sender->account->currency,
                'receiver_name' => $receiver->name,
                'concept'       => $validated['concept'] ?? null,
            ],
        ]);

        return response()->json([
            'message'  => 'Transferencia realizada exitosamente',
            'transfer' => [
                'id'        => $transfer->id,
                'reference' => $transfer->reference,
                'amount'    => $transfer->amount,
                'concept'   => $transfer->concept,
                'status'    => $transfer->status,
                'sender'    => [
                    'id'    => $transfer->sender->id,
                    'name'  => $transfer->sender->name,
                    'email' => $transfer->sender->email,
                ],
                'receiver' => [
                    'id'    => $transfer->receiver->id,
                    'name'  => $transfer->receiver->name,
                    'email' => $transfer->receiver->email,
                ],
                'created_at' => $transfer->created_at,
            ],
        ], 201);
    }

    /**
     * Get transfer history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $transfers = Transfer::where('sender_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['sender:id,name,email', 'receiver:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transfers);
    }

    /**
     * Get a specific transfer by ID.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $transfer = Transfer::with(['sender:id,name,email', 'receiver:id,name,email'])
            ->where('id', $id)
            ->where(function ($query) use ($request) {
                $query->where('sender_id', $request->user()->id)
                    ->orWhere('receiver_id', $request->user()->id);
            })
            ->first();

        if (!$transfer) {
            return response()->json([
                'message' => 'Transferencia no encontrada',
            ], 404);
        }

        return response()->json([
            'transfer' => [
                'id'         => $transfer->id,
                'reference'  => $transfer->reference,
                'amount'     => $transfer->amount,
                'concept'    => $transfer->concept,
                'status'     => $transfer->status,
                'sender'     => [
                    'id'    => $transfer->sender->id,
                    'name'  => $transfer->sender->name,
                    'email' => $transfer->sender->email,
                ],
                'receiver'   => [
                    'id'    => $transfer->receiver->id,
                    'name'  => $transfer->receiver->name,
                    'email' => $transfer->receiver->email,
                ],
                'created_at' => $transfer->created_at,
            ],
        ]);
    }
}
