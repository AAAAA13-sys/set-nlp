<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return true;
        }

        $role = is_string($user->role ?? null)
            ? $user->role
            : ($user->role instanceof UserRole ? $user->role->value : '');

        return $role === UserRole::STUDENT->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'course_section_id' => ['required', 'integer', 'min:1'],
            'submission_key' => ['required', 'string', 'min:6', 'max:96'],
            'ratings' => ['required', 'array', 'min:20', 'max:25'],
            'ratings.*' => ['required', 'integer', 'min:1', 'max:5'],
            'dwell_time_seconds' => ['nullable', 'integer', 'min:0'],
            'confirm_uniform' => ['sometimes', 'boolean'],
            'comment_text' => ['nullable', 'string', 'max:2000'],
            'professor_questions' => ['nullable', 'array', 'max:5'],
            'professor_questions.three_words' => ['nullable', 'array', 'size:3'],
            'professor_questions.room_for_improvements' => ['nullable', 'array', 'min:1', 'max:10'],
            'start_token' => ['nullable', 'string'],
        ];
    }
}
