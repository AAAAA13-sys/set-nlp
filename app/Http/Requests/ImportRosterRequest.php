<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class ImportRosterRequest extends FormRequest
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

        return $role === UserRole::ADMIN->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:csv,json,rows'],
            'academic_term_id' => ['sometimes', 'integer', 'min:1'],
            'content' => ['required_if:format,csv,json', 'nullable', 'string'],
            'rows' => ['required_if:format,rows', 'nullable', 'array', 'min:1'],
        ];
    }
}
