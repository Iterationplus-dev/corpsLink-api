<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ApiException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 400,
        protected string $errorCode = 'error',
        protected array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => array_filter([
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                ...$this->errors,
            ], fn ($value) => $value !== null),
        ], $this->status);
    }
}
