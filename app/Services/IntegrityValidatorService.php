<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

class IntegrityValidatorService
{
    public const MIN_DWELL_SECONDS_20_ITEMS = 0;
    public const DEFAULT_QUESTION_COUNT = 20;
    public const FIVE_CLUSTER_QUESTION_COUNT = 25;
    public const LIKERT_CLUSTERS_COUNT = 5;
    public const LIKERT_SCALE_POINTS = 5;
    public const MIN_PROFESSOR_QUESTIONS = 4;
    public const MAX_PROFESSOR_QUESTIONS = 5;
    public const MAX_COMMENT_LENGTH = 2000;

    public const CLUSTER_NAMES = [
        'Pedagogical Delivery',
        'Assessment & Grading',
        'Classroom Management',
        'Professional Conduct',
        'Student Support & Mentorship',
    ];

    /**
     * Calculate arithmetic mean (\mu) across Likert item ratings.
     *
     * @param array<int|string, int|float> $ratings
     */
    public function calculateMean(array $ratings): float
    {
        $values = array_values($ratings);
        $count = count($values);
        if ($count === 0) {
            throw new InvalidArgumentException('Ratings array cannot be empty.');
        }

        $sum = 0.0;
        foreach ($values as $rating) {
            if (!is_numeric($rating)) {
                throw new InvalidArgumentException('All Likert ratings must be numeric.');
            }
            $sum += (float) $rating;
        }

        return round($sum / $count, 4);
    }

    /**
     * Calculate population variance (\sigma^2 = (1/N) * \sum (r_i - \mu)^2) across Likert ratings.
     *
     * @param array<int|string, int|float> $ratings
     */
    public function calculateVariance(array $ratings): float
    {
        $values = array_values($ratings);
        $count = count($values);
        if ($count === 0) {
            throw new InvalidArgumentException('Ratings array cannot be empty.');
        }

        $mean = $this->calculateMean($values);
        $sumSquaredDiffs = 0.0;
        foreach ($values as $rating) {
            $diff = ((float) $rating) - $mean;
            $sumSquaredDiffs += $diff * $diff;
        }

        return round($sumSquaredDiffs / $count, 4);
    }

    /**
     * Detect whether a submission exhibits zero variance (straight-lining: \sigma^2 = 0).
     *
     * @param array<int|string, int|float> $ratings
     */
    public function isStraightLined(array $ratings): bool
    {
        if (count($ratings) === 0) {
            return false;
        }

        return $this->calculateVariance($ratings) === 0.0;
    }

    /**
     * Validate interaction duration under the Mandatory Timing Policy:
     * Students may submit a complete evaluation whenever they are ready (zero minimum waiting period
     * and zero maximum answering duration). Only negative durations are invalid.
     */
    public function validateDwellTime(int $dwellSeconds, int $questionCount = self::DEFAULT_QUESTION_COUNT): bool
    {
        if ($questionCount <= 0) {
            return false;
        }

        return $dwellSeconds >= 0;
    }

