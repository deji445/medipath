<?php

namespace App\Http\Requests\Assessment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssessmentAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'question_key' => ['nullable', 'required_without:answers', 'string', 'max:80'],
            'answer' => ['nullable', 'required_with:question_key', 'string', 'max:1000'],
            'answers' => ['nullable', 'required_without:question_key', 'array', 'min:1', 'max:3'],
            'answers.*' => ['required', 'string', 'max:120'],
            'group_key' => ['nullable', 'required_with:answers', 'string', 'max:80'],
        ];
    }
}
