<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityEvaluation extends Model
{
    protected string $table = 'quality_evaluations';

    protected array $fillable = [
        'cohort',
        'sus_items_json',
        'sus_score',
        'functional_suitability',
        'performance_efficiency',
        'usability',
        'reliability',
        'portability',
        'iso_overall_mean',
        'recorded_date',
    ];

    protected array $casts = [
        'sus_items_json' => 'array',
        'sus_score' => 'float',
        'functional_suitability' => 'float',
        'performance_efficiency' => 'float',
        'usability' => 'float',
        'reliability' => 'float',
        'portability' => 'float',
        'iso_overall_mean' => 'float',
    ];
}
