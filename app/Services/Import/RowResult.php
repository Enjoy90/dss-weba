<?php

namespace App\Services\Import;

/**
 * What will happen to one spreadsheet row when the import is confirmed.
 */
final class RowResult
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const SKIP = 'skip';

    public const ERROR = 'error';

    /**
     * @param  array<int, string>  $messages  reasons, filled for skipped and rejected rows
     * @param  array<string, mixed>  $data  the cleaned values, filled for rows that will be written
     */
    public function __construct(
        public readonly int $row,
        public readonly string $action,
        public readonly array $messages,
        public readonly array $data,
        public readonly ?string $number,
    ) {}

    public function isImportable(): bool
    {
        return $this->action === self::CREATE || $this->action === self::UPDATE;
    }
}