    /**
     * Validate that a ratings array has 20 (4 clusters x 5) or 25 (5 clusters x 5) items and integer values in [1, 5].
     *
     * @param array<int|string, mixed> $ratings
     */
    public function validateRatingsArray(array $ratings, int $expectedCount = self::DEFAULT_QUESTION_COUNT): bool
    {
        $count = count($ratings);
        if ($expectedCount === self::DEFAULT_QUESTION_COUNT) {
            if ($count !== self::DEFAULT_QUESTION_COUNT && $count !== self::FIVE_CLUSTER_QUESTION_COUNT) {
                return false;
            }
        } elseif ($count !== $expectedCount) {
            return false;
        }

        foreach ($ratings as $value) {
            if (!is_int($value) && !is_numeric($value)) {
                return false;
            }
            $num = (float) $value;
            if ($num < 1.0 || $num > 5.0 || floor($num) !== $num) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate per-cluster 5-point Likert averages across the 5 questionnaire clusters.
     *
     * @param array<int|string, int|float> $ratings
     * @return array<string, float>
     */
    public function calculateClusterScores(array $ratings): array
    {
        $values = array_values(array_map('floatval', $ratings));
        $clusterScores = [];

        foreach (self::CLUSTER_NAMES as $idx => $clusterName) {
            $slice = array_slice($values, $idx * 5, 5);
            if (count($slice) > 0) {
                $clusterScores[$clusterName] = round(array_sum($slice) / count($slice), 2);
            } else {
                $clusterScores[$clusterName] = count($values) > 0
                    ? round(array_sum($values) / count($values), 2)
                    : 0.0;
            }
        }

        return $clusterScores;
    }

    /**
     * Validate the 4-to-5 structured qualitative questions regarding the professor:
     *   1. 3 words that describe the experience with the prof
     *   2. Room for improvements of the prof (a. topic 1, b. topic 2, ...)
     *   3. Key teaching strengths of the prof
     *   4. Actionable course/professor recommendations
     *   5. Optional 5th observation (strictly capped at MAX_PROFESSOR_QUESTIONS = 5)
     *
     * @param array<string, mixed> $profQuestions
     * @return array{valid: bool, code: string, question_count: int, max_questions: int, normalized: array<string, mixed>}
     */
    public function validateProfessorQuestions(array $profQuestions): array
    {
        if (isset($profQuestions['questions']) && is_array($profQuestions['questions'])) {
            $qCount = count($profQuestions['questions']);
            if ($qCount > self::MAX_PROFESSOR_QUESTIONS) {
                return [
                    'valid' => false,
                    'code' => 'MAX_PROFESSOR_QUESTIONS_EXCEEDED',
                    'question_count' => $qCount,
                    'max_questions' => self::MAX_PROFESSOR_QUESTIONS,
                    'normalized' => [],
                ];
            }
        }

        $rawWords = $profQuestions['three_words'] ?? ($profQuestions['q1_three_words'] ?? []);
        if (is_string($rawWords)) {
            $words = preg_split('/[,;\/|\s]+/', trim($rawWords), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        } elseif (is_array($rawWords)) {
            $words = array_values(array_filter(array_map(static fn ($w) => trim((string) $w), $rawWords), static fn ($w) => $w !== ''));
        } else {
            $words = [];
        }
        $threeWords = array_slice($words, 0, 3);

        $rawImprovements = $profQuestions['room_for_improvements'] ?? ($profQuestions['q2_room_for_improvements'] ?? []);
        $topics = [];
        if (is_array($rawImprovements)) {
            $idx = 0;
            foreach ($rawImprovements as $entry) {
                $text = is_array($entry)
                    ? trim((string) ($entry['topic'] ?? ($entry['text'] ?? '')))
                    : trim((string) $entry);
                if ($text === '') {
                    continue;
                }
                $letter = chr(ord('a') + ($idx % 26));
                $topics[] = [
                    'sub_item' => "{$letter}.",
                    'label' => "{$letter}. Topic " . ($idx + 1),
                    'topic' => $text,
                ];
                $idx++;
            }
        } elseif (is_string($rawImprovements) && trim($rawImprovements) !== '') {
            $lines = preg_split('/[\r\n;]+/', trim($rawImprovements), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($lines as $idx => $line) {
                $letter = chr(ord('a') + ($idx % 26));
                $topics[] = [
                    'sub_item' => "{$letter}.",
                    'label' => "{$letter}. Topic " . ($idx + 1),
                    'topic' => trim($line),
                ];
            }
        }

        $q3 = trim((string) ($profQuestions['teaching_strengths'] ?? ($profQuestions['q3_teaching_strengths'] ?? '')));
        $q4 = trim((string) ($profQuestions['recommendations'] ?? ($profQuestions['q4_recommendations'] ?? '')));
        $q5 = trim((string) ($profQuestions['additional_observation'] ?? ($profQuestions['q5_additional_observation'] ?? '')));

        $activeCount = 4 + ($q5 !== '' ? 1 : 0);

        return [
            'valid' => $activeCount <= self::MAX_PROFESSOR_QUESTIONS,
            'code' => 'VALID_PROFESSOR_QUESTIONS',
            'question_count' => $activeCount,
            'max_questions' => self::MAX_PROFESSOR_QUESTIONS,
            'normalized' => [
                'q1_three_words' => $threeWords,
                'q2_room_for_improvements' => $topics,
                'q3_teaching_strengths' => $q3,
                'q4_recommendations' => $q4,
                'q5_additional_observation' => $q5 !== '' ? $q5 : null,
            ],
        ];
    }

    /**
     * Issue a server-signed HMAC-SHA256 start token recording when a student opened a survey.
     */
    public function issueStartToken(
        int $studentId,
        int $courseSectionId,
        string $secret = 'evaluatech-hmac-signing-secret-2026',
        ?int $timestamp = null
    ): string {
        $issuedAt = $timestamp ?? time();
        $payload = "{$studentId}:{$courseSectionId}:{$issuedAt}";
        $signature = hash_hmac('sha256', $payload, $secret);

        return base64_encode("{$payload}:{$signature}");
    }

    /**
     * Verify a server-signed start token and confirm minimum elapsed dwell duration.
     */
    public function verifyStartToken(
        string $token,
        int $studentId,
        int $courseSectionId,
        string $secret = 'evaluatech-hmac-signing-secret-2026',
        int $minDwellSeconds = self::MIN_DWELL_SECONDS_20_ITEMS,
        ?int $nowTimestamp = null
    ): bool {
        $decoded = base64_decode($token, true);
        if (!is_string($decoded) || $decoded === '') {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            return false;
        }

        [$tokenStudentId, $tokenSectionId, $issuedAtStr, $signature] = $parts;
        if (!ctype_digit($tokenStudentId) || !ctype_digit($tokenSectionId) || !ctype_digit($issuedAtStr)) {
            return false;
        }

        if ((int) $tokenStudentId !== $studentId || (int) $tokenSectionId !== $courseSectionId) {
            return false;
        }

        $payload = "{$tokenStudentId}:{$tokenSectionId}:{$issuedAtStr}";
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expectedSignature, $signature)) {
            return false;
        }

        $now = $nowTimestamp ?? time();
        $elapsed = $now - (int) $issuedAtStr;

        return $elapsed >= $minDwellSeconds;
    }

    /**
     * Neutralize CSV formula injection (values starting with =, +, -, @, tab, CR).
     */
    public function sanitizeCsvCell(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $trimmed = ltrim($value, " \t\r\n");
        if ($trimmed === '') {
            return $value;
        }

        $dangerousPrefixes = ['=', '+', '-', '@'];
        if (in_array($trimmed[0], $dangerousPrefixes, true) || in_array($value[0], ["\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Neutralize CSV formula injection alias for formula sanitization.
     */
    public function sanitizeCsvFormula(string $value): string
    {
        return $this->sanitizeCsvCell($value);
    }

    /**
     * Compute a one-way salted HMAC-SHA256 hash for an anonymized evaluation comment.
     */
    public function computeCommentHash(
        string $commentText,
        int $courseSectionId,
        int $academicTermId,
        string $secret = 'evaluatech-hmac-signing-secret-2026'
    ): string {
        $normalized = strtolower(trim($commentText));
        return hash_hmac('sha256', "{$courseSectionId}|{$academicTermId}|{$normalized}", $secret);
    }

    /**
     * Strip any student-identifying or correlation headers before vault persistence.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function stripIdentifyingMetadata(array $payload): array
    {
        $forbiddenKeys = [
            'student_id',
            'user_id',
            'clearance_id',
            'ip_address',
            'session_id',
            'user_agent',
            'email',
            'institutional_id',
            'submission_key',
            'start_token',
            'clearance_token',
        ];

        foreach ($forbiddenKeys as $forbiddenKey) {
            unset($payload[$forbiddenKey]);
        }

        return $payload;
    }

    /**
     * Scrub accidental PII (emails, phone numbers, institutional IDs), control characters,
     * and prompt-injection delimiter tokens from free-text narrative feedback before vault or NLP storage.
     */
    public function scrubPiiFromNarrative(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // 1. Strip ASCII control characters (preserving tabs/newlines)
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        // 2. Neutralize LLM/prompt delimiter injection sequences (#33)
        $clean = str_ireplace(
            ['<|system|>', '<|user|>', '<|assistant|>', '<|im_start|>', '<|im_end|>', '[INST]', '[/INST]'],
            '',
            $clean
        );

        // 3. Scrub accidental email addresses, PH phone numbers, and PLP student IDs (#34)
        $clean = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[REDACTED-EMAIL]', $clean) ?? $clean;
        $clean = preg_replace('/(?:\+63|0)9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}\b/', '[REDACTED-PHONE]', $clean) ?? $clean;
        $clean = preg_replace('/\b20\d{2}[-\s]\d{4,6}\b/', '[REDACTED-ID]', $clean) ?? $clean;

        $clean = trim($clean);
        if (mb_strlen($clean, 'UTF-8') > self::MAX_COMMENT_LENGTH) {
            $clean = mb_substr($clean, 0, self::MAX_COMMENT_LENGTH, 'UTF-8');
        }

        return $clean;
    }
}
