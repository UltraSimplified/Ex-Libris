<?php

namespace App\Http\Controllers;

use App\Services\Listings\ListingCsvExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the listings CSV requested by the Nova export action.
 *
 * Reached through a short-lived signed URL, so the range cannot be edited
 * in the address bar and the link stops working shortly after it is issued.
 * Nothing is written to disk.
 */
class ListingExportController extends Controller
{
    public function __invoke(Request $request, ListingCsvExport $export): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $filename = $export->filename($validated['from'], $validated['to']);

        return response()->streamDownload(
            function () use ($export, $validated): void {
                $handle = fopen('php://output', 'wb');
                $export->writeTo($handle, $validated['from'], $validated['to']);
                fclose($handle);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}
