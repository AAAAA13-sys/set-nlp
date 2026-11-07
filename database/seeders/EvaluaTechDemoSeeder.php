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

        // 1. Departments
        $this->pdo->exec("
            INSERT OR IGNORE INTO departments (id, code, name, college_name, created_at, updated_at)
            VALUES
                (1, 'CCS', 'Department of Computer Science', 'College of Computer Studies', '{$now}', '{$now}'),
                (2, 'GENED', 'Department of General Education', 'College of Arts and Sciences', '{$now}', '{$now}')
        ");

        // 2. Active Academic Term (Fall)
        $this->pdo->exec("
            INSERT OR IGNORE INTO academic_terms (id, code, name, academic_year, is_active, evaluation_open, starts_on, ends_on, created_at, updated_at)
            VALUES (1, 'FALL-2026', 'Fall', '2026-2027', 1, 1, '2026-08-15', '2026-12-15', '{$now}', '{$now}')
        ");

        $passwordHash = password_hash('evaluatech123', PASSWORD_BCRYPT);

        // 3. Canonical Five-Role Institutional Users + 6 Course Instructors
        $users = [
            [1, 'ADM-00001', 'Registrar System Admin', 'admin@evaluatech.edu', 'admin', 1],
            [2, 'DEAN-0001', 'Dr. V. Mendoza (Dean)', 'dean@evaluatech.edu', 'dean', 1],
            [3, 'CHR-00001', 'Dr. L. Bautista (Chair)', 'chair@evaluatech.edu', 'chair', 1],
            [4, 'FAC-10001', 'Prof. A. Dela Cruz', 'adelacruz@evaluatech.edu', 'faculty', 1],
            [5, 'FAC-10002', 'Dr. M. Santos', 'msantos@evaluatech.edu', 'faculty', 1],
            [6, 'FAC-10003', 'Prof. E. Rivera', 'erivera@evaluatech.edu', 'faculty', 2],
            [7, 'FAC-10004', 'Dr. R. Garcia', 'rgarcia@evaluatech.edu', 'faculty', 1],
            [8, 'FAC-10005', 'Prof. K. Gomez', 'kgomez@evaluatech.edu', 'faculty', 1],
            [9, 'FAC-10006', 'Engr. J. Luna', 'jluna@evaluatech.edu', 'faculty', 2],
            [10, '2024-10892', 'Juan Dela Cruz (2024-10892)', '2024-10892@student.evaluatech.edu', 'student', 1],
        ];

        $userStmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO users (id, institutional_id, name, email, password, role, department_id, is_active, created_at, updated_at)
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

        // 4. Six Canonical Courses from Chapter II Wireframe
        $courses = [
            [1, 1, 'CS-401', 'Design and Analysis of Algorithms', 3, 4, 1, 1],
            [2, 1, 'CS-405', 'Artificial Intelligence & NLP', 3, 5, 1, 1],
            [3, 2, 'ENG-102', 'Academic & Technical Communication', 3, 6, 1, 0],
            [4, 1, 'MATH-201', 'Discrete Mathematics & Probability', 3, 7, 0, 0],
            [5, 1, 'CS-408', 'Software Engineering & Quality Assurance', 3, 8, 0, 0],
            [6, 2, 'PHYS-101', 'University Physics for Computing', 3, 9, 0, 0],
        ];

        $courseStmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO courses (id, department_id, code, title, units, created_at, updated_at)
            VALUES (:id, :dept_id, :code, :title, :units, :created_at, :updated_at)
        ");

        $sectionStmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO course_sections (id, course_id, faculty_id, academic_term_id, section_code, term_grades_submitted, registrar_lock, enrolled_count, created_at, updated_at)
            VALUES (:id, :course_id, :faculty_id, 1, 'SEC-A', :grades_submitted, :registrar_lock, 42, :created_at, :updated_at)
        ");

        $enrollStmt = $this->pdo->prepare("
            INSERT OR IGNORE INTO enrollments (id, student_id, course_section_id, academic_term_id, enrollment_status, created_at, updated_at)
            VALUES (:id, 10, :section_id, 1, 'ENROLLED', :created_at, :updated_at)
        ");

        foreach ($courses as [$cid, $deptId, $code, $title, $units, $facultyId, $gradesSub, $regLock]) {
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
                ':grades_submitted' => $gradesSub,
                ':registrar_lock' => $regLock,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $enrollStmt->execute([
                ':id' => $cid,
                ':section_id' => $cid,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }

        // 5. Submit the first 3 courses for Student 2024-10892 so queue shows "3 of 6 Completed"
        $completedSubmissions = [
            [
                'student_id' => 10,
                'course_section_id' => 1,
                'submission_key' => 'SEED-2024-10892-CS401',
                'ratings' => [5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5, 4, 5, 5],
                'dwell_time_seconds' => 185,
                'comment_text' => 'Explains complex algorithms very clearly and provides great practical code examples.',
            ],
            [
                'student_id' => 10,
                'course_section_id' => 2,
                'submission_key' => 'SEED-2024-10892-CS405',
                'ratings' => [5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4, 5, 4],
                'dwell_time_seconds' => 210,
                'comment_text' => 'Very organized lectures and helpful guidance on NLP lab exercises.',
            ],
            [
                'student_id' => 10,
                'course_section_id' => 3,
                'submission_key' => 'SEED-2024-10892-ENG102',
                'ratings' => [4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5, 4, 4, 5],
                'dwell_time_seconds' => 160,
                'comment_text' => 'Respectful and supportive classroom atmosphere with clear writing rubrics.',
            ],
        ];

        foreach ($completedSubmissions as $submission) {
            $this->evaluationService->submitEvaluation($submission);
        }

        // 6. Seed additional anonymized vault records for Watchlist demonstration (Chapter II/III benchmarks)
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

        $vaultStmt->execute([
            ':id' => '00000000-0000-4000-8000-000000000004',
            ':section_id' => 4,
            ':faculty_id' => 7,
            ':ratings_json' => json_encode([3, 3, 3, 3, 4, 3, 3, 3, 3, 3, 3, 3, 3, 3, 4, 3, 3, 3, 3, 3]),
            ':mean_score' => 3.10,
            ':rating_variance' => 0.0900,
            ':comment_text' => 'Takes over a month to return quiz scores and provides no helpful feedback.',
            ':comment_hash' => hash_hmac('sha256', 'seed-watchlist-1', 'evaluatech-hmac-signing-secret-2026'),
            ':polarity' => -0.6200,
            ':valence' => 'Negative',
            ':tag' => 'Assessment & Grading',
            ':recorded_date' => $today,
        ]);

        $vaultStmt->execute([
            ':id' => '00000000-0000-4000-8000-000000000005',
            ':section_id' => 5,
            ':faculty_id' => 8,
            ':ratings_json' => json_encode([3, 4, 3, 3, 4, 3, 3, 4, 3, 3, 4, 3, 3, 4, 3, 3, 4, 3, 3, 3]),
            ':mean_score' => 3.30,
            ':rating_variance' => 0.2100,
            ':comment_text' => 'Arrives to morning lectures 15 minutes late consistently.',
            ':comment_hash' => hash_hmac('sha256', 'seed-watchlist-2', 'evaluatech-hmac-signing-secret-2026'),
            ':polarity' => -0.4500,
            ':valence' => 'Negative',
            ':tag' => 'Classroom Management',
            ':recorded_date' => $today,
        ]);

        return [
            'seeded' => true,
            'student_institutional_id' => '2024-10892',
            'courses_enrolled' => 6,
            'courses_completed' => 3,
        ];
    }
}
