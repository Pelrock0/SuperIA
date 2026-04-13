<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DeleteAccountRequest;
use App\Services\AccountDeletionService;
use Illuminate\Http\JsonResponse;

class AccountDeletionController extends Controller
{
    public function __construct(
        private AccountDeletionService $accountDeletionService,
    ) {}

    public function destroy(DeleteAccountRequest $request): JsonResponse
    {
        try {
            $this->accountDeletionService->initiateDelete(
                auth('api')->user(),
                $request->validated('password'),
            );

            return response()->json([
                'data' => [
                    'message' => 'Tu cuenta ha sido eliminada. Recibiras un email de confirmacion.',
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => ['code' => 'DELETION_FAILED', 'message' => $e->getMessage()],
            ], 422);
        }
    }
}
