<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChecklistItemResource;
use App\Models\ChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $checkedIds = $user->checklistItems()
            ->wherePivotNotNull('checked_at')
            ->pluck('checklist_items.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // sort_order is sequential across the whole catalog (assigned by the
        // seeder), so ordering by it alone keeps items grouped by category in
        // the intended display order without an alphabetical sort scrambling it.
        $items = ChecklistItem::query()->orderBy('sort_order')->get();

        // Collection::each() stops iterating the moment its callback returns
        // false — and an arrow function here returns the assignment's value,
        // so the first unchecked item would silently truncate the loop. A
        // plain foreach has no such early-exit trap.
        foreach ($items as $item) {
            $item->checked = in_array((int) $item->id, $checkedIds, true);
        }

        return $this->success(ChecklistItemResource::collection($items));
    }

    public function toggle(Request $request, ChecklistItem $checklistItem): JsonResponse
    {
        $user = $request->user();

        $isChecked = $user->checklistItems()
            ->wherePivot('checklist_item_id', $checklistItem->id)
            ->wherePivotNotNull('checked_at')
            ->exists();

        $user->checklistItems()->syncWithoutDetaching([
            $checklistItem->id => ['checked_at' => $isChecked ? null : now()],
        ]);

        $checklistItem->checked = ! $isChecked;

        return $this->success(ChecklistItemResource::make($checklistItem));
    }
}
