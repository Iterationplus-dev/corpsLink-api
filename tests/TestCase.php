<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed roles + institutions for every test using RefreshDatabase.
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiter state lives in cache, not the database, so
        // RefreshDatabase alone won't isolate it between test methods.
        Cache::flush();
    }

    /**
     * The error envelope is {error: {code, message, fields: [{field, message}]}}
     * — Laravel's built-in assertJsonValidationErrors() expects the
     * framework default shape, so this checks the "fields" array instead.
     */
    protected function assertValidationError(TestResponse $response, string $field): void
    {
        $response->assertStatus(422);

        $fields = collect($response->json('error.fields'))->pluck('field');

        $this->assertTrue(
            $fields->contains($field),
            "Expected a validation error for [{$field}], got: ".$fields->implode(', '),
        );
    }
}
