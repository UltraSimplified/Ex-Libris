<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    /**
     * Hand over an issue's file, under a name that says which issue it is.
     *
     * The archive and the writers' pages only link the issues that have a
     * file, but an old link or a guessed address still comes here, so an
     * issue with no file, or one whose file has gone from the disk, is a
     * plain not-found rather than an error.
     */
    public function download(int $id): StreamedResponse
    {
        $issue = Issue::findOrFail($id);

        abort_unless($issue->downloadable && Storage::disk('do')->exists($issue->pdf), 404);

        return Storage::disk('do')->download($issue->pdf, $issue->downloadName());
    }
}
