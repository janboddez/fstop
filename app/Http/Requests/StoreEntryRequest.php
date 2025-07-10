<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:250',
            'slug' => 'nullable|string|max:250',
            'content' => 'required|string',
            'summary' => 'nullable|string',
            'published' => 'nullable|date_format:Y-m-d',
            'status' => 'in:draft,published',
            'visibility' => 'in:public,unlisted,private',
            'type' => 'in:' . implode(',', get_registered_entry_types()),
            'featured' => 'nullable|string|max:250', // Skip (!) URL validation in order to allow spaces and whatnot.
            'meta_keys' => 'nullable|array',
            'meta_values' => 'nullable|array',
            'meta_keys.*' => 'nullable|string|max:250',
            'meta_values.*' => 'nullable|string',
            'tags' => 'nullable|string',
        ];
    }
}
