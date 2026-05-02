<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecipeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:1000'],
            'servings' => ['required', 'integer', 'min:1', 'max:99'],
            'cook_time_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'notes' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:8192'],

            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.quantity_text' => ['nullable', 'string', 'max:64'],
            'ingredients.*.unit_text' => ['nullable', 'string', 'max:32'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],

            'steps' => ['required', 'array', 'min:1'],
            'steps.*.body' => ['required', 'string'],
        ];
    }
}
