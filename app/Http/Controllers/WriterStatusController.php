<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Makes a writer active or an alumnus from the tickbox on their page in
 * the Office, without opening the edit form.
 */
class WriterStatusController extends Controller
{
    public function __invoke(Request $request, User $user): JsonResponse
    {
        abort_unless(Gate::allows('viewNova'), 403);
        abort_unless($user->is_writer, 404);

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $user->is_active_writer = $validated['active'];
        $user->save();

        return response()->json(['active' => $user->is_active_writer]);
    }
}
