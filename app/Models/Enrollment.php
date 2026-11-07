<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    protected string $table = 'enrollments';

    protected array $fillable = [
        'student_id',
        'course_section_id',
        'academic_term_id',
        'enrollment_status',
    ];

    protected array $casts = [
        'student_id' => 'integer',
        'course_section_id' => 'integer',
        'academic_term_id' => 'integer',
    ];
}
