<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Modules\Pets\Enums\ExhibitionClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGameEventRequest extends FormRequest
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
            'pet_id' => ['sometimes', 'integer', 'min:1'],
            'plan' => ['required', 'array:stages,offspring_ids,member_ids,exhibition_class,sport_class,program,test_type,handler_category'],
            'plan.exhibition_class' => ['sometimes', 'string', Rule::enum(ExhibitionClass::class)],
            'plan.sport_class' => ['sometimes', 'string', 'max:40'],
            'plan.program' => ['sometimes', 'in:pr1,pr2'],
            'plan.test_type' => ['sometimes', 'in:birch,lavender,anise,cinnamon,orange,clove'],
            'plan.handler_category' => ['sometimes', 'string', 'max:40'],
            'plan.stages' => ['required', 'array', 'min:1', 'max:6'],
            'plan.stages.*' => ['required', 'in:careful,balanced,bold'],
            'plan.offspring_ids' => ['sometimes', 'array', 'max:5'],
            'plan.offspring_ids.*' => ['integer', 'min:1', 'distinct'],
            'plan.member_ids' => ['sometimes', 'array', 'max:4'],
            'plan.member_ids.*' => ['integer', 'min:1', 'distinct'],
            'gear_ids' => ['present', 'array', 'max:4'],
            'gear_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }
}
