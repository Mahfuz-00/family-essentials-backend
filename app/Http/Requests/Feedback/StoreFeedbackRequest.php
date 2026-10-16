<?php

namespace App\Http\Requests\Feedback;

use App\Models\FeedbackReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(FeedbackReport::TYPES)],
            'priority' => ['sometimes', Rule::in(FeedbackReport::PRIORITIES)],
            'subject' => ['required', 'string', 'min:4', 'max:160'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            // Anything the client could tell us about where it happened. Kept
            // free-form on purpose: it aids triage, it is not data.
            'context' => ['sometimes', 'array'],
            'context.platform' => ['sometimes', 'nullable', 'string', 'max:64'],
            'context.app_version' => ['sometimes', 'nullable', 'string', 'max:64'],
            'context.screen' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Give the report a short title so it can be found again.',
            'body.min' => 'Add a little more detail — what happened, and what you expected instead.',
        ];
    }
}
