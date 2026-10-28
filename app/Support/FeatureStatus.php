<?php

namespace App\Support;

/**
 * Whether a feature is out. A draft is seen only by the Office, marked as
 * a draft wherever it appears; a published feature by everybody.
 */
enum FeatureStatus: string
{
    /** Still being written: saved however far it has got, and kept from the public. */
    case Draft = 'draft';

    /** Complete, and on the site for everybody. */
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
        };
    }

    /**
     * Each status by its label, as the Office's picker offers them.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->label()])
            ->all();
    }
}
