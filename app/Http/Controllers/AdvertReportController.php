<?php

namespace App\Http\Controllers;

use App\Models\Advert;
use App\Services\Adverts\AdvertReport;
use Illuminate\Http\Response;

class AdvertReportController extends Controller
{
    /**
     * An advert's figures across its whole run, as a Good on Paper PDF.
     *
     * Reached only by the signed link the Office issues, on its Adverts card
     * and on each advert's page, as the listings export is.
     */
    public function __invoke(Advert $advert, AdvertReport $report): Response
    {
        return $report->pdf($advert)->download($report->filename($advert));
    }
}
