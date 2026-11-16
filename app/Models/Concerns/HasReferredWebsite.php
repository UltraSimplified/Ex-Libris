<?php

namespace App\Models\Concerns;

use App\Support\AffiliateLink;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

/**
 * A promoter's or a festival's website, tagged as Good on Paper's referral
 * as a venue's is, credited to "promoter-12" or "festival-3". A link that
 * is not a web address is passed on as it was.
 *
 * Appended only on the owner's own page, where the link is drawn.
 */
trait HasReferredWebsite
{
    protected function websiteLink(): Attribute
    {
        return Attribute::get(function (): ?string {
            $website = trim((string) $this->website);

            if ($website === '') {
                return null;
            }

            return AffiliateLink::tag($website, Str::lower(class_basename($this)).'-'.$this->id) ?? $website;
        });
    }
}
