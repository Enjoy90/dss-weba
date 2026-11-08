<?php

namespace App\Services\Import;

/**
 * The outcome of checking a file, before anything is saved.
 */
final class ImportReport
{
    /**
     * @param  array<int, string>  $fileErrors  problems with the whole file, nothing can be imported
     * @param  array<int, RowResult>  $rows
     * @param  array<int, string>  $notes  remarks that do not block the import
     */
    public function __construct(
        public readonly array $fileErrors = [],
        public readonly array $rows = [],
        public readonly array $notes = [],
    ) {}

    public function count(string $action): int
    {
        return count(array_filter($this->rows, fn (RowResult $row): bool => $row->action === $action));
    }

    public function created(): int
    {
        return $this->count(RowResult::CREATE);
    }

    public function updated(): int
    {
        return $this->count(RowResult::UPDATE);
    }

    public function skipped(): int
    {
        return $this->count(RowResult::SKIP);
    }

    public function rejected(): int
    {
        return $this->count(RowResult::ERROR);
    }

    public function importableCount(): int
    {
        return $this->created() + $this->updated();
    }

    public function canImport(): bool
    {
        return $this->fileErrors === [] && $this->importableCount() > 0;
    }
}
