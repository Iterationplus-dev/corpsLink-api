<?php

namespace App\Http\Controllers\Api\V1\Institutions;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstitutionResource;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();
        $type = $request->string('type')->value();
        $state = $request->string('state')->value();
        $sort = $request->string('sort', 'name')->value();

        $institutions = Institution::query()
            ->active()
            ->withCount('vehicles')
            ->with(['vehicles' => fn ($query) => $query->select('id', 'institution_id', 'destination')])
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('abbreviation', 'like', "%{$search}%")
                ->orWhere('state', 'like', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($state, fn ($query) => $query->where('state', $state))
            ->orderBy(in_array($sort, ['name', 'state', 'type']) ? $sort : 'name')
            ->get();

        return $this->success(InstitutionResource::collection($institutions));
    }

    public function show(Institution $institution): JsonResponse
    {
        $institution->loadCount('vehicles')->load(['vehicles' => fn ($query) => $query->select('id', 'institution_id', 'destination')]);

        return $this->success(InstitutionResource::make($institution));
    }
}
