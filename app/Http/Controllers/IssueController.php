<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Stockist;
use Illuminate\Http\Request;
use Inertia\Inertia;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Issue $issue)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Issue $issue)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Issue $issue)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Issue $issue)
    {
        //
    }

    public function stockists()
    {
        $stockists = Stockist::OrderBy('name')->get();

        return Inertia::render('Magazine/Stockists', [
            'title' => 'Stockists',
            'stockists' => $stockists,
        ]);
    }

    public function archives($year = null)
    {
        $year = $year ?? date('Y');

        if ($year == date('Y')) {
            $issues = Issue::orderBy('issue_number', 'desc')
                ->where('month_year', 'like', "%$year%")
                ->get()
                ->slice(1);
        }

        if ($year !== date('Y')) {
            $issues = Issue::orderBy('issue_number', 'desc')->where('month_year', 'like', "%$year")->get();
        }

        // A cover is only a download link when there is a file behind it.
        $issues->each(fn (Issue $issue) => $issue->append('downloadable'));

        return Inertia::render('Magazine/Archives', [
            'title' => 'Archives',
            'year' => $year,
            'issues' => $issues,
        ]);
    }
}
