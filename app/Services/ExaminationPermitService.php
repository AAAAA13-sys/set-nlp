<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ExaminationPermitService
 *
 * Evaluation clearance service proxy maintaining backward compatibility
 * for legacy tests and contracts while delegating to EvaluationClearanceService.
 */
class ExaminationPermitService extends EvaluationClearanceService
{
}
