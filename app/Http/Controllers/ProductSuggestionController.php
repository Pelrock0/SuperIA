<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuggestionQueryRequest;
use App\Services\ProductSuggestionService;
use Illuminate\Http\JsonResponse;

class ProductSuggestionController extends Controller
{
    public function __construct(
        private ProductSuggestionService $service,
    ) {}

    public function index(SuggestionQueryRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        $result = $this->service->suggest(
            $user,
            (string) $request->validated('q'),
            (bool) $request->validated('include_ai', false),
        );

        return response()->json(['data' => $result]);
    }
}
