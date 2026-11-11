<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\DecoupledEvaluationService;
use Illuminate\Database\Seeder;
use PDO;

class EvaluaTechDemoSeeder extends Seeder
{
    private PDO $pdo;
    private DecoupledEvaluationService $evaluationService;

    public function __construct(?PDO $pdo = null)
    {
        $this->evaluationService = new DecoupledEvaluationService($pdo);
        $this->pdo = $this->evaluationService->getPdo();
    }

    public function run(): void
    {
        $this->seed();
    }

    /**
     * Seed the canonical EvaluaTech demonstration dataset (5 roles, student 2024-10892 with 6 courses, 3 of 6 completed).
     *
     * @return array<string, mixed>
     */
    public function seed(): array
    {
        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        // Reset clearances, vault, enrollments, sections, courses, and users cleanly on re-seed
        $this->pdo->exec("DELETE FROM evaluation_clearances");
        $this->pdo->exec("DELETE FROM evaluation_vault");
        $this->pdo->exec("DELETE FROM enrollments");
        $this->pdo->exec("DELETE FROM course_sections");
        $this->pdo->exec("DELETE FROM courses");
        $this->pdo->exec("DELETE FROM users");
        $this->pdo->exec("DELETE FROM departments");

        // 1. Departments
        $this->pdo->exec("
            INSERT INTO departments (id, code, name, college_name, created_at, updated_at)
            VALUES
                (1, 'CCS', 'Department of Computer Science', 'College of Computer Studies', '{$now}', '{$now}'),
                (2, 'CAS', 'Department of General Education', 'College of Arts and Sciences', '{$now}', '{$now}')
        ");

        // 2. Academic Terms (1st Semester & 2nd Semester for AY 2026-2027)
        $this->pdo->exec("
            INSERT OR REPLACE INTO academic_terms (id, code, name, academic_year, is_active, evaluation_open, starts_on, ends_on, created_at, updated_at)
            VALUES 
                (1, '1SEM-2026', '1st Semester', '2026-2027', 1, 1, '2026-08-15', '2026-12-15', '{$now}', '{$now}'),
                (2, '2SEM-2026', '2nd Semester', '2026-2027', 0, 0, '2027-01-15', '2027-05-30', '{$now}', '{$now}')
        ");

        // 2b. Evaluation Window Configuration (Mandatory Timing Policy: 0s min dwell, schedule in Asia/Manila)
        try {
            $this->pdo->exec("
                INSERT OR REPLACE INTO evaluation_window_configs (
                    academic_term_id, status, min_dwell_seconds, straight_line_variance_threshold,
                    watchlist_mean_threshold, watchlist_negative_sentiment_pct, announcement_message,
                    starts_at, ends_at, timezone, last_window_action, created_at, updated_at
                ) VALUES (
                    1, 'OPEN', 0, 0.0, 3.50, 35.0,
                    'School AAAAA13 Student Evaluation of Teachers (SET) is currently active for 1st Semester AY 2026-2027.',
                    '2026-08-15 08:00:00', '2026-12-20 23:59:59', 'Asia/Manila', 'OPEN',
                    '{$now}', '{$now}'
                )
            ");
        } catch (\Throwable) {
            // column or table compatibility
        }

        $passwordHash = password_hash('evaluatech123', PASSWORD_BCRYPT);

        // 3. Canonical Five-Role Institutional Users + 6 Course Instructors + 2 Students
        $users = [
            [1, 'ADM-00001', 'University Registrar Admin', 'admin@aaaaa13.edu', 'admin', 1],
            [2, 'DEAN-0001', 'Dr. V. Mendoza', 'dean@aaaaa13.edu', 'dean', null],
            [3, 'CHR-00001', 'Dr. L. Bautista', 'chair@aaaaa13.edu', 'chair', 1],
            [4, 'FAC-10001', 'Prof. A. Dela Cruz', 'adelacruz@aaaaa13.edu', 'faculty', 1],
            [5, 'FAC-10002', 'Dr. M. Santos', 'msantos@aaaaa13.edu', 'faculty', 1],
            [6, 'FAC-10003', 'Prof. E. Rivera', 'erivera@aaaaa13.edu', 'faculty', 2],
            [7, 'FAC-10004', 'Dr. R. Garcia', 'rgarcia@aaaaa13.edu', 'faculty', 2],
            [8, 'FAC-10005', 'Prof. K. Gomez', 'kgomez@aaaaa13.edu', 'faculty', 1],
            [9, 'FAC-10006', 'Engr. J. Luna', 'jluna@aaaaa13.edu', 'faculty', 2],
            [10, '2024-10892', 'Juan Dela Cruz', '2024-10892@aaaaa13.edu', 'student', 1],
            [11, '2024-10955', 'Maria Clara Reyes', '2024-10955@aaaaa13.edu', 'student', 1],
        ];

        $userStmt = $this->pdo->prepare("
            INSERT INTO users (id, institutional_id, name, email, password, role, department_id, is_active, created_at, updated_at)
            VALUES (:id, :inst_id, :name, :email, :password, :role, :dept_id, 1, :created_at, :updated_at)
        ");

        foreach ($users as [$id, $instId, $name, $email, $role, $deptId]) {
            $userStmt->execute([
                ':id' => $id,
                ':inst_id' => $instId,
                ':name' => $name,
                ':email' => $email,
                ':password' => $passwordHash,
                ':role' => $role,
                ':dept_id' => $deptId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        // 4. Six Canonical Courses from Chapter II Wireframe (3 CCS, 3 CAS)
        $courses = [
            [1, 1, 'CS-401', 'Design and Analysis of Algorithms', 3, 4, 1, 1, 42],
            [2, 1, 'CS-405', 'Artificial Intelligence & NLP', 3, 5, 1, 1, 38],
            [3, 2, 'ENG-102', 'Academic & Technical Communication', 3, 6, 1, 0, 45],
            [4, 2, 'MATH-201', 'Discrete Mathematics & Probability', 3, 7, 0, 0, 40],
            [5, 1, 'CS-408', 'Software Engineering & Quality Assurance', 3, 8, 0, 0, 36],
            [6, 2, 'PHYS-101', 'University Physics for Computing', 3, 9, 0, 0, 44],
        ];

        $courseStmt = $this->pdo->prepare("
            INSERT INTO courses (id, department_id, code, title, units, created_at, updated_at)
            VALUES (:id, :dept_id, :code, :title, :units, :created_at, :updated_at)
        ");

        $sectionStmt = $this->pdo->prepare("
            INSERT INTO course_sections (id, course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock, enrolled_count, created_at, updated_at)
            VALUES (:id, :course_id, :faculty_id, 1, :section_code, :grades_submitted, :registrar_lock, :enrolled_count, :created_at, :updated_at)
        ");

        $enrollStmt = $this->pdo->prepare("
            INSERT INTO enrollments (student_id, course_section_id, academic_term_id, enrollment_status, created_at, updated_at)
            VALUES (:student_id, :section_id, 1, 'ENROLLED', :created_at, :updated_at)
        ");

        foreach ($courses as [$cid, $deptId, $code, $title, $units, $facultyId, $gradesSub, $regLock, $enrolledCount]) {
            $courseStmt->execute([
                ':id' => $cid,
                ':dept_id' => $deptId,
                ':code' => $code,
                ':title' => $title,
                ':units' => $units,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $sectionStmt->execute([
                ':id' => $cid,
                ':course_id' => $cid,
                ':faculty_id' => $facultyId,
                ':section_code' => 'SEC-A',
                ':grades_submitted' => $gradesSub,
                ':registrar_lock' => $regLock,
                ':enrolled_count' => $enrolledCount,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            // Enroll both Student 10 (2024-10892) and Student 11 (2024-10955) in sections 1..6
            $enrollStmt->execute([
                ':student_id' => 10,
                ':section_id' => $cid,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $enrollStmt->execute([
                ':student_id' => 11,
                ':section_id' => $cid,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        // Additional CS-401 SEC-B section for Prof. A. Dela Cruz (FAC-10001) so instructor workspace shows both unlocked & locked DB sections
        $sectionStmt->execute([
            ':id' => 7,
            ':course_id' => 1,
            ':faculty_id' => 4,
            ':section_code' => 'SEC-B',
            ':grades_submitted' => 0,
            ':registrar_lock' => 0,
            ':enrolled_count' => 39,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        // 5. Submit 3 of 6 courses for Student 2024-10892 (Queue shows 3 of 6 Completed)
        $student10Submissions = [
            [
                'student_id' => 10,
                'course_section_id' => 1,
                'submission_key' => 'SEED-2024-10892-CS401',
                'ratings' => [5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5],
                'dwell_time_seconds' => 185,
                'comment_text' => 'Explains complex algorithms very clearly and provides great practical code examples.',
                'professor_questions' => [
                    'three_words' => ['Malinaw', 'Structured', 'Approachable'],
                    'room_for_improvements' => ['More timed dynamic programming practice drills before midterms'],
                    'teaching_strengths' => 'Breaks down recurrence relations and graph complexity step by step.',
                    'recommendations' => 'Keep sharing annotated code walkthroughs after lab sessions.',
                ],
            ],
            [
                'student_id' => 10,
                'course_section_id' => 2,
                'submission_key' => 'SEED-2024-10892-CS405',
                'ratings' => [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5],
                'dwell_time_seconds' => 210,
                'comment_text' => 'Very organized lectures and helpful guidance on NLP lab exercises.',
                'professor_questions' => [
                    'three_words' => ['Organized', 'Supportive', 'Mahusay'],
                    'room_for_improvements' => ['Provide sample Taglish corpus datasets earlier in the term'],
                    'teaching_strengths' => 'Clear explanation of tokenization, lemmatization, and sentiment pipelines.',
                    'recommendations' => 'Include an optional workshop on fine-tuning local transformer models.',
                ],
            ],
            [
                'student_id' => 10,
                'course_section_id' => 3,
                'submission_key' => 'SEED-2024-10892-ENG102',
                'ratings' => [4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4],
                'dwell_time_seconds' => 160,
                'comment_text' => 'Respectful and supportive classroom atmosphere with clear writing rubrics.',
                'professor_questions' => [
                    'three_words' => ['Respectful', 'Patient', 'Clear'],
                    'room_for_improvements' => ['Return peer-reviewed technical drafts one week earlier'],
                    'teaching_strengths' => 'Constructive writing feedback and transparent grading rubrics.',
                    'recommendations' => 'Add IEEE research paper formatting templates to the course syllabus.',
                ],
            ],
        ];

        foreach ($student10Submissions as $submission) {
            $this->evaluationService->submitEvaluation($submission);
        }

        // 5b. Submit all 6 of 6 courses for Student 2024-10955 (Full Evaluation Completion & Participation Receipt)
        $student11Submissions = [
            [
                'student_id' => 11,
                'course_section_id' => 1,
                'submission_key' => 'SEED-2024-10955-CS401',
                'ratings' => [5, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 5, 4, 5, 5],
                'dwell_time_seconds' => 195,
                'comment_text' => 'Excellent mastery of algorithmic complexity and fair problem set grading.',
            ],
            [
                'student_id' => 11,
                'course_section_id' => 2,
                'submission_key' => 'SEED-2024-10955-CS405',
                'ratings' => [4, 5, 4, 5, 4, 5, 4, 5, 4, 4, 5, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 5, 4],
                'dwell_time_seconds' => 178,
                'comment_text' => 'Great hands-on NLP laboratory sessions and clear project expectations.',
            ],
            [
                'student_id' => 11,
                'course_section_id' => 3,
                'submission_key' => 'SEED-2024-10955-ENG102',
                'ratings' => [4, 4, 5, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4],
                'dwell_time_seconds' => 152,
                'comment_text' => 'Helpful feedback on technical documentation structure and oral defense.',
            ],
            [
                'student_id' => 11,
                'course_section_id' => 4,
                'submission_key' => 'SEED-2024-10955-MATH201',
                'ratings' => [4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4, 4, 4, 3, 4],
                'dwell_time_seconds' => 168,
                'comment_text' => 'Good coverage of discrete probability proofs, though pacing on combinatorics can improve.',
            ],
            [
                'student_id' => 11,
                'course_section_id' => 5,
                'submission_key' => 'SEED-2024-10955-CS408',
                'ratings' => [3, 3, 3, 4, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 3, 3, 4, 3, 3],
                'dwell_time_seconds' => 204,
                'comment_text' => 'Takes over a month to return sprint grading rubrics and arrives late to morning lectures.',
                'professor_questions' => [
                    'three_words' => ['Delayed', 'Unstructured', 'Rushed'],
                    'room_for_improvements' => ['Return quiz and sprint rubrics within two weeks', 'Start morning classes on time'],
                    'teaching_strengths' => 'Shares real industry software testing examples when time permits.',
                    'recommendations' => 'Publish clear grading rubrics and adhere to scheduled consultation hours.',
                ],
            ],
            [
                'student_id' => 11,
                'course_section_id' => 6,
                'submission_key' => 'SEED-2024-10955-PHYS101',
                'ratings' => [4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4, 4, 4, 4, 5, 4],
                'dwell_time_seconds' => 172,
                'comment_text' => 'Clear physics laboratory demonstrations and fair problem-solving exams.',
            ],
        ];

        foreach ($student11Submissions as $submission) {
            $this->evaluationService->submitEvaluation($submission);
        }

        // 5c. Seed realistic section clearances across enrolled cohorts for institutional demonstration
        $targetClearances = [
            1 => 39,
            2 => 36,
            3 => 38,
            4 => 30,
            5 => 34,
            6 => 37,
            7 => 35,
        ];

        $cohortClrStmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO evaluation_clearances (
                student_id, course_section_id, academic_term_id, submission_key, status, clearance_token, cleared_at, created_at, updated_at
            ) VALUES (
                :student_id, :course_section_id, 1, :submission_key, 'CLEARED', :clearance_token, :cleared_at, :created_at, :updated_at
            )
        ");

        $appKey = 'evaluatech-app-signing-secret-2026-key';
        foreach ($targetClearances as $secId => $targetCount) {
            $chk = $this->pdo->prepare("SELECT COUNT(*) FROM evaluation_clearances WHERE course_section_id = :sec_id AND status = 'CLEARED'");
            $chk->execute([':sec_id' => $secId]);
            $existing = (int) $chk->fetchColumn();

            $secDeptId = ($secId === 3 || $secId === 4 || $secId === 6) ? 2 : 1;

            for ($i = $existing + 1; $i <= $targetCount; $i++) {
                $syntheticStudentId = 1000 + ($secId * 100) + $i;
                $subKey = "SEED-SEC{$secId}-STU{$syntheticStudentId}";
                $token = "EVT.CLR.SEC{$secId}.STU{$syntheticStudentId}." . substr(hash_hmac('sha256', $subKey, $appKey), 0, 32);

                $userStmt->execute([
                    ':id' => $syntheticStudentId,
                    ':inst_id' => 'STU-' . $syntheticStudentId,
                    ':name' => 'Cohort Student ' . $syntheticStudentId,
                    ':email' => 'stu' . $syntheticStudentId . '@aaaaa13.edu',
                    ':password' => $passwordHash,
                    ':role' => 'student',
                    ':dept_id' => $secDeptId,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);

                $enrollStmt->execute([
                    ':student_id' => $syntheticStudentId,
                    ':section_id' => $secId,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);

                $cohortClrStmt->execute([
                    ':student_id' => $syntheticStudentId,
                    ':course_section_id' => $secId,
                    ':submission_key' => $subKey,
                    ':clearance_token' => $token,
                    ':cleared_at' => $now,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }
        }

        // 6. Seed additional anonymized vault records across sections (Zero student identity fields)
        $vaultStmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO evaluation_vault (
                id, course_section_id, faculty_id, academic_term_id,
                ratings_json, mean_score, rating_variance, is_straight_lined,
                dwell_time_seconds, comment_text, comment_hash,
                sentiment_polarity, sentiment_valence, pedagogical_tag, recorded_date
            ) VALUES (
                :id, :section_id, :faculty_id, 1,
                :ratings_json, :mean_score, :rating_variance, 0,
                195, :comment_text, :comment_hash,
                :polarity, :valence, :tag, :recorded_date
            )
        ");

        $additionalVaultRows = [
            [
                ':id' => '00000000-0000-4000-8000-000000000001',
                ':section_id' => 1,
                ':faculty_id' => 4,
                ':ratings_json' => json_encode([5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4, 5, 5, 5, 4]),
                ':mean_score' => 4.75,
                ':rating_variance' => 0.1875,
                ':comment_text' => 'Clear explanations of graph algorithms and responsive during consultation hours.',
                ':comment_hash' => hash_hmac('sha256', 'seed-vault-cs401-a', 'evaluatech-hmac-signing-secret-2026'),
                ':polarity' => 0.7400,
                ':valence' => 'Positive',
                ':tag' => 'Pedagogical Delivery',
                ':recorded_date' => $today,
            ],
            [
                ':id' => '00000000-0000-4000-8000-000000000004',
                ':section_id' => 4,
                ':faculty_id' => 7,
                ':ratings_json' => json_encode([4, 4, 3, 4, 4, 3, 4, 4, 3, 4, 4, 3, 4, 4, 3, 4, 4, 3, 4, 4]),
                ':mean_score' => 3.70,
                ':rating_variance' => 0.2100,
                ':comment_text' => 'Covers discrete math syllabus steadily; additional worked proof examples would help.',
                ':comment_hash' => hash_hmac('sha256', 'seed-vault-math201-a', 'evaluatech-hmac-signing-secret-2026'),
                ':polarity' => 0.0200,
                ':valence' => 'Neutral',
                ':tag' => 'Pedagogical Delivery',
                ':recorded_date' => $today,
            ],
            [
                ':id' => '00000000-0000-4000-8000-000000000005',
                ':section_id' => 5,
                ':faculty_id' => 8,
                ':ratings_json' => json_encode([3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3]),
                ':mean_score' => 3.10,
                ':rating_variance' => 0.0900,
                ':comment_text' => 'Takes over a month to return quiz scores and provides no helpful feedback.',
                ':comment_hash' => hash_hmac('sha256', 'seed-watchlist-cs408-1', 'evaluatech-hmac-signing-secret-2026'),
                ':polarity' => -0.6200,
                ':valence' => 'Negative',
                ':tag' => 'Assessment & Grading',
                ':recorded_date' => $today,
            ],
            [
                ':id' => '00000000-0000-4000-8000-000000000006',
                ':section_id' => 5,
                ':faculty_id' => 8,
                ':ratings_json' => json_encode([3, 3, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 3]),
                ':mean_score' => 3.10,
                ':rating_variance' => 0.0900,
                ':comment_text' => 'Arrives to morning lectures 15 minutes late consistently without prior notice.',
                ':comment_hash' => hash_hmac('sha256', 'seed-watchlist-cs408-2', 'evaluatech-hmac-signing-secret-2026'),
                ':polarity' => -0.4500,
                ':valence' => 'Negative',
                ':tag' => 'Classroom Management',
                ':recorded_date' => $today,
            ],
            [
                ':id' => '00000000-0000-4000-8000-000000000007',
                ':section_id' => 6,
                ':faculty_id' => 9,
                ':ratings_json' => json_encode([4, 4, 4, 4, 4, 4, 4, 5, 4, 4, 4, 4, 4, 5, 4, 4, 4, 4, 4, 4]),
                ':mean_score' => 4.10,
                ':rating_variance' => 0.0900,
                ':comment_text' => 'Well-structured physics lab experiments and clear safety guidelines.',
                ':comment_hash' => hash_hmac('sha256', 'seed-vault-phys101-a', 'evaluatech-hmac-signing-secret-2026'),
                ':polarity' => 0.5200,
                ':valence' => 'Positive',
                ':tag' => 'Classroom Management',
                ':recorded_date' => $today,
            ],
        ];

        foreach ($additionalVaultRows as $vRow) {
            $vaultStmt->execute($vRow);
        }

        return [
            'seeded' => true,
            'student_institutional_id' => '2024-10892',
            'courses_enrolled' => 6,
            'courses_completed' => 3,
        ];
    }
}
