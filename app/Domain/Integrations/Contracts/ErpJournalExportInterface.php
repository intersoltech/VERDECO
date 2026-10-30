<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Contracts;

use App\Domain\Costing\Models\DailyProductionSummary;
use App\Domain\Production\Models\ProductionBatch;

interface ErpJournalExportInterface
{
    /**
     * Generate standard accounting journal entries for a closed batch.
     *
     * @param ProductionBatch $batch
     * @return array<int, array<string, mixed>>
     */
    public function exportBatchJournal(ProductionBatch $batch): array;

    /**
     * Generate standard accounting journal entries for a daily production summary.
     *
     * @param DailyProductionSummary $summary
     * @return array<int, array<string, mixed>>
     */
    public function exportDailyJournal(DailyProductionSummary $summary): array;
}
