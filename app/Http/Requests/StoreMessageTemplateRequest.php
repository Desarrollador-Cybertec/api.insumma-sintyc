<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\MessageTemplate::class);
    }

    public function rules(): array
    {
        return [
            'slug'    => ['required', 'string', 'max:100', 'unique:message_templates,slug'],
            'name'    => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string'],
            'active'  => ['sometimes', 'boolean'],
        ];
    }
}
