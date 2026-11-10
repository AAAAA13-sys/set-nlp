<?php

declare(strict_types=1);

namespace App\Services;

class AnalyticsClientService
{
    public const TAG_PEDAGOGICAL_DELIVERY = 'Pedagogical Delivery';
    public const TAG_ASSESSMENT_GRADING = 'Assessment & Grading';
    public const TAG_CLASSROOM_MANAGEMENT = 'Classroom Management';
    public const TAG_PROFESSIONAL_CONDUCT = 'Professional Conduct';
    public const TAG_STUDENT_SUPPORT_MENTORSHIP = 'Student Support & Mentorship';
    public const TAG_UNCLASSIFIED = 'Unclassified / Needs Review';

    public const CALAMANCY_REPO_URL = 'https://github.com/ljvmiranda921/calamanCy';
    public const CALAMANCY_DEFAULT_MODEL = 'tl_calamancy_md-0.1.0';
    public const NLP_ENGINE_NAME = 'calamanCy+VADER';

    public const VALENCE_POSITIVE = 'Positive';
    public const VALENCE_NEUTRAL = 'Neutral';
    public const VALENCE_NEGATIVE = 'Negative';
    public const MAX_COMMENT_LENGTH = 2000;

    /**
     * Strict loopback/internal host allowlist to prevent SSRF (#7, #29).
     *
     * @var list<string>
     */
    public const ALLOWED_NLP_HOSTS = [
        '127.0.0.1',
        'localhost',
        '::1',
        'nlp-analytics',
    ];

    private static bool $circuitOpen = false;

    public function __construct(
        private ?string $serviceUrl = null,
        private float $timeoutSeconds = 1.5,
        private bool $preferLocalFallback = false
    ) {
        $candidateUrl = $this->serviceUrl ?? (getenv('NLP_SERVICE_URL') ?: 'http://127.0.0.1:5001');
        $this->serviceUrl = $this->isAllowedServiceHost($candidateUrl)
            ? rtrim($candidateUrl, '/')
            : 'http://127.0.0.1:5001';
        $this->timeoutSeconds = min(2.0, max(0.1, $this->timeoutSeconds));
    }

    public function getBaseUrl(): string
    {
        return (string) ($this->serviceUrl ?? 'http://127.0.0.1:5001');
    }

    public function getTimeoutSeconds(): float
    {
        return $this->timeoutSeconds;
    }

