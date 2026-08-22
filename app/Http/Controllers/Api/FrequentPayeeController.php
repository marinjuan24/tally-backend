<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FrequentPayee;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FrequentPayeeController extends Controller
{
    /**
     * List all frequent payees for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $payees = FrequentPayee::where('user_id', $request->user()->id)
            ->with('payee:id,name,email,account_number')
            ->get();

        return response()->json([
            'payees' => $payees->map(fn ($payee) => [
                'id'        => $payee->id,
                'alias'     => $payee->alias,
                'payee'     => [
                    'id'             => $payee->payee->id,
                    'name'           => $payee->payee->name,
                    'email'          => $payee->payee->email,
                    'account_number' => $payee->payee->account_number,
                ],
                'created_at' => $payee->created_at,
            ]),
        ]);
    }

    /**
     * Add a new frequent payee.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payee_email' => ['required', 'string', 'email', 'exists:users,email'],
            'alias'       => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $payee = User::where('email', $validated['payee_email'])->first();

        if ($user->id === $payee->id) {
            return response()->json([
                'message' => 'No puedes agregarte a ti mismo como beneficiario frecuente',
            ], 422);
        }

        $exists = FrequentPayee::where('user_id', $user->id)
            ->where('payee_id', $payee->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Este beneficiario ya está en tu lista de frecuentes',
            ], 422);
        }

        $frequentPayee = FrequentPayee::create([
            'user_id'  => $user->id,
            'payee_id' => $payee->id,
            'alias'    => $validated['alias'] ?? null,
        ]);

        $frequentPayee->load('payee:id,name,email,account_number');

        return response()->json([
            'message' => 'Beneficiario agregado exitosamente',
            'payee'   => [
                'id'        => $frequentPayee->id,
                'alias'     => $frequentPayee->alias,
                'payee'     => [
                    'id'             => $frequentPayee->payee->id,
                    'name'           => $frequentPayee->payee->name,
                    'email'          => $frequentPayee->payee->email,
                    'account_number' => $frequentPayee->payee->account_number,
                ],
            ],
        ], 201);
    }

    /**
     * Update alias for a frequent payee.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'alias' => ['required', 'string', 'max:100'],
        ]);

        $payee = FrequentPayee::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$payee) {
            return response()->json([
                'message' => 'Beneficiario no encontrado',
            ], 404);
        }

        $payee->update(['alias' => $validated['alias']]);

        return response()->json([
            'message' => 'Beneficiario actualizado',
            'payee'   => [
                'id'    => $payee->id,
                'alias' => $payee->alias,
            ],
        ]);
    }

    /**
     * Remove a frequent payee.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $payee = FrequentPayee::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->first();

        if (!$payee) {
            return response()->json([
                'message' => 'Beneficiario no encontrado',
            ], 404);
        }

        $payee->delete();

        return response()->json([
            'message' => 'Beneficiario eliminado',
        ]);
    }
}
