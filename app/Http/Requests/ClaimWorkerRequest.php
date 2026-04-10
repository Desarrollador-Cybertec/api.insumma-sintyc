<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClaimWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()->isAdminLevel()) {
            return true;
        }

        if (!$this->user()->isManagerLevel()) {
            return false;
        }

        // Area managers can only claim workers for areas they manage
        return $this->user()->managedAreas()
            ->where('areas.id', $this->input('area_id'))
            ->exists();
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'area_id' => ['required', 'exists:areas,id'],
        ];
    }
}
