<?php

namespace Tests\Feature\Account;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->getJson('/api/v1/profile')->assertStatus(401);
    }

    public function test_user_can_update_editable_profile_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/profile', [
                'fullName' => 'Updated Name',
                'phone' => '08099998877',
            ]);

        $response->assertOk();
        $response->assertJsonPath('fullName', 'Updated Name');
        $this->assertSame('08099998877', $user->fresh()->phone);
    }

    public function test_institution_and_call_up_number_are_not_editable_via_profile_update(): void
    {
        $original = Institution::query()->first();
        $other = Institution::factory()->create();
        $user = User::factory()->create(['institution_id' => $original->id, 'call_up_number' => 'NYSC/LOCK/2026/1']);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/profile', [
            'institution_id' => $other->id,
            'call_up_number' => 'NYSC/CHANGED/2026/1',
        ])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame($original->id, $fresh->institution_id);
        $this->assertSame('NYSC/LOCK/2026/1', $fresh->call_up_number);
    }

    public function test_user_can_upload_an_avatar(): void
    {
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'public_id' => 'corpslink/avatars/1/abc123',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/corpslink/avatars/1/abc123.jpg',
            ]),
        ]);

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => $file]);

        $response->assertOk();
        $this->assertSame('corpslink/avatars/1/abc123', $user->fresh()->avatar_path);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.cloudinary.com')
            && str_contains($request->url(), '/image/upload'));
    }

    public function test_avatar_upload_deletes_the_previous_avatar(): void
    {
        Http::fake([
            'api.cloudinary.com/*/image/upload' => Http::response([
                'public_id' => 'corpslink/avatars/1/new',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/corpslink/avatars/1/new.jpg',
            ]),
            'api.cloudinary.com/*/image/destroy' => Http::response(['result' => 'ok']),
        ]);

        $user = User::factory()->create(['avatar_path' => 'corpslink/avatars/1/old']);
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => $file])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/image/destroy')
            && $request['public_id'] === 'corpslink/avatars/1/old');
    }

    public function test_avatar_upload_rejects_non_image_files(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/profile/avatar', ['avatar' => $file])
            ->assertUnprocessable();

        Http::assertNothingSent();
    }
}
