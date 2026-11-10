<?php

namespace App\Services\Report;

use Carbon\CarbonInterface;

/**
 * A finished report: the letterhead, a title, a few labeled facts about it, and one or more tables.
 * The screen preview, the PDF and the Excel file are all drawn from this one object, so they agree.
 */
final class ReportDocument
{
    /**
     * @param  array<string, string>  $meta  label and value lines under the title, such as the period
     * @param  array<int, ReportTable>  $tables
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly array $meta,
        public readonly array $tables,
        public readonly CompanyProfile $company,
        public readonly string $printedBy,
        public readonly CarbonInterface $printedAt,
    ) {}

    public function fileName(string $extension): string
    {
        return 'laporan-'.$this->slug.'-'.$this->printedAt->format('Ymd-His').'.'.$extension;
    }
}
