<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Attachment::class);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,zip'],
            'task_id' => ['nullable', 'exists:tasks,id'],
            'area_id' => ['nullable', 'exists:areas,id'],
        ];
    }
}
