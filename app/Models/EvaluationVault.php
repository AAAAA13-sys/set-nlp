<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationVault extends Model
{
    protected string $table = 'evaluation_vault';

    protected string $primaryKey = 'id';

    public bool $incrementing = false;

    protected string $keyType = 'string';

    public bool $timestamps = false;

    protected array $fillable = [
        'id',
        'course_section_id',
        'faculty_id',
        'academic_term_id',
        'ratings_json',
        'cluster_scores_json',
        'mean_score',
        'rating_variance',
        'is_straight_lined',
        'dwell_time_seconds',
        'comment_text',
        'professor_questions_json',
        'comment_hash',
        'sentiment_polarity',
        'sentiment_valence',
        'pedagogical_tag',
        'nlp_engine',
        'recorded_date',
    ];

    protected array $casts = [
        'course_section_id' => 'integer',
        'faculty_id' => 'integer',
        'academic_term_id' => 'integer',
        'ratings_json' => 'array',
        'cluster_scores_json' => 'array',
        'professor_questions_json' => 'array',
        'mean_score' => 'float',
        'rating_variance' => 'float',
        'is_straight_lined' => 'boolean',
        'dwell_time_seconds' => 'integer',
        'sentiment_polarity' => 'float',
    ];
}
