<?php

namespace Tests\Feature\Support;

use App\Models\Faq;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_list_published_faqs(): void
    {
        Faq::factory()->unpublished()->create();

        $response = $this->getJson('/api/v1/support/faqs');

        $response->assertOk();
        $this->assertTrue(collect($response->json())->every(
            fn ($faq) => Faq::query()->findOrFail($faq['id'])->is_published,
        ));
    }

    public function test_unpublished_faqs_are_excluded_from_the_list(): void
    {
        $unpublished = Faq::factory()->unpublished()->create();

        $response = $this->getJson('/api/v1/support/faqs');

        $response->assertOk();
        $this->assertFalse(collect($response->json())->contains('id', $unpublished->id));
    }

    public function test_guest_can_view_a_single_faq(): void
    {
        $faq = Faq::factory()->create();

        $response = $this->getJson("/api/v1/support/faqs/{$faq->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $faq->id);
        $response->assertJsonPath('question', $faq->question);
    }
}
