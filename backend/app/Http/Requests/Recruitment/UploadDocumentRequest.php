<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('documents.upload') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
            'document_type' => ['nullable', Rule::in(['cv', 'cover_letter', 'portfolio', 'certificate', 'other'])],
            'is_primary_cv' => ['nullable', 'boolean'],
        ];
    }
}
