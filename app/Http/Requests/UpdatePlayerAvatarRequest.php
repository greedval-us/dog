<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Players\DTO\PlayerAvatarData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerAvatarRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'bail', 'required', 'file', 'max:'.config('doglive.avatar.max_kilobytes'),
                'image', 'mimes:jpg,jpeg,png,webp',
                'dimensions:max_width='.config('doglive.avatar.max_dimension').',max_height='.config('doglive.avatar.max_dimension'),
            ],
        ];
    }

    public function toData(): PlayerAvatarData
    {
        return new PlayerAvatarData($this->validated('avatar')->getPathname());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'avatar.required' => __('Choose an avatar image.'),
            'avatar.file' => __('Choose a JPG, PNG or WebP image.'),
            'avatar.image' => __('Choose a JPG, PNG or WebP image.'),
            'avatar.mimes' => __('Choose a JPG, PNG or WebP image.'),
            'avatar.uploaded' => __('The image could not be uploaded. Choose a smaller file.'),
            'avatar.max' => __('The avatar must not exceed :size MB.', ['size' => config('doglive.avatar.max_kilobytes') / 1024]),
            'avatar.dimensions' => __('The image must not exceed :size × :size pixels.', ['size' => config('doglive.avatar.max_dimension')]),
        ];
    }
}
