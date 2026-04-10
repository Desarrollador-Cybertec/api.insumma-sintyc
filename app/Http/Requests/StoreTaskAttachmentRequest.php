<?php

namespace App\Http\Requests;

use App\Enums\AttachmentTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addAttachment', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt,zip'],
            'attachment_type' => ['sometimes', Rule::enum(AttachmentTypeEnum::class)],
        ];
    }
}
