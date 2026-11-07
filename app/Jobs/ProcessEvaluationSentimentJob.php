<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AnalyticsClientService;
use App\Services\DecoupledEvaluationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PDO;

class ProcessEvaluationSentimentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $vaultId,
        private string $commentText
    ) {
    }

    /**
     * Execute asynchronous sentiment polarity and pedagogical domain classification on an anonymized vault record.
     *
     * @return array{vault_id: string, polarity: float, valence: string, pedagogical_tag: string}
     */
    public function handle(
        ?AnalyticsClientService $analytics = null,
        ?PDO $pdo = null
    ): array {
        $client = $analytics ?? new AnalyticsClientService();
        $result = $client->analyzeComment($this->commentText);

        $db = $pdo ?? DecoupledEvaluationService::getSharedPdo();
        $stmt = $db->prepare("
            UPDATE evaluation_vault
            SET sentiment_polarity = :polarity,
                sentiment_valence = :valence,
                pedagogical_tag = :tag
            WHERE id = :id
        ");
        $stmt->execute([
            ':polarity' => $result['polarity'],
            ':valence' => $result['valence'],
            ':tag' => $result['pedagogical_tag'],
            ':id' => $this->vaultId,
        ]);

        return [
            'vault_id' => $this->vaultId,
            'polarity' => $result['polarity'],
            'valence' => $result['valence'],
            'pedagogical_tag' => $result['pedagogical_tag'],
        ];
    }
}
