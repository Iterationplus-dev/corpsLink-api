<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface ImageStorageContract
{
    /**
     * @return array{public_id: string, secure_url: string}
     */
    public function upload(UploadedFile $file, string $folder): array;

    public function delete(string $publicId): void;

    /**
     * @param  array<string, int|string>  $transformations
     */
    public function url(string $publicId, array $transformations = []): string;
}
