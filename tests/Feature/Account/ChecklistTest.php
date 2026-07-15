<?php

namespace Tests\Feature\Account;

use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_checklist_items_with_their_own_checked_state(): void
    {
        $user = User::factory()->create();
        $checkedItem = ChecklistItem::factory()->create(['category' => 'Documents', 'label' => 'Test Packed Item']);
        $uncheckedItem = ChecklistItem::factory()->create(['category' => 'Camp Essentials', 'label' => 'Test Unpacked Item']);
        $user->checklistItems()->attach($checkedItem->id, ['checked_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/checklist');

        $response->assertOk();
        $byId = collect($response->json())->keyBy('id');

        $this->assertTrue($byId[$checkedItem->id]['checked']);
        $this->assertFalse($byId[$uncheckedItem->id]['checked']);
    }

    public function test_user_can_toggle_a_checklist_item_checked_then_unchecked(): void
    {
        $user = User::factory()->create();
        $item = ChecklistItem::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/checklist/{$item->id}/toggle");

        $response->assertOk();
        $response->assertJsonPath('checked', true);
        $this->assertNotNull($user->checklistItems()->find($item->id)->pivot->checked_at);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/checklist/{$item->id}/toggle");

        $response->assertOk();
        $response->assertJsonPath('checked', false);
        $this->assertNull($user->checklistItems()->find($item->id)->pivot->checked_at);
    }

    public function test_checked_state_does_not_leak_between_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $item = ChecklistItem::factory()->create();
        $other->checklistItems()->attach($item->id, ['checked_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/checklist');

        $response->assertOk();
        $this->assertFalse(collect($response->json())->firstWhere('id', $item->id)['checked']);
    }
}
