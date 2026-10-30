{{--
    The advert report, rendered to PDF by dompdf. dompdf lays out tables
    and blocks but not flexbox or grid, so the layout is tables throughout.
    Type is Helvetica, the PDF core face that Neue Haas Grotesk revives, so
    nothing needs embedding; the colours are the Office's light mode, slate
    greys with Nova's sky blue for accents.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Advert report: {{ $advert['title'] }}</title>
    <style>
        @page {
            margin: 16mm 16mm 20mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.35;
            color: #1e293b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td, th {
            vertical-align: top;
        }

        /* The rule is drawn under the whole masthead, not under each of its
           cells, which dompdf would draw with a seam between them. */
        .masthead-rule {
            border-bottom: 2.5pt solid #0f172a;
            padding-bottom: 12pt;
        }

        .masthead td {
            padding: 0;
            vertical-align: bottom;
        }

        .lockup {
            font-size: 21pt;
            line-height: 0.92;
            font-weight: bold;
            letter-spacing: -0.5pt;
            color: #0f172a;
        }

        .masthead-label {
            text-align: right;
            font-size: 8pt;
            letter-spacing: 2pt;
            text-transform: uppercase;
            color: #64748b;
        }

        .masthead-label strong {
            display: block;
            font-size: 15pt;
            letter-spacing: 0;
            text-transform: none;
            color: #0f172a;
            margin-bottom: 3pt;
        }

        h1 {
            font-size: 22pt;
            line-height: 1.1;
            margin: 16pt 0 4pt;
            letter-spacing: -0.4pt;
        }

        h2 {
            font-size: 8pt;
            letter-spacing: 2pt;
            text-transform: uppercase;
            color: #334155;
            border-bottom: 1.5pt solid #1e293b;
            padding-bottom: 3pt;
            margin: 18pt 0 6pt;
        }

        .meta {
            color: #64748b;
            margin: 0;
        }

        .run {
            margin: 6pt 0 0;
            font-weight: bold;
        }

        .running {
            display: inline-block;
            background: #0ea5e9;
            color: #ffffff;
            font-size: 7.5pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            padding: 1pt 5pt;
            margin-left: 4pt;
        }

        /* The square mobile creative, as wide as keeps it level with the figures. */
        .creative {
            width: 40%;
            padding: 14pt 14pt 0 0;
        }

        .creative img {
            width: 100%;
            border: 0.75pt solid #e2e8f0;
        }

        .no-creative {
            border: 0.75pt dashed #cbd5e1;
            color: #94a3b8;
            padding: 30pt 10pt;
            text-align: center;
        }

        .figures {
            padding-top: 14pt;
        }

        .figure {
            border-left: 3pt solid #0ea5e9;
            padding: 2pt 0 2pt 9pt;
            margin-bottom: 10pt;
        }

        .figure .value {
            font-size: 22pt;
            font-weight: bold;
            line-height: 1.05;
            color: #0f172a;
        }

        .figure .label {
            font-size: 7.5pt;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
            color: #64748b;
        }

        .small-figures td {
            width: 50%;
            font-size: 8.5pt;
            color: #334155;
        }

        .small-figures strong {
            display: block;
            font-size: 12pt;
            color: #0f172a;
        }

        .data th {
            text-align: left;
            font-size: 7.5pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            color: #64748b;
            font-weight: normal;
            padding: 3pt 4pt;
            border-bottom: 0.75pt solid #cbd5e1;
        }

        .data td {
            padding: 3pt 4pt;
            border-bottom: 0.5pt solid #e2e8f0;
        }

        .data .num {
            text-align: right;
            white-space: nowrap;
        }

        .data .bar-cell {
            width: 42%;
            padding-top: 5pt;
        }

        .bar {
            height: 7pt;
            background: #0ea5e9;
        }

        .bar-empty {
            height: 7pt;
            background: #f1f5f9;
        }

        .page-path {
            font-size: 8.5pt;
            word-wrap: break-word;
        }

        .columns td.column {
            width: 50%;
        }

        .columns td.column:first-child {
            padding-right: 10pt;
        }

        .columns td.column:last-child {
            padding-left: 10pt;
        }

        .empty {
            color: #94a3b8;
            padding: 4pt 0;
        }

        .notes {
            margin-top: 18pt;
            padding: 8pt 10pt;
            background: #f1f5f9;
            font-size: 8pt;
            color: #334155;
        }

        .keep {
            page-break-inside: avoid;
        }

        .footer {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 7.5pt;
            color: #64748b;
            border-top: 0.75pt solid #cbd5e1;
            padding-top: 4pt;
        }

        .data td.period {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>Good on Paper &middot; goodonpaper.info &middot; Advert report for {{ $advert['title'] }}</td>
            </tr>
        </table>
    </div>

    <div class="masthead-rule">
    <table class="masthead">
        <tr>
            <td>
                <div class="lockup">GOOD<br>ON<br>PAPER</div>
            </td>
            <td class="masthead-label">
                <strong>Advert report</strong>
                Generated {{ $generatedAt->format('j F Y') }}
            </td>
        </tr>
    </table>
    </div>

    <h1>{{ $advert['title'] }}</h1>
    <p class="meta">
        {{ collect([
            filled($advert['campaign']) ? '“'.$advert['campaign'].'”' : null,
            $advert['owner'],
            $advert['slot'],
        ])->filter()->implode(' · ') }}
    </p>
    <p class="run">
        @if ($run['started'])
            {{ $run['from']->format('j M Y') }} &ndash; {{ $run['to']->format('j M Y') }}
            &middot; {{ $run['days'] }} {{ \Illuminate\Support\Str::plural('day', $run['days']) }}{{ $run['running'] ? ' so far' : '' }}
            @if ($run['running'])
                <span class="running">Running</span>
            @endif
        @else
            Starts {{ $run['from']->format('j M Y') }}. Nothing has run yet.
        @endif
    </p>

    <table>
        <tr>
            <td class="creative">
                @if ($advert['creative'])
                    <img src="{{ $advert['creative'] }}" alt="{{ $advert['title'] }}">
                @else
                    <div class="no-creative">No creative on file</div>
                @endif
            </td>
            <td class="figures">
                <div class="figure">
                    <div class="value">{{ number_format($totals['impressions']) }}</div>
                    <div class="label">Impressions</div>
                </div>
                <div class="figure">
                    <div class="value">{{ number_format($totals['clicks']) }}</div>
                    <div class="label">{{ \Illuminate\Support\Str::plural('Click', $totals['clicks']) }} through</div>
                </div>
                <div class="figure">
                    <div class="value">{{ number_format($totals['rate'], 2) }}%</div>
                    <div class="label">Click-through rate</div>
                </div>
                <table class="small-figures">
                    <tr>
                        <td>
                            <strong>{{ number_format($totals['perDay'], 1) }}</strong>
                            impressions a day
                        </td>
                        <td>
                            @if ($totals['best'] && $totals['best']['impressions'] > 0)
                                <strong>{{ $totals['best']['label'] }}</strong>
                                busiest {{ $series['period'] }}, {{ number_format($totals['best']['impressions']) }} impressions
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Free to run on to the next page: a long run is a long table. --}}
    <div>
        <h2>Impressions {{ $series['period'] === 'week' ? 'week by week' : 'day by day' }}</h2>
        @if (empty($series['rows']))
            <p class="empty">No days to show yet.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>{{ $series['period'] === 'week' ? 'Week' : 'Day' }}</th>
                        <th></th>
                        <th class="num">Impressions</th>
                        <th class="num">Clicks</th>
                        <th class="num">Click-through</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($series['rows'] as $row)
                        <tr>
                            <td class="period">{{ $row['label'] }}</td>
                            <td class="bar-cell">
                                @if ($row['bar'] > 0)
                                    <div class="bar" style="width: {{ $row['bar'] }}%;"></div>
                                @else
                                    <div class="bar-empty" style="width: 1.5%;"></div>
                                @endif
                            </td>
                            <td class="num">{{ number_format($row['impressions']) }}</td>
                            <td class="num">{{ number_format($row['clicks']) }}</td>
                            <td class="num">{{ number_format($row['rate'], 2) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <table class="columns keep">
        <tr>
            <td class="column">
                <h2>By device</h2>
                @include('reports.partials.advert-split', ['rows' => $devices, 'heading' => 'Device'])
            </td>
            <td class="column">
                <h2>By place</h2>
                @include('reports.partials.advert-split', ['rows' => $places, 'heading' => 'Place'])
            </td>
        </tr>
        <tr>
            <td class="column">
                <h2>By placement</h2>
                @include('reports.partials.advert-split', ['rows' => $placements, 'heading' => 'Placement'])
            </td>
            <td class="column"></td>
        </tr>
    </table>

    <div class="keep">
        <h2>Pages it was seen on most</h2>
        @if (empty($pages))
            <p class="empty">No pages recorded yet.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th class="num">Impressions</th>
                        <th class="num">Share</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pages as $page)
                        <tr>
                            <td class="page-path">{{ \Illuminate\Support\Str::limit($page['page'], 90) }}</td>
                            <td class="num">{{ number_format($page['impressions']) }}</td>
                            <td class="num">{{ number_format($page['share'], 1) }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="notes keep">
        <strong>How these are counted.</strong>
        An impression is counted once per page view, when the advert scrolls into view.
        A click is counted when someone follows the advert through to
        {{ $advert['link'] ? parse_url($advert['link'], PHP_URL_HOST) ?: 'its link' : 'its link' }}.
        Places come from the reader's connection and are approximate.
        @if ($run['started'])
            These figures cover {{ $run['from']->format('j M Y') }} to {{ $run['until']->format('j M Y') }}.
        @endif
    </div>
</body>
</html>
