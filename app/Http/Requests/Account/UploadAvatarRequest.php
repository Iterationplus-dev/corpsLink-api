<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                File::image()
                    ->max(config('corpslink.avatar.max_kilobytes'))
                    ->extensions(config('corpslink.avatar.mimes')),
            ],
        ];
    }
}
