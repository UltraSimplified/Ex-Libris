<?php

namespace App\Http\Controllers;

use App\Models\Feature;
use App\Models\FeatureRead;
use App\Support\FeatureStatus;
use App\Support\Reader;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Takes the reading time a feature page reports as its reader leaves it.
 * Sent by navigator.sendBeacon, which never reads the reply, so anything
 * not worth counting is passed over quietly rather than refused.
 */
class FeatureReadController extends Controller
{
    public function __invoke(Request $request, Feature $feature): Response
    {
        abort_unless($feature->status === FeatureStatus::Published, 404);

        $report = $request->validate([
            'seconds' => ['required', 'integer', 'min:0', 'max:'.FeatureRead::LONGEST_REPORT],
            'newRead' => ['required', 'boolean'],
            'reachedEnd' => ['required', 'boolean'],
        ]);

        if (Reader::counts($request)) {
            FeatureRead::record($feature, (int) $report['seconds'], (bool) $report['newRead'], (bool) $report['reachedEnd']);
        }

        return response()->noContent();
    }
}
