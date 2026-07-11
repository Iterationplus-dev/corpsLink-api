<?php

namespace Tests\Unit\Services;

use App\Services\Images\CloudinaryImageStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class CloudinaryImageStorageTest extends TestCase
{
    protected CloudinaryImageStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage = new CloudinaryImageStorage('demo', 'test-key', 'test-secret');
    }

    protected function expectedSignature(array $params): string
    {
        ksort($params);
        $joined = collect($params)->map(fn ($value, $key) => "{$key}={$value}")->implode('&');

        return sha1($joined.'test-secret');
    }

    /**
     * Multipart requests store fields as a list of ['name' => ..., 'contents' => ...]
     * entries rather than a flat associative array, so plain $request['key']
     * array access (which works for asForm()/json requests) doesn't apply here.
     */
    protected function multipartValue($request, string $name): mixed
    {
        return collect($request->data())->firstWhere('name', $name)['contents'] ?? null;
    }

    public function test_upload_sends_a_correctly_signed_request(): void
    {
        Http::fake([
            'api.cloudinary.com/*' => Http::response([
                'public_id' => 'corpslink/avatars/1/abc123',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/corpslink/avatars/1/abc123.jpg',
            ]),
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg');

        $result = $this->storage->upload($file, 'corpslink/avatars/1');

        $this->assertSame('corpslink/avatars/1/abc123', $result['public_id']);
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/corpslink/avatars/1/abc123.jpg', $result['secure_url']);

        Http::assertSent(function ($request) {
            $expected = $this->expectedSignature([
                'folder' => $this->multipartValue($request, 'folder'),
                'timestamp' => $this->multipartValue($request, 'timestamp'),
            ]);

            return str_contains($request->url(), 'https://api.cloudinary.com/v1_1/demo/image/upload')
                && $this->multipartValue($request, 'api_key') === 'test-key'
                && $this->multipartValue($request, 'folder') === 'corpslink/avatars/1'
                && $this->multipartValue($request, 'signature') === $expected;
        });
    }

    public function test_upload_throws_when_the_request_fails(): void
    {
        Http::fake([
            'api.cloudinary.com/*' => Http::response(['error' => ['message' => 'Invalid signature']], 401),
        ]);

        $this->expectException(RuntimeException::class);

        $this->storage->upload(UploadedFile::fake()->image('avatar.jpg'), 'corpslink/avatars/1');
    }

    public function test_delete_sends_a_correctly_signed_request(): void
    {
        Http::fake([
            'api.cloudinary.com/*' => Http::response(['result' => 'ok']),
        ]);

        $this->storage->delete('corpslink/avatars/1/abc123');

        Http::assertSent(function ($request) {
            $expected = $this->expectedSignature([
                'public_id' => $request['public_id'],
                'timestamp' => $request['timestamp'],
            ]);

            return str_contains($request->url(), 'https://api.cloudinary.com/v1_1/demo/image/destroy')
                && $request['api_key'] === 'test-key'
                && $request['public_id'] === 'corpslink/avatars/1/abc123'
                && $request['signature'] === $expected;
        });
    }

    public function test_delete_treats_not_found_as_success(): void
    {
        Http::fake([
            'api.cloudinary.com/*' => Http::response(['result' => 'not found']),
        ]);

        $this->storage->delete('corpslink/avatars/1/already-gone');

        $this->addToAssertionCount(1);
    }

    public function test_delete_throws_on_other_failures(): void
    {
        Http::fake([
            'api.cloudinary.com/*' => Http::response(['error' => ['message' => 'Invalid signature']], 401),
        ]);

        $this->expectException(RuntimeException::class);

        $this->storage->delete('corpslink/avatars/1/abc123');
    }

    public function test_url_builds_the_transformation_segment_in_insertion_order(): void
    {
        $url = $this->storage->url('corpslink/avatars/1/abc123', ['w' => 200, 'h' => 200, 'c' => 'fill']);

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/w_200,h_200,c_fill/corpslink/avatars/1/abc123',
            $url,
        );
    }

    public function test_url_omits_the_transformation_segment_when_none_given(): void
    {
        $url = $this->storage->url('corpslink/avatars/1/abc123');

        $this->assertSame('https://res.cloudinary.com/demo/image/upload/corpslink/avatars/1/abc123', $url);
    }
}
