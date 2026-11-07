<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected string $table = 'courses';

    protected array $fillable = [
        'department_id',
        'code',
        'title',
        'units',
    ];

    protected array $casts = [
        'department_id' => 'integer',
        'units' => 'integer',
    ];
}
