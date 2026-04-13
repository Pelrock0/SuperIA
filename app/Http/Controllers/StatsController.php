<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private StatsService $service,
    ) {}

    public function index(): JsonResponse
    {
        $user = auth('api')->user();

        return response()->json(['data' => $this->service->getStats($user)]);
    }
}
