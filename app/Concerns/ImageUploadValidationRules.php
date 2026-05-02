<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait ImageUploadValidationRules
{
    /**
     * Validatie voor user-uploaded afbeeldingen.
     *
     * Bewust permissief op input (HEIC/HEIF van iPhone, AVIF, TIFF, etc.) —
     * de ImageProcessor converteert alles naar WebP.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function imageUploadRules(): array
    {
        return [
            'nullable',
            'file',
            'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/heic,image/heif,image/avif,image/tiff,image/bmp',
            'max:20480',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function imageUploadMessages(string $field = 'image'): array
    {
        return [
            "{$field}.file" => 'Het bestand kon niet worden gelezen. Probeer een andere foto.',
            "{$field}.mimetypes" => 'Dit bestandstype wordt niet ondersteund. Gebruik een foto (JPEG, PNG, HEIC, WebP, GIF).',
            "{$field}.max" => 'De foto is te groot (max 20 MB).',
        ];
    }
}
