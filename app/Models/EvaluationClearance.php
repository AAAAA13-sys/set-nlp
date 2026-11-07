<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationClearance extends Model
{
    protected string $table = 'evaluation_clearances';

    protected array $fillable = [
        'student_id',
        'course_section_id',
        'academic_term_id',
        'submission_key',
        'status',
        'clearance_token',
        'cleared_at',
    ];

    protected array $casts = [
        'student_id' => 'integer',
        'course_section_id' => 'integer',
        'academic_term_id' => 'integer',
    ];
}
