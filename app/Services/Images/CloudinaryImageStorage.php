<?php

namespace App\Services\Images;

use App\Contracts\ImageStorageContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to Cloudinary's REST API directly via Http — no SDK, same pattern
 * as ZeptomailTransport/TermiiChannel/PaystackGateway. Cloudinary's signed
 * requests only need SHA-1 request signing, not the OAuth2 JWT exchange
 * that justified pulling in an SDK for Firebase.
 *
 * @see https://cloudinary.com/documentation/authentication_signatures
 */
class CloudinaryImageStorage implements ImageStorageContract
{
    public function __construct(
        protected string $cloudName,
        protected string $apiKey,
        protected string $apiSecret,
    ) {}

    /**
     * @return array{public_id: string, secure_url: string}
     */
    public function upload(UploadedFile $file, string $folder): array
    {
        $timestamp = (string) time();
        $signature = $this->sign(['folder' => $folder, 'timestamp' => $timestamp]);

        $response = Http::attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName())
            ->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload", [
                'api_key' => $this->apiKey,
                'timestamp' => $timestamp,
                'folder' => $folder,
                'signature' => $signature,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Cloudinary upload failed: '.$response->body());
        }

        return [
            'public_id' => $response->json('public_id'),
            'secure_url' => $response->json('secure_url'),
        ];
    }

    public function delete(string $publicId): void
    {
        $timestamp = (string) time();
        $signature = $this->sign(['public_id' => $publicId, 'timestamp' => $timestamp]);

        $response = Http::asForm()->post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy", [
            'api_key' => $this->apiKey,
            'timestamp' => $timestamp,
            'public_id' => $publicId,
            'signature' => $signature,
        ]);

        // Deleting an asset that's already gone shouldn't be an error —
        // cleanup calls need to stay idempotent.
        if ($response->failed() || ! in_array($response->json('result'), ['ok', 'not found'], true)) {
            throw new RuntimeException('Cloudinary delete failed: '.$response->body());
        }
    }

    /**
     * Pure string building — Cloudinary transformation URLs are
     * deterministic, no API call needed to resolve one.
     *
     * @param  array<string, int|string>  $transformations
     */
    public function url(string $publicId, array $transformations = []): string
    {
        if ($transformations === []) {
            return "https://res.cloudinary.com/{$this->cloudName}/image/upload/{$publicId}";
        }

        $segments = collect($transformations)
            ->map(fn ($value, $key) => "{$key}_{$value}")
            ->implode(',');

        return "https://res.cloudinary.com/{$this->cloudName}/image/upload/{$segments}/{$publicId}";
    }

    /**
     * Cloudinary's signature spec: params sorted by key, joined as
     * "key=value&key2=value2" WITHOUT URL-encoding (http_build_query would
     * URL-encode and silently produce a wrong signature), then the API
     * secret appended directly before hashing. `file`, `cloud_name`,
     * `resource_type`, and `api_key` are never part of the signed string.
     *
     * @param  array<string, string>  $params
     */
    protected function sign(array $params): string
    {
        ksort($params);

        $joined = collect($params)->map(fn ($value, $key) => "{$key}={$value}")->implode('&');

        return sha1($joined.$this->apiSecret);
    }
}
