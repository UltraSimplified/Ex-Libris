{{-- Impressions and clicks split one way, for the advert report. --}}
@if (empty($rows))
    <p class="empty">Nothing recorded yet.</p>
@else
    <table class="data">
        <thead>
            <tr>
                <th>{{ $heading }}</th>
                <th class="num">Impressions</th>
                <th class="num">Share</th>
                <th class="num">Clicks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="num">{{ number_format($row['impressions']) }}</td>
                    <td class="num">{{ number_format($row['share'], 1) }}%</td>
                    <td class="num">{{ number_format($row['clicks']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
