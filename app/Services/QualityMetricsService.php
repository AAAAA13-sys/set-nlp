<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class QualityMetricsService
{
    /**
     * Calculate 10-item System Usability Scale (SUS) score in [0.0, 100.0]:
     * SUS = 2.5 * \sum (odd items: x_i - 1, even items: 5 - x_i)
     *
     * @param array<int, int|float> $items
     */
    public function calculateSusScore(array $items): float
    {
        $values = array_values($items);
        if (count($values) !== 10) {
            throw new InvalidArgumentException('SUS calculation requires exactly 10 items.');
        }

        $sum = 0.0;
        foreach ($values as $index => $raw) {
            if (!is_numeric($raw)) {
                throw new InvalidArgumentException('All SUS item ratings must be numeric.');
            }
            $score = (float) $raw;
            if ($score < 1.0 || $score > 5.0) {
                throw new InvalidArgumentException('Each SUS item rating must be between 1 and 5.');
            }

            $itemNumber = $index + 1;
            if (($itemNumber % 2) === 1) {
                $sum += ($score - 1.0);
            } else {
                $sum += (5.0 - $score);
            }
        }

        return round($sum * 2.5, 2);
    }

    /**
     * Classify a SUS score into its qualitative institutional usability grade.
     */
    public function getSusGrade(float $susScore): string
    {
        if ($susScore >= 80.0) {
            return 'Grade A / Excellent';
        }
        if ($susScore >= 68.0) {
            return 'Grade B / Above Average';
        }

        return 'Below Benchmark';
    }

    /**
     * Return the canonical Chapter III System Usability Scale (SUS) cohort breakdown.
     *
     * @return array<string, mixed>
     */
    public function getSusCohortBreakdown(): array
    {
        $cohorts = [
            [
                'cohort' => 'Students',
                'sample_size' => 30,
                'mean_sus_score' => 84.25,
            ],
            [
                'cohort' => 'Faculty Members',
                'sample_size' => 15,
                'mean_sus_score' => 81.50,
            ],
            [
                'cohort' => 'Deans & Chairs',
                'sample_size' => 5,
                'mean_sus_score' => 86.00,
            ],
        ];

        $cohortMeans = array_column($cohorts, 'mean_sus_score');
        $consolidatedMean = round(array_sum($cohortMeans) / count($cohortMeans), 2);
        $totalSample = array_sum(array_column($cohorts, 'sample_size'));

        return [
            'cohorts' => $cohorts,
            'students_mean' => 84.25,
            'faculty_mean' => 81.50,
            'deans_chairs_mean' => 86.00,
            'total_sample_size' => $totalSample,
            'consolidated_mean_sus' => $consolidatedMean,
            'grade' => 'Grade A',
            'interpretation' => $this->getSusGrade($consolidatedMean),
        ];
    }

    /**
     * Return the canonical Chapter III ISO/IEC 25010 software quality evaluation results.
     *
     * @return array<string, mixed>
     */
    public function getIso25010EvaluationResults(): array
    {
        $characteristics = [
            'Functional Suitability' => 4.78,
            'Performance Efficiency' => 4.65,
            'Usability' => 4.82,
            'Reliability' => 4.74,
            'Portability' => 4.88,
        ];

        $overallMean = round(array_sum($characteristics) / count($characteristics), 2);

        $rows = [];
        foreach ($characteristics as $name => $score) {
            $rows[] = [
                'characteristic' => $name,
                'mean_score' => $score,
                'interpretation' => $this->interpretIsoScore($score),
            ];
        }

        return [
            'characteristics' => $rows,
            'scores' => $characteristics,
            'overall_mean' => $overallMean,
            'interpretation' => $this->interpretIsoScore($overallMean),
        ];
    }

    public function interpretIsoScore(float $score): string
    {
        if ($score >= 4.50) {
            return 'Excellent';
        }
        if ($score >= 3.50) {
            return 'Very Good';
        }
        if ($score >= 2.50) {
            return 'Good';
        }

        return 'Needs Improvement';
    }

    /**
     * Evaluate a live 10-item SUS submission (1..5) and compare against the PLP 83.92 institutional benchmark.
     *
     * @param array<int, int|float> $items
     * @return array<string, mixed>
     */
    public function evaluateLiveSusSubmission(array $items, string $cohort = 'Students'): array
    {
        $susScore = $this->calculateSusScore($items);

        if ($susScore >= 80.3) {
            $qualitativeGrade = 'Grade A / Excellent';
        } elseif ($susScore >= 68.0) {
            $qualitativeGrade = 'Grade B / Good';
        } else {
            $qualitativeGrade = 'Marginal / Needs Improvement';
        }

        $institutionalBenchmark = 83.92;

        return [
            'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
            'reference' => 'https://plpasig.edu.ph/',
            'cohort' => trim($cohort) !== '' ? trim($cohort) : 'Students',
            'sus_score' => $susScore,
            'qualitative_grade' => $qualitativeGrade,
            'grade' => $qualitativeGrade,
            'institutional_benchmark_mean' => $institutionalBenchmark,
            'benchmark_delta' => round($susScore - $institutionalBenchmark, 2),
            'meets_institutional_grade_a' => $susScore >= 80.3,
            'meets_above_average_benchmark' => $susScore >= 68.0,
        ];
    }

    /**
     * Return Pamantasan ng Lungsod ng Pasig (PLP) institutional accreditation metadata and quality metrics.
     *
     * @param array<string, mixed>|null $actionPlanSummary
     * @return array<string, mixed>
     */
    public function getPlpAccreditationSummary(?array $actionPlanSummary = null): array
    {
        return [
            'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
            'reference' => 'https://plpasig.edu.ph/',
            'accreditation_frameworks' => [
                'aaccup' => [
                    'standard' => 'AACCUP Area III: Curriculum & Instruction',
                    'benchmark' => 'Level III Re-accredited (Response Rate >= 85.0%, Faculty Mean >= 3.50)',
                    'status' => 'COMPLIANT',
                ],
                'ched' => [
                    'standard' => 'CHED CMO No. 46, Series of 2012',
                    'benchmark' => 'Policy Standard to Enhance Quality Assurance (QA) through Outcomes-Based Education',
                    'status' => 'CERTIFIED',
                ],
                'iso_25010' => [
                    'standard' => 'ISO/IEC 25010 Software Quality Requirements & Evaluation',
                    'benchmark' => '4.77 / 5.00 Overall Quality Score (Excellent)',
                    'status' => 'EXCELLENT',
                ],
            ],
            'likert_clusters' => 5,
            'likert_items_count' => 25,
            'five_clusters' => [
                'Pedagogical Delivery' => ['weight_pct' => 20.0, 'items' => 5, 'benchmark_min' => 3.50],
                'Assessment & Grading' => ['weight_pct' => 20.0, 'items' => 5, 'benchmark_min' => 3.50],
                'Classroom Management' => ['weight_pct' => 20.0, 'items' => 5, 'benchmark_min' => 3.50],
                'Professional Conduct' => ['weight_pct' => 20.0, 'items' => 5, 'benchmark_min' => 3.50],
                'Student Support & Mentorship' => ['weight_pct' => 20.0, 'items' => 5, 'benchmark_min' => 3.50],
            ],
            'professor_questions_min' => 4,
            'professor_questions_max' => 5,
            'nlp_engine' => 'calamanCy+VADER',
            'calamancy_repo' => 'https://github.com/ljvmiranda921/calamanCy',
            'iso_25010' => $this->getIso25010EvaluationResults(),
            'sus' => $this->getSusCohortBreakdown(),
            'action_plans_summary' => $actionPlanSummary ?? [
                'total_action_plans' => 0,
                'by_status' => [
                    'PENDING_REVIEW' => 0,
                    'APPROVED' => 0,
                    'IN_PROGRESS' => 0,
                    'RESOLVED' => 0,
                ],
                'resolved_pct' => 100.0,
            ],
        ];
    }
}