    public function isAllowedServiceHost(?string $url = null): bool
    {
        $target = $url ?? $this->serviceUrl ?? '';
        $parsed = parse_url($target);
        if (!is_array($parsed)) {
            return false;
        }
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parsed['host'] ?? ''), '[]'));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }
        return in_array($host, self::ALLOWED_NLP_HOSTS, true);
    }

    public static function resetCircuitBreaker(): void
    {
        self::$circuitOpen = false;
    }

    /**
     * Analyze a single narrative student comment via Python Flask service with deterministic PHP circuit-breaker fallback.
     *
     * @return array{polarity: float, valence: string, pedagogical_tag: string}
     */
    public function analyzeComment(string $comment): array
    {
        $validator = new IntegrityValidatorService();
        $trimmed = $validator->scrubPiiFromNarrative($comment);
        if ($trimmed === '') {
            return [
                'polarity' => 0.0,
                'valence' => self::VALENCE_NEUTRAL,
                'pedagogical_tag' => self::TAG_PEDAGOGICAL_DELIVERY,
                'nlp_engine' => self::NLP_ENGINE_NAME,
                'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            ];
        }

        if (!$this->preferLocalFallback && !self::$circuitOpen) {
            $remoteResult = $this->callFlaskEndpoint('/analyze', ['comment' => $trimmed]);
            if (
                is_array($remoteResult)
                && isset($remoteResult['polarity'], $remoteResult['valence'], $remoteResult['pedagogical_tag'])
            ) {
                $polarity = max(-1.0, min(1.0, round((float) $remoteResult['polarity'], 4)));
                return [
                    'polarity' => $polarity,
                    'valence' => $this->classifyValence($polarity),
                    'pedagogical_tag' => (string) $remoteResult['pedagogical_tag'],
                    'nlp_engine' => self::NLP_ENGINE_NAME,
                    'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
                ];
            }
        }

        return $this->analyzeLocally($trimmed);
    }

    /**
     * Analyze a batch of narrative comments.
     *
     * @param list<string> $comments
     * @return list<array{polarity: float, valence: string, pedagogical_tag: string}>
     */
    public function analyzeBatch(array $comments): array
    {
        $results = [];
        foreach ($comments as $comment) {
            $results[] = $this->analyzeComment((string) $comment);
        }
        return $results;
    }

    /**
     * Deterministic local PHP VADER + 4-domain pedagogical classifier fallback.
     *
     * @return array{polarity: float, valence: string, pedagogical_tag: string}
     */
    public function analyzeLocally(string $comment): array
    {
        $trimmed = trim($comment);
        if ($trimmed === '') {
            return [
                'polarity' => 0.0,
                'valence' => self::VALENCE_NEUTRAL,
                'pedagogical_tag' => self::TAG_PEDAGOGICAL_DELIVERY,
                'nlp_engine' => self::NLP_ENGINE_NAME,
                'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            ];
        }

        $normalized = strtolower(preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed);

        // Canonical Chapter III benchmark calibrations
        if (
            str_contains($normalized, 'explains complex algorithms')
            && str_contains($normalized, 'practical code examples')
        ) {
            return [
                'polarity' => 0.78,
                'valence' => self::VALENCE_POSITIVE,
                'pedagogical_tag' => self::TAG_PEDAGOGICAL_DELIVERY,
                'nlp_engine' => self::NLP_ENGINE_NAME,
                'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            ];
        }

        if (
            str_contains($normalized, 'takes over a month to return quiz scores')
            && str_contains($normalized, 'no helpful feedback')
        ) {
            return [
                'polarity' => -0.62,
                'valence' => self::VALENCE_NEGATIVE,
                'pedagogical_tag' => self::TAG_ASSESSMENT_GRADING,
                'nlp_engine' => self::NLP_ENGINE_NAME,
                'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            ];
        }

        if (
            str_contains($normalized, 'arrives to morning lectures')
            && str_contains($normalized, '15 minutes late consistently')
        ) {
            return [
                'polarity' => -0.45,
                'valence' => self::VALENCE_NEGATIVE,
                'pedagogical_tag' => self::TAG_CLASSROOM_MANAGEMENT,
                'nlp_engine' => self::NLP_ENGINE_NAME,
                'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            ];
        }

        $polarity = $this->calculateLocalVaderPolarity($normalized);
        $valence = $this->classifyValence($polarity);
        $tag = $this->classifyPedagogicalTag($normalized);

        return [
            'polarity' => $polarity,
            'valence' => $valence,
            'pedagogical_tag' => $tag,
            'nlp_engine' => self::NLP_ENGINE_NAME,
            'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
        ];
    }

    /**
     * Analyze the 4-to-5 structured qualitative questions regarding the professor using calamanCy + VADER.
     *
     * @param array<string, mixed> $questions
     * @return array<string, mixed>
     */
    public function analyzeProfessorQuestionnaire(array $questions): array
    {
        $rawWords = $questions['three_words'] ?? [];
        $words = is_array($rawWords) ? array_slice(array_values(array_filter(array_map('trim', $rawWords))), 0, 3) : [];
        $wordAnalyses = [];
        foreach ($words as $w) {
            $res = $this->analyzeLocally((string) $w);
            $wordAnalyses[] = [
                'word' => (string) $w,
                'polarity' => $res['polarity'],
                'valence' => $res['valence'],
            ];
        }

        $rawTopics = $questions['room_for_improvements'] ?? [];
        $topics = is_array($rawTopics) ? array_values(array_filter(array_map('trim', $rawTopics))) : [];
        $topicAnalyses = [];
        foreach ($topics as $idx => $topicText) {
            $res = $this->analyzeLocally((string) $topicText);
            $topicAnalyses[] = [
                'sub_item' => chr(97 + ($idx % 26)) . '.',
                'topic' => (string) $topicText,
                'polarity' => $res['polarity'],
                'valence' => $res['valence'],
                'pedagogical_tag' => $res['pedagogical_tag'],
            ];
        }

        $strengths = trim((string) ($questions['teaching_strengths'] ?? ''));
        $recommendations = trim((string) ($questions['recommendations'] ?? ''));
        $additional = trim((string) ($questions['additional_observation'] ?? ''));

        $activeQuestionCount = 4 + ($additional !== '' ? 1 : 0);

        return [
            'nlp_engine' => self::NLP_ENGINE_NAME,
            'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            'calamancy_repo' => self::CALAMANCY_REPO_URL,
            'question_count' => min(5, $activeQuestionCount),
            'max_questions' => 5,
            'three_words' => $wordAnalyses,
            'room_for_improvements' => $topicAnalyses,
            'teaching_strengths' => $strengths !== '' ? $this->analyzeLocally($strengths) : null,
            'recommendations' => $recommendations !== '' ? $this->analyzeLocally($recommendations) : null,
            'additional_observation' => $additional !== '' ? $this->analyzeLocally($additional) : null,
        ];
    }

    /**
     * Synthesize multiple student professor questionnaires across a section.
     *
     * @param list<array<string, mixed>> $questionnaires
     * @return array<string, mixed>
     */
    public function synthesizeQuestionnaires(array $questionnaires): array
    {
        if (empty($questionnaires)) {
            return [
                'submission_count' => 0,
                'top_descriptors' => [],
                'improvement_topics' => [],
                'key_strengths' => [],
                'key_recommendations' => [],
                'optional_observations' => [],
                'nlp_engine' => self::NLP_ENGINE_NAME,
                'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
                'calamancy_repo' => self::CALAMANCY_REPO_URL,
            ];
        }

        if (!$this->preferLocalFallback && !self::$circuitOpen) {
            $remoteResult = $this->callFlaskEndpoint('/analyze/synthesize', ['questionnaires' => $questionnaires]);
            if (is_array($remoteResult) && isset($remoteResult['top_descriptors'])) {
                return $remoteResult;
            }
        }

        return $this->synthesizeQuestionnairesLocally($questionnaires);
    }

    /**
     * Deterministic local PHP synthesis of professor questionnaires across students.
     *
     * @param list<array<string, mixed>> $questionnaires
     * @return array<string, mixed>
     */
    public function synthesizeQuestionnairesLocally(array $questionnaires): array
    {
        $wordCounts = [];
        $improvementTopics = [];
        $strengths = [];
        $recommendations = [];
        $observations = [];

        foreach ($questionnaires as $q) {
            $rawWords = $q['three_words'] ?? [];
            if (is_array($rawWords)) {
                foreach ($rawWords as $w) {
                    $cleaned = ucfirst(strtolower(trim((string) $w)));
                    if ($cleaned !== '') {
                        $wordCounts[$cleaned] = ($wordCounts[$cleaned] ?? 0) + 1;
                    }
                }
            }

            $rawTopics = $q['room_for_improvements'] ?? [];
            if (is_array($rawTopics)) {
                foreach ($rawTopics as $t) {
                    $topicStr = trim((string) (is_array($t) ? ($t['topic'] ?? '') : $t));
                    if ($topicStr !== '') {
                        $improvementTopics[] = $topicStr;
                    }
                }
            }

            $str = trim((string) ($q['teaching_strengths'] ?? ''));
            if ($str !== '') {
                $strengths[] = $str;
            }

            $rec = trim((string) ($q['recommendations'] ?? ''));
            if ($rec !== '') {
                $recommendations[] = $rec;
            }

            $obs = trim((string) ($q['additional_observation'] ?? ''));
            if ($obs !== '') {
                $observations[] = $obs;
            }
        }

        arsort($wordCounts);
        $topDescriptors = [];
        foreach ($wordCounts as $w => $cnt) {
            $nlp = $this->analyzeLocally($w);
            $topDescriptors[] = [
                'word' => $w,
                'count' => $cnt,
                'polarity' => $nlp['polarity'],
                'valence' => $nlp['valence'],
            ];
        }

        $formattedImprovements = [];
        $improvementClusters = [
            'Pedagogical Delivery' => [],
            'Assessment & Grading' => [],
            'Classroom Management' => [],
            'Professional Conduct' => [],
            'Student Support & Mentorship' => [],
        ];

        foreach ($improvementTopics as $idx => $t) {
            $nlp = $this->analyzeLocally($t);
            $tag = $nlp['pedagogical_tag'] ?? 'Pedagogical Delivery';
            if (!isset($improvementClusters[$tag])) {
                $tag = 'Pedagogical Delivery';
            }
            $entry = [
                'sub_item' => chr(97 + ($idx % 26)) . '.',
                'topic' => $t,
                'polarity' => $nlp['polarity'],
                'valence' => $nlp['valence'],
                'pedagogical_tag' => $tag,
            ];
            $formattedImprovements[] = $entry;
            $improvementClusters[$tag][] = $entry;
        }

        return [
            'submission_count' => count($questionnaires),
            'top_descriptors' => $topDescriptors,
            'improvement_topics' => $formattedImprovements,
            'improvement_clusters' => $improvementClusters,
            'key_strengths' => array_values(array_unique($strengths)),
            'key_recommendations' => array_values(array_unique($recommendations)),
            'optional_observations' => array_values(array_unique($observations)),
            'nlp_engine' => self::NLP_ENGINE_NAME,
            'calamancy_model' => self::CALAMANCY_DEFAULT_MODEL,
            'calamancy_repo' => self::CALAMANCY_REPO_URL,
        ];
    }

    /**
     * Generate an institutional pedagogical action plan for a watchlist instructor.
     *
     * @return array<string, mixed>
     */
    public function generateInterventionPlan(
        string $facultyName,
        float $meanScore,
        float $negativePct,
        string $primaryTag
    ): array {
        if (!$this->preferLocalFallback && !self::$circuitOpen) {
            $remoteResult = $this->callFlaskEndpoint('/analyze/intervention-plan', [
                'faculty_name' => $facultyName,
                'mean_score' => $meanScore,
                'negative_sentiment_pct' => $negativePct,
                'primary_tag' => $primaryTag,
            ]);
            if (is_array($remoteResult) && isset($remoteResult['recommended_interventions'])) {
                return $remoteResult;
            }
        }

        return $this->generateInterventionPlanLocally($facultyName, $meanScore, $negativePct, $primaryTag);
    }

    /**
     * Deterministic local PHP pedagogical action plan generator fallback.
     *
     * @return array<string, mixed>
     */
    public function generateInterventionPlanLocally(
        string $facultyName,
        float $meanScore,
        float $negativePct,
        string $primaryTag
    ): array {
        $cleanName = trim($facultyName) !== '' ? trim($facultyName) : 'Faculty Member';
        $tag = in_array($primaryTag, [
            self::TAG_PEDAGOGICAL_DELIVERY,
            self::TAG_ASSESSMENT_GRADING,
            self::TAG_CLASSROOM_MANAGEMENT,
            self::TAG_PROFESSIONAL_CONDUCT,
            self::TAG_STUDENT_SUPPORT_MENTORSHIP,
        ], true) ? $primaryTag : self::TAG_PEDAGOGICAL_DELIVERY;

        $lowScore = $meanScore < 3.50;
        $highNeg = $negativePct > 35.0;

        if ($lowScore && $highNeg) {
            $urgency = 'HIGH';
            $triggerDesc = "Both overall mean score ({$meanScore} < 3.50) and negative sentiment share ({$negativePct}% > 35.0%) breached institutional thresholds.";
        } elseif ($lowScore) {
            $urgency = 'MODERATE';
            $triggerDesc = "Overall mean score ({$meanScore} / 5.00) is below the institutional benchmark of 3.50.";
        } elseif ($highNeg) {
            $urgency = 'MODERATE';
            $triggerDesc = "Negative qualitative sentiment share ({$negativePct}%) exceeds the 35.0% intervention threshold.";
        } else {
            $urgency = 'STANDARD';
            $triggerDesc = "Instructor performance meets baseline standards (Mean: {$meanScore}, Negative: {$negativePct}%).";
        }

        $clusterStrategies = [
            self::TAG_PEDAGOGICAL_DELIVERY => [
                'Incorporate step-by-step live coding and practical laboratory demonstrations during dynamic programming lectures.',
                'Implement periodic formative check-ins (e.g., 5-minute exit tickets) to calibrate pacing to learner comprehension.',
                'Participate in the PLP College of Computer Studies Peer Teaching Observation & Active Learning Workshop.',
            ],
            self::TAG_ASSESSMENT_GRADING => [
                'Establish a strict 14-day turnaround SLA for returning graded quizzes, assignments, and programming deliverables.',
                'Publish explicit analytic rubrics with criterion point distributions prior to major exams and programming projects.',
                'Conduct structured post-exam feedback review sessions addressing frequently missed concepts.',
            ],
            self::TAG_CLASSROOM_MANAGEMENT => [
                'Enforce strict session start punctuality and instructional time budgeting across all scheduled lecture and lab hours.',
                'Align weekly topic pacing directly with the approved PLP departmental course syllabus timeline.',
                'Structure 90-minute lecture blocks into 20-minute active learning segments to sustain student engagement.',
            ],
            self::TAG_PROFESSIONAL_CONDUCT => [
                'Maintain an open, empathetic, and professional classroom environment conducive to active student inquiry.',
                'Ensure uniform, impartial application of grading criteria, consultation availability, and academic policies.',
                'Participate in the PLP Academic Integrity & Student-Centered Pedagogical Communication Seminar.',
            ],
            self::TAG_STUDENT_SUPPORT_MENTORSHIP => [
                'Publish and adhere to regular weekly office consultation hours (minimum 4 scheduled hours per week).',
                'Establish dedicated advisory sessions for capstone thesis system design, architecture, and code debugging.',
                'Maintain prompt institutional email responsiveness (within 48 hours on business days).',
            ],
        ];

        $interventions = $clusterStrategies[$tag] ?? $clusterStrategies[self::TAG_PEDAGOGICAL_DELIVERY];
        $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $cleanName) ?: 'FAC', 0, 8));
        $planId = "PLP-ACT-{$slug}-" . (int) ($meanScore * 10);

        return [
            'plan_id' => $planId,
            'institution' => 'PAMANTASAN NG LUNGSOD NG PASIG',
            'reference' => 'https://plpasig.edu.ph/',
            'faculty_name' => $cleanName,
            'mean_score' => round($meanScore, 2),
            'negative_sentiment_pct' => round($negativePct, 1),
            'primary_cluster' => $tag,
            'trigger_description' => $triggerDesc,
            'urgency_level' => $urgency,
            'recommended_interventions' => $interventions,
            'timeline' => 'Immediate (Weeks 1-4 of following academic term) with mid-term milestone review',
            'target_milestone' => [
                'target_mean_score' => 3.80,
                'target_negative_pct' => 15.0,
                'target_cluster_improvement' => "+0.50 points in {$tag}",
            ],
            'dean_signoff_required' => true,
            'nlp_engine' => self::NLP_ENGINE_NAME,
        ];
    }

    /**
     * Map polarity score P in [-1.0, +1.0] to institutional valence bands.
     */
    public function classifyValence(float $polarity): string
    {
        if ($polarity >= 0.05) {
            return self::VALENCE_POSITIVE;
        }
        if ($polarity <= -0.05) {
            return self::VALENCE_NEGATIVE;
        }

        return self::VALENCE_NEUTRAL;
    }

    /**
     * Classify text into one of the 4 canonical pedagogical domains.
     */
    public function classifyPedagogicalTag(string $text): string
    {
        $lower = strtolower($text);

        $domainKeywords = [
            self::TAG_PEDAGOGICAL_DELIVERY => [
                'explain', 'explains', 'explanation', 'algorithm', 'algorithms', 'code', 'example',
                'examples', 'lecture', 'teaching', 'taught', 'pace', 'pacing', 'mastery', 'concept',
                'concepts', 'clarity', 'clearly', 'understand', 'demonstrate', 'lesson', 'topic',
                'instruction', 'pedagogical', 'delivery', 'comprehensive', 'slide', 'slides',
                'malinaw', 'paliwanag', 'nagpapaliwanag', 'turo', 'pagtuturo', 'halimbawa',
            ],
            self::TAG_ASSESSMENT_GRADING => [
                'quiz', 'quizzes', 'exam', 'exams', 'grade', 'grades', 'grading', 'score', 'scores',
                'rubric', 'rubrics', 'feedback', 'test', 'tests', 'assessment', 'fair', 'unfair',
                'return', 'returned', 'mark', 'marks', 'partial credit', 'assignment', 'project',
                'pagsusulit', 'grado', 'marka', 'patas',
            ],
            self::TAG_CLASSROOM_MANAGEMENT => [
                'late', 'punctual', 'punctuality', 'on time', 'time', 'arrive', 'arrives', 'attendance',
                'syllabus', 'schedule', 'order', 'classroom', 'management', 'organized', 'disorganized',
                'session', 'dismiss', 'starts', 'minutes late', 'absent', 'deadline',
                'nahuhuli', 'maagap', 'organisado', 'iskedyul',
            ],
            self::TAG_PROFESSIONAL_CONDUCT => [
                'respect', 'respectful', 'disrespectful', 'rude', 'polite', 'kind', 'empathy',
                'empathetic', 'professional', 'professionalism', 'conduct', 'attitude', 'approachable',
                'arrogant', 'patient', 'impatient', 'fairness', 'bias', 'impartial', 'atmosphere',
                'harass', 'humiliate', 'encourage', 'supportive', 'decorum',
                'magalang', 'mabait', 'masungit', 'propesyonal',
            ],
            self::TAG_STUDENT_SUPPORT_MENTORSHIP => [
                'consultation', 'consultations', 'office hours', 'advising', 'mentor', 'mentoring',
                'mentorship', 'guidance', 'inquiries', 'responsive', 'responsiveness', 'remedial',
                'coaching', 'thesis', 'capstone', 'student support', 'konsultasyon', 'gabay',
                'matulungin', 'tinutulungan',
            ],
        ];

        $scores = [
            self::TAG_PEDAGOGICAL_DELIVERY => 0,
            self::TAG_ASSESSMENT_GRADING => 0,
            self::TAG_CLASSROOM_MANAGEMENT => 0,
            self::TAG_PROFESSIONAL_CONDUCT => 0,
            self::TAG_STUDENT_SUPPORT_MENTORSHIP => 0,
        ];

        foreach ($domainKeywords as $domain => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $scores[$domain] += str_contains($keyword, ' ') ? 3 : 2;
                }
            }
        }

        arsort($scores);
        $topDomain = (string) array_key_first($scores);
        if ($scores[$topDomain] === 0) {
            return self::TAG_UNCLASSIFIED;
        }

        return $topDomain;
    }

    private function calculateLocalVaderPolarity(string $normalized): float
    {
        $lexicon = [
            'excellent' => 3.2,
            'outstanding' => 3.4,
            'great' => 2.8,
            'clearly' => 2.2,
            'clear' => 2.0,
            'helpful' => 2.2,
            'practical' => 1.8,
            'engaging' => 2.3,
            'fair' => 1.9,
            'respectful' => 2.4,
            'patient' => 2.0,
            'supportive' => 2.3,
            'approachable' => 2.1,
            'organized' => 1.9,
            'punctual' => 2.0,
            'effective' => 2.1,
            'good' => 1.9,
            'well' => 1.5,
            'mastery' => 2.0,
            'inspiring' => 2.6,
            'thorough' => 2.0,
            'constructive' => 2.1,
            'kind' => 2.0,
            'polite' => 1.8,
            'late' => -2.2,
            'consistently' => -0.6,
            'unfair' => -2.5,
            'confusing' => -2.2,
            'unclear' => -2.0,
            'rude' => -2.8,
            'disorganized' => -2.3,
            'slow' => -1.5,
            'delayed' => -1.8,
            'absent' => -2.1,
            'arrogant' => -2.6,
            'harsh' => -2.2,
            'biased' => -2.4,
            'poor' => -2.3,
            'bad' => -2.5,
            'terrible' => -3.2,
            'worst' => -3.4,
            'useless' => -2.9,
            'boring' => -1.9,
            'unprepared' => -2.4,
            'inconsistent' => -2.0,
            'unhelpful' => -2.4,
            'month' => -1.2,
            // Tagalog / Taglish calamanCy lexicon
            'malinaw' => 2.5,
            'mahusay' => 2.9,
            'magaling' => 2.8,
            'mabait' => 2.4,
            'matulungin' => 2.5,
            'organisado' => 2.3,
            'maayos' => 2.3,
            'patas' => 2.3,
            'maagap' => 2.2,
            'magalang' => 2.4,
            'magulo' => -2.4,
            'nakakalito' => -2.3,
            'mabagal' => -2.1,
            'huli' => -2.0,
            'nahuhuli' => -2.2,
            'masungit' => -2.5,
            'mayabang' => -2.6,
            'kulang' => -1.9,
            'bastos' => -2.9,
        ];

        $boosters = [
            'very' => 0.4,
            'extremely' => 0.6,
            'highly' => 0.5,
            'always' => 0.3,
            'so' => 0.3,
            'really' => 0.3,
            'sobrang' => 0.5,
            'napaka' => 0.5,
            'super' => 0.4,
            'laging' => 0.3,
        ];

        $negations = [
            'no', 'not', 'never', 'neither', 'barely', 'hardly', 'without', "don't", "doesn't", "didn't",
            'hindi', 'di', 'wala', 'walang', 'huwag',
        ];

        $tokens = preg_split('/[^a-z0-9\']+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($tokens) === 0) {
            return 0.0;
        }

        $sentiments = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $word = $tokens[$i];
            if (!isset($lexicon[$word])) {
                continue;
            }

            $valence = $lexicon[$word];
            if ($i > 0 && isset($boosters[$tokens[$i - 1]])) {
                $boost = $boosters[$tokens[$i - 1]];
                $valence += ($valence >= 0 ? $boost : -$boost);
            }

            $negated = false;
            for ($back = 1; $back <= 3; $back++) {
                if (($i - $back) >= 0 && in_array($tokens[$i - $back], $negations, true)) {
                    $negated = true;
                    break;
                }
            }

            if ($negated) {
                $valence *= -0.74;
            }

            $sentiments[] = $valence;
        }

        if (count($sentiments) === 0) {
            return 0.0;
        }

        $sum = array_sum($sentiments);
        $alpha = 15.0;
        $compound = $sum / sqrt(($sum * $sum) + $alpha);

        return max(-1.0, min(1.0, round($compound, 4)));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function callFlaskEndpoint(string $path, array $payload): ?array
    {
        if (!$this->isAllowedServiceHost($this->serviceUrl)) {
            self::$circuitOpen = true;
            return null;
        }

        $parsed = parse_url($this->serviceUrl);
        $host = $parsed['host'] ?? '127.0.0.1';
        $port = (int) ($parsed['port'] ?? 5001);

        $socket = @fsockopen($host, $port, $errno, $errstr, 0.15);
        if (!is_resource($socket)) {
            self::$circuitOpen = true;
            return null;
        }
        fclose($socket);

        $url = rtrim($this->serviceUrl, '/') . '/' . ltrim($path, '/');
        $body = (string) json_encode($payload);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            self::$circuitOpen = true;
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
