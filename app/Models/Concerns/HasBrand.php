<?php

namespace App\Models\Concerns;

use App\Support\Brand;

/**
 * The four colours a venue or promoter is known by, which their gig
 * widgets are painted in.
 */
trait HasBrand
{
    /**
     * The owner may change their colours from the dashboard.
     */
    public function initializeHasBrand(): void
    {
        $this->mergeFillable(Brand::COLUMNS);
    }

    /**
     * The colours ready to paint with, Good on Paper's own standing in for
     * any left unset.
     */
    public function brand(): Brand
    {
        return Brand::from($this->only(Brand::COLUMNS));
    }
}
