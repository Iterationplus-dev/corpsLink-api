<?php

namespace Tests\Feature\Account;

use App\Models\NextOfKin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NextOfKinTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_next_of_kin(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/emergency-contact', [
            'fullName' => 'Chinedu Okafor',
            'relationship' => 'Brother',
            'phone' => '08052210374',
            'address' => '14 Adeola Close, Surulere, Lagos',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('next_of_kins', [
            'user_id' => $user->id,
            'full_name' => 'Chinedu Okafor',
        ]);
    }

    public function test_user_can_update_existing_next_of_kin(): void
    {
        $user = User::factory()->create();
        $nok = NextOfKin::factory()->for($user)->create(['full_name' => 'Old Name']);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/emergency-contact', [
            'fullName' => 'New Name',
            'relationship' => $nok->relationship,
            'phone' => $nok->phone,
            'address' => $nok->address,
        ])->assertOk();

        $this->assertSame(1, NextOfKin::query()->where('user_id', $user->id)->count());
        $this->assertSame('New Name', $nok->fresh()->full_name);
    }

    public function test_user_can_view_their_next_of_kin(): void
    {
        $user = User::factory()->create();
        NextOfKin::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile/emergency-contact')
            ->assertOk();
    }

    public function test_user_can_delete_their_next_of_kin(): void
    {
        $user = User::factory()->create();
        NextOfKin::factory()->for($user)->create();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/profile/emergency-contact')
            ->assertOk();

        $this->assertDatabaseCount('next_of_kins', 0);
    }

    public function test_validation_fails_for_missing_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile/emergency-contact', []);

        $response->assertUnprocessable();
        $this->assertValidationError($response, 'fullName');
        $this->assertValidationError($response, 'relationship');
        $this->assertValidationError($response, 'phone');
        $this->assertValidationError($response, 'address');
    }
}
