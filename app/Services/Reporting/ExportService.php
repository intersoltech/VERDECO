<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Domain\Production\Models\ProductionBatch;
use App\Exports\BatchCostSheetExport;
use App\Exports\MonthlyVarianceReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportService
{
    public function downloadBatchPdf(ProductionBatch $batch): Response
    {
        $batch->load(['plant', 'line', 'materials', 'qualityResults']);

        $pdf = Pdf::loadView('reports.batch-cost-sheet', [
            'batch' => $batch,
        ]);

        return $pdf->download("batch-cost-sheet-{$batch->batch_number}.pdf");
    }

    public function downloadBatchExcel(ProductionBatch $batch): BinaryFileResponse
    {
        $batch->load(['plant', 'line', 'materials']);

        return Excel::download(
            new BatchCostSheetExport($batch),
            "batch-cost-sheet-{$batch->batch_number}.xlsx"
        );
    }

    public function downloadMonthlyVarianceExcel(?int $plantId = null, ?string $month = null): BinaryFileResponse
    {
        $fileName = 'monthly-variance-report-'.($month ?? now()->format('Y-m')).'.xlsx';

        return Excel::download(
            new MonthlyVarianceReportExport($plantId, $month),
            $fileName
        );
    }
}
