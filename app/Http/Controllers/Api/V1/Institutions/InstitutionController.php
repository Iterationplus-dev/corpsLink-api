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
        // Keeps the response shape a flat array (no breaking change to the
        // documented contract) while capping payload size — 651 institutions
        // with a logo each was ~290KB and forced the client to request every
        // logo image and mount every card on first paint. Defaults to page 1
        // / 30 per page when the client omits these, so an old caller still
        // gets a bounded response instead of the full unbounded table.
        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(1, $request->integer('perPage', 30)));

        $institutions = Institution::query()
            ->active()
            ->withCount('vehicles')
            ->with(['vehicles' => fn ($query) => $query->select('id', 'institution_id', 'destination')])
            ->when($search, fn ($query) => $query->where(fn ($q) => $q
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('abbreviation', "%{$search}%")
                ->orWhereLike('state', "%{$search}%")))
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($state, fn ($query) => $query->where('state', $state))
            ->orderBy(in_array($sort, ['name', 'state', 'type']) ? $sort : 'name')
            ->forPage($page, $perPage)
            ->get();

        return $this->success(InstitutionResource::collection($institutions));
    }

    public function show(Institution $institution): JsonResponse
    {
        $institution->loadCount('vehicles')->load(['vehicles' => fn ($query) => $query->select('id', 'institution_id', 'destination')]);

        return $this->success(InstitutionResource::make($institution));
    }
}
