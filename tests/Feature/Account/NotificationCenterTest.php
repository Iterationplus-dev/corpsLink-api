<?php

namespace Tests\Feature\Account;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_user_can_mark_a_single_notification_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);
        $notification = $user->notifications()->first();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);
        $user->notify(new WelcomeNotification);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $other->notify(new WelcomeNotification);
        $notification = $other->notifications()->first();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertStatus(404);

        $this->assertNull($notification->fresh()->read_at);
    }
}
