<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('applications.transition') ?? false;
    }

    public function rules(): array
    {
        return [
            'stage_slug' => [
                'required',
                'string',
                Rule::in(['applied', 'screening', 'shortlisted', 'interview', 'assessment', 'offered', 'hired', 'rejected', 'withdrawn']),
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
