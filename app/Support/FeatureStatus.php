<?php

namespace App\Support;

/**
 * Whether a feature is out. A draft is seen only by the Office, marked as
 * a draft wherever it appears; a published feature by everybody; a private
 * one by nobody, the Office included, though it stays in the Office.
 */
enum FeatureStatus: string
{
    /** Still being written: saved however far it has got, and kept from the public. */
    case Draft = 'draft';

    /** Complete, and on the site for everybody. */
    case Published = 'published';

    /** Kept off the site altogether, for everybody, and kept in the Office. */
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Private => 'Private',
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
