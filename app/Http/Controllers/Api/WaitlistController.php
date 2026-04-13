<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WaitlistFormRequest;
use App\Services\WaitlistService;
use Illuminate\Http\JsonResponse;

class WaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlistService
    ) {}

    public function store(WaitlistFormRequest $request): JsonResponse
    {
        $result = $this->waitlistService->register(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('shopping_companion'),
        );

        return response()->json($result, 201);
    }
}
