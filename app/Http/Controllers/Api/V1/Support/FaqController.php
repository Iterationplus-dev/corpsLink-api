<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->string('category')->value();

        $faqs = Faq::query()
            ->published()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderBy('sort_order')
            ->get();

        return $this->success(FaqResource::collection($faqs));
    }

    public function show(Faq $faq): JsonResponse
    {
        return $this->success(FaqResource::make($faq));
    }
}
