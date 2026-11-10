<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class MentorshipLifecycleService
{
    public const STATE_DRAFT = 'DRAFT';
    public const STATE_SUBMITTED = 'SUBMITTED';
    public const STATE_APPROVED = 'APPROVED';
    public const STATE_IN_PROGRESS = 'IN_PROGRESS';
    public const STATE_RESOLVED = 'RESOLVED';

    public const VALID_TRANSITIONS = [
        self::STATE_DRAFT => [self::STATE_SUBMITTED],
        self::STATE_SUBMITTED => [self::STATE_APPROVED, self::STATE_IN_PROGRESS, self::STATE_DRAFT],
        self::STATE_APPROVED => [self::STATE_IN_PROGRESS, self::STATE_RESOLVED],
        self::STATE_IN_PROGRESS => [self::STATE_RESOLVED, self::STATE_APPROVED],
        self::STATE_RESOLVED => [],
    ];

    private PDO $pdo;
    private DecoupledEvaluationService $evaluationService;

    public function __construct(?PDO $pdo = null, ?DecoupledEvaluationService $evaluationService = null)
    {
        $this->evaluationService = $evaluationService ?? new DecoupledEvaluationService($pdo);
        $this->pdo = $this->evaluationService->getPdo();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Retrieve or instantiate the action plan for a faculty member.
     *
     * @return array<string, mixed>|null
     */
    public function getFacultyActionPlan(int $facultyId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT fap.*, u.name AS faculty_name, u.institutional_id AS faculty_institutional_id,
                   d.name AS department_name, d.code AS department_code
            FROM faculty_action_plans fap
            JOIN users u ON u.id = fap.faculty_id
            LEFT JOIN departments d ON d.id = fap.department_id
            WHERE fap.faculty_id = ?
            ORDER BY fap.id DESC
            LIMIT 1
        ');
        $stmt->execute([$facultyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->formatActionPlanRow($row);
    }

    /**
     * Retrieve an action plan by unique plan_id.
     *
     * @return array<string, mixed>|null
     */
    public function getActionPlanById(string $planId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT fap.*, u.name AS faculty_name, u.institutional_id AS faculty_institutional_id,
                   d.name AS department_name, d.code AS department_code
            FROM faculty_action_plans fap
            JOIN users u ON u.id = fap.faculty_id
            LEFT JOIN departments d ON d.id = fap.department_id
            WHERE fap.plan_id = ?
            LIMIT 1
        ');
        $stmt->execute([$planId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->formatActionPlanRow($row);
    }

    /**
     * Submit faculty self-reflection and proposed milestones.
     * Transitions state from DRAFT/PENDING_REVIEW to SUBMITTED.
     *
     * @param array<int, array<string, mixed>> $milestones
     * @return array<string, mixed>
     */
    public function submitFacultyReflection(int $facultyId, string $facultyReflection, array $milestones = []): array
    {
        $plan = $this->getFacultyActionPlan($facultyId);
        if (!$plan) {
            throw new RuntimeException("No active action plan found for faculty ID {$facultyId}.");
        }

        $currentStatus = (string) $plan['status'];
        // Allow submission from DRAFT or PENDING_REVIEW
        if ($currentStatus !== self::STATE_DRAFT && $currentStatus !== 'PENDING_REVIEW' && $currentStatus !== self::STATE_SUBMITTED) {
            throw new InvalidArgumentException("Action plan cannot be submitted from status '{$currentStatus}'.");
        }

        $interventions = is_array($plan['interventions_json']) ? $plan['interventions_json'] : [];
        $interventions['faculty_reflection'] = trim($facultyReflection);
        $interventions['faculty_reflection_submitted_at'] = date('Y-m-d H:i:s');

        // Format milestones with unique IDs
        $formattedMilestones = [];
        foreach ($milestones as $idx => $m) {
            $formattedMilestones[] = [
                'id' => (string) ($m['id'] ?? 'ms-' . ($idx + 1)),
                'title' => (string) ($m['title'] ?? 'Milestone ' . ($idx + 1)),
                'target_date' => (string) ($m['target_date'] ?? date('Y-m-d', strtotime('+30 days'))),
                'is_completed' => !empty($m['is_completed']),
                'completed_at' => !empty($m['is_completed']) ? ($m['completed_at'] ?? date('Y-m-d H:i:s')) : null,
                'notes' => (string) ($m['notes'] ?? ''),
            ];
        }
        $interventions['milestones'] = $formattedMilestones;

        $newStatus = self::STATE_SUBMITTED;
        $updatedJson = json_encode($interventions, JSON_THROW_ON_ERROR);

        $stmt = $this->pdo->prepare('
            UPDATE faculty_action_plans
            SET status = ?, interventions_json = ?, updated_at = ?
            WHERE plan_id = ?
        ');
        $stmt->execute([$newStatus, $updatedJson, date('Y-m-d H:i:s'), $plan['plan_id']]);

        return $this->getActionPlanById((string) $plan['plan_id']) ?? [];
    }

    /**
     * Department Chair assigns senior faculty peer mentor and approves action plan.
     * Transitions state from SUBMITTED to APPROVED / IN_PROGRESS.
     *
     * @return array<string, mixed>
     */
    public function assignMentorAndApprove(string $planId, int $mentorFacultyId, ?string $chairNotes, int $chairUserId): array
    {
        $plan = $this->getActionPlanById($planId);
        if (!$plan) {
            throw new RuntimeException("Action plan '{$planId}' not found.");
        }

        // Verify mentor faculty exists
        $mentorStmt = $this->pdo->prepare('SELECT id, name, institutional_id FROM users WHERE id = ?');
        $mentorStmt->execute([$mentorFacultyId]);
        $mentor = $mentorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$mentor) {
            throw new InvalidArgumentException("Designated mentor faculty ID {$mentorFacultyId} not found.");
        }

        $interventions = is_array($plan['interventions_json']) ? $plan['interventions_json'] : [];
        $interventions['mentor_faculty_id'] = $mentorFacultyId;
        $interventions['mentor_name'] = (string) $mentor['name'];
        $interventions['mentor_institutional_id'] = (string) $mentor['institutional_id'];
        $interventions['chair_assigned_by_user_id'] = $chairUserId;
        $interventions['chair_assigned_at'] = date('Y-m-d H:i:s');

        $currentNotes = (string) ($plan['dean_notes'] ?? '');
        $updatedNotes = trim($currentNotes . "\n" . ($chairNotes ?? ''));

        $newStatus = self::STATE_APPROVED;
        $updatedJson = json_encode($interventions, JSON_THROW_ON_ERROR);

        $stmt = $this->pdo->prepare('
            UPDATE faculty_action_plans
            SET status = ?, interventions_json = ?, dean_notes = ?, updated_at = ?
            WHERE plan_id = ?
        ');
        $stmt->execute([$newStatus, $updatedJson, $updatedNotes, date('Y-m-d H:i:s'), $planId]);

        return $this->getActionPlanById($planId) ?? [];
    }

    /**
     * Update completion progress of an action plan milestone.
     *
     * @return array<string, mixed>
     */
    public function updateMilestoneProgress(string $planId, string $milestoneId, bool $completed, ?string $notes = null): array
    {
        $plan = $this->getActionPlanById($planId);
        if (!$plan) {
            throw new RuntimeException("Action plan '{$planId}' not found.");
        }

        $interventions = is_array($plan['interventions_json']) ? $plan['interventions_json'] : [];
        $milestones = $interventions['milestones'] ?? [];

        $found = false;
        $allCompleted = true;

        foreach ($milestones as &$ms) {
            if ($ms['id'] === $milestoneId) {
                $ms['is_completed'] = $completed;
                $ms['completed_at'] = $completed ? date('Y-m-d H:i:s') : null;
                if ($notes !== null) {
                    $ms['notes'] = trim($notes);
                }
                $found = true;
            }
            if (empty($ms['is_completed'])) {
                $allCompleted = false;
            }
        }
        unset($ms);

        if (!$found) {
            throw new InvalidArgumentException("Milestone '{$milestoneId}' not found in action plan '{$planId}'.");
        }

        $interventions['milestones'] = $milestones;
        $updatedJson = json_encode($interventions, JSON_THROW_ON_ERROR);

        // If in APPROVED and milestone is touched, shift to IN_PROGRESS
        $newStatus = (string) $plan['status'];
        if ($newStatus === self::STATE_APPROVED) {
            $newStatus = self::STATE_IN_PROGRESS;
        }

        $stmt = $this->pdo->prepare('
            UPDATE faculty_action_plans
            SET status = ?, interventions_json = ?, updated_at = ?
            WHERE plan_id = ?
        ');
        $stmt->execute([$newStatus, $updatedJson, date('Y-m-d H:i:s'), $planId]);

        return $this->getActionPlanById($planId) ?? [];
    }

    /**
     * Sign off and resolve an action plan.
     * Transitions state to RESOLVED.
     *
     * @return array<string, mixed>
     */
    public function signOffAndResolve(string $planId, string $resolutionNotes, int $signedOffByUserId): array
    {
        $plan = $this->getActionPlanById($planId);
        if (!$plan) {
            throw new RuntimeException("Action plan '{$planId}' not found.");
        }

        $newStatus = self::STATE_RESOLVED;
        $signedOffAt = date('Y-m-d H:i:s');

        $currentNotes = (string) ($plan['dean_notes'] ?? '');
        $finalNotes = trim($currentNotes . "\n[RESOLVED on {$signedOffAt}]: " . $resolutionNotes);

        $stmt = $this->pdo->prepare('
            UPDATE faculty_action_plans
            SET status = ?, dean_notes = ?, signed_off_by_user_id = ?, signed_off_at = ?, updated_at = ?
            WHERE plan_id = ?
        ');
        $stmt->execute([$newStatus, $finalNotes, $signedOffByUserId, $signedOffAt, $signedOffAt, $planId]);

        return $this->getActionPlanById($planId) ?? [];
    }

    /**
     * Department Chair overview of all departmental action plans and mentorship tracking.
     *
     * @return array<string, mixed>
     */
    public function getDepartmentMentorshipDashboard(int $departmentId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT fap.*, u.name AS faculty_name, u.institutional_id AS faculty_institutional_id,
                   d.name AS department_name, d.code AS department_code
            FROM faculty_action_plans fap
            JOIN users u ON u.id = fap.faculty_id
            LEFT JOIN departments d ON d.id = fap.department_id
            WHERE fap.department_id = ?
            ORDER BY fap.id DESC
        ');
        $stmt->execute([$departmentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $plans = [];
        $statusCounts = [
            self::STATE_DRAFT => 0,
            self::STATE_SUBMITTED => 0,
            self::STATE_APPROVED => 0,
            self::STATE_IN_PROGRESS => 0,
            self::STATE_RESOLVED => 0,
        ];

        foreach ($rows as $r) {
            $formatted = $this->formatActionPlanRow($r);
            $plans[] = $formatted;

            $st = (string) $formatted['status'];
            if (isset($statusCounts[$st])) {
                $statusCounts[$st]++;
            } else {
                $statusCounts[self::STATE_SUBMITTED]++;
            }
        }

        $totalPlans = count($plans);
        $resolvedCount = $statusCounts[self::STATE_RESOLVED];
        $resolutionRatePct = $totalPlans > 0 ? round(($resolvedCount / $totalPlans) * 100, 1) : 100.0;

        return [
            'department_id' => $departmentId,
            'total_action_plans' => $totalPlans,
            'active_action_plans' => $totalPlans - $resolvedCount,
            'resolved_action_plans' => $resolvedCount,
            'resolution_rate_pct' => $resolutionRatePct,
            'status_breakdown' => $statusCounts,
            'action_plans' => $plans,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Helper to decode and format an action plan database row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatActionPlanRow(array $row): array
    {
        $rawInterventions = $row['interventions_json'] ?? null;
        $interventions = [];
        if (is_string($rawInterventions) && trim($rawInterventions) !== '') {
            $decoded = json_decode($rawInterventions, true);
            if (is_array($decoded)) {
                $interventions = $decoded;
            }
        } elseif (is_array($rawInterventions)) {
            $interventions = $rawInterventions;
        }

        $milestones = $interventions['milestones'] ?? [];
        $totalMilestones = count($milestones);
        $completedMilestones = 0;
        foreach ($milestones as $ms) {
            if (!empty($ms['is_completed'])) {
                $completedMilestones++;
            }
        }
        $milestoneProgressPct = $totalMilestones > 0 ? round(($completedMilestones / $totalMilestones) * 100, 1) : 0.0;

        return [
            'id' => (int) $row['id'],
            'plan_id' => (string) $row['plan_id'],
            'faculty_id' => (int) $row['faculty_id'],
            'faculty_name' => (string) ($row['faculty_name'] ?? 'Faculty Member'),
            'faculty_institutional_id' => (string) ($row['faculty_institutional_id'] ?? ''),
            'department_id' => (int) ($row['department_id'] ?? 1),
            'department_name' => (string) ($row['department_name'] ?? 'College of Computer Studies'),
            'primary_cluster' => (string) ($row['primary_cluster'] ?? 'Pedagogical Delivery'),
            'status' => (string) ($row['status'] ?? self::STATE_SUBMITTED),
            'urgency_level' => (string) ($row['urgency_level'] ?? 'MODERATE'),
            'mean_score' => (float) $row['mean_score'],
            'negative_sentiment_pct' => (float) $row['negative_sentiment_pct'],
            'interventions_json' => $interventions,
            'mentor_faculty_id' => $interventions['mentor_faculty_id'] ?? null,
            'mentor_name' => $interventions['mentor_name'] ?? null,
            'faculty_reflection' => $interventions['faculty_reflection'] ?? null,
            'milestones' => $milestones,
            'total_milestones' => $totalMilestones,
            'completed_milestones' => $completedMilestones,
            'milestone_progress_pct' => $milestoneProgressPct,
            'dean_notes' => $row['dean_notes'] ?? null,
            'signed_off_by_user_id' => $row['signed_off_by_user_id'] ? (int) $row['signed_off_by_user_id'] : null,
            'signed_off_at' => $row['signed_off_at'] ?? null,
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $row['updated_at'] ?? date('Y-m-d H:i:s'),
        ];
    }
}
