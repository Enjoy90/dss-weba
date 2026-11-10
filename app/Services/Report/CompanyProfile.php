<?php

namespace App\Services\Report;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * The company details for the letterhead of a report (PRD F13 function 6 and F15 function 1). They are read from the
 * settings table, where the settings page (P9-02) will write them. Until then the name falls back to the default.
 */
final class CompanyProfile
{
    public const DEFAULT_NAME = 'PT Weba International';

    public function __construct(
        public readonly string $name,
        public readonly ?string $address,
        public readonly ?string $phone,
        public readonly ?string $logoPath,
    ) {}

    public static function load(): self
    {
        $name = trim((string) Setting::get('company_name', ''));
        $logo = trim((string) Setting::get('company_logo', ''));
        $disk = Storage::disk('public');

        return new self(
            $name === '' ? self::DEFAULT_NAME : $name,
            self::clean(Setting::get('company_address')),
            self::clean(Setting::get('company_phone')),
            $logo !== '' && $disk->exists($logo) ? $disk->path($logo) : null,
        );
    }

    /**
     * The address and phone as the lines under the company name. Empty details are left out.
     *
     * @return array<int, string>
     */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->address,
            $this->phone === null ? null : 'Telepon '.$this->phone,
        ]));
    }

    /**
     * The logo as a data URI the PDF engine can draw without fetching anything, or null.
     */
    public function logoDataUri(): ?string
    {
        if ($this->logoPath === null || ! is_file($this->logoPath)) {
            return null;
        }

        $mime = mime_content_type($this->logoPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($this->logoPath));
    }

    private static function clean(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
