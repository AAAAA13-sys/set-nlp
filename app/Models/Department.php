<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected string $table = 'departments';

    protected array $fillable = [
        'code',
        'name',
        'college_name',
    ];
}
