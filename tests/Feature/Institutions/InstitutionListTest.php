<?php

namespace Tests\Feature\Institutions;

use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionListTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The registration wizard's School step calls this before the user has
     * an account/token — it must stay public, not gated behind auth:sanctum.
     */
    public function test_guest_can_list_institutions(): void
    {
        $response = $this->getJson('/api/v1/institutions');

        $response->assertOk();
        $this->assertNotEmpty($response->json());
    }

    public function test_guest_can_view_a_single_institution(): void
    {
        $institution = Institution::query()->first();

        $response = $this->getJson("/api/v1/institutions/{$institution->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $institution->id);
    }

    public function test_guest_can_list_an_institutions_vehicles(): void
    {
        $institution = Institution::query()->first();

        $response = $this->getJson("/api/v1/institutions/{$institution->id}/vehicles");

        $response->assertOk();
    }

    public function test_search_matches_regardless_of_case(): void
    {
        $institution = Institution::query()->where('abbreviation', 'UNILAG')->firstOrFail();

        $response = $this->getJson('/api/v1/institutions?search=unilag');

        $response->assertOk();
        $this->assertContains(
            $institution->id,
            collect($response->json())->pluck('id')->all(),
        );
    }
}
