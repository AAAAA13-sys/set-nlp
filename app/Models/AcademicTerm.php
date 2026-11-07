<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicTerm extends Model
{
    protected string $table = 'academic_terms';

    protected array $fillable = [
        'code',
        'name',
        'academic_year',
        'is_active',
        'evaluation_open',
        'starts_on',
        'ends_on',
    ];

    protected array $casts = [
        'is_active' => 'boolean',
        'evaluation_open' => 'boolean',
    ];
}
