<?php

namespace App\Http\Requests;

use App\Enums\CommentTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:5000'],
            'type' => ['sometimes', Rule::in([
                CommentTypeEnum::COMMENT->value,
                CommentTypeEnum::PROGRESS->value,
                CommentTypeEnum::COMPLETION_NOTE->value,
            ])],
        ];
    }
}
