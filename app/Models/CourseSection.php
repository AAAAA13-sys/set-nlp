<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected string $table = 'course_sections';

    protected array $fillable = [
        'course_id',
        'faculty_id',
        'academic_term_id',
        'section_code',
        'term_grades_submitted',
        'registrar_lock',
        'enrolled_count',
    ];

    protected array $casts = [
        'course_id' => 'integer',
        'faculty_id' => 'integer',
        'academic_term_id' => 'integer',
        'term_grades_submitted' => 'boolean',
        'registrar_lock' => 'boolean',
        'enrolled_count' => 'integer',
    ];

    public function isDoubleBlindUnlocked(): bool
    {
        return (bool) $this->term_grades_submitted && (bool) $this->registrar_lock;
    }
}
