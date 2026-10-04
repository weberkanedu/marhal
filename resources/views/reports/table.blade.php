<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $report->title }}</title>
    <style>
        @page { margin: 28px 28px 40px 28px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #111; }
        .header { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .header td { vertical-align: top; }
        .agency { font-size: 11px; font-weight: bold; }
        .muted { color: #666; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #eee; text-align: left; padding: 4px 5px; border-bottom: 1px solid #999; font-size: 8.5px; }
        table.data td { padding: 3px 5px; border-bottom: 1px solid #ddd; }
        table.data tr:nth-child(even) td { background: #fafafa; }
        .num { text-align: right; white-space: nowrap; }
        tr.total td { font-weight: bold; border-top: 1.5px solid #111; border-bottom: none; background: #fff !important; }
        .empty { padding: 20px; text-align: center; color: #666; }
        .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7.5px; color: #888; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <td>
                <h1>{{ $report->title }}</h1>
                @foreach ($report->subtitle as $line)
                    <div class="muted">{{ $line }}</div>
                @endforeach
            </td>
            <td style="text-align: right;">
                @if (! empty($logo))
                    <img src="{{ $logo }}" alt="" style="max-height: 42px; max-width: 160px; margin-bottom: 4px;"><br>
                @endif
                @if ($tenant)
                    <div class="agency">{{ $tenant->name }}</div>
                    @if ($tenant->phone)<div class="muted">{{ $tenant->phone }}</div>@endif
                    @if ($tenant->email)<div class="muted">{{ $tenant->email }}</div>@endif
                @endif
            </td>
        </tr>
    </table>

    @if (count($report->rows) === 0)
        <div class="empty">Bu raporda kayıt yok.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 18px;">#</th>
                    @foreach ($report->columns as $column)
                        <th class="{{ $column->isNumeric() ? 'num' : '' }}"
                            @if ($column->pdfWidth) style="width: {{ $column->pdfWidth }}%;" @endif>
                            {{ $column->label }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($report->rows as $i => $row)
                    <tr>
                        <td class="muted">{{ $i + 1 }}</td>
                        @foreach ($report->columns as $column)
                            <td class="{{ $column->isNumeric() ? 'num' : '' }}">{{ $formatValue($column, $row[$column->key] ?? null) }}</td>
                        @endforeach
                    </tr>
                @endforeach
                @foreach ($report->totals as $total)
                    <tr class="total">
                        <td></td>
                        @foreach ($report->columns as $column)
                            <td class="{{ $column->isNumeric() ? 'num' : '' }}">{{ $formatValue($column, $total[$column->key] ?? null) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Sayfa numarası CSS sayacı ile (dompdf'te PHP çalıştırma kapalı tutulur). --}}
    <div class="footer">
        {{ implode(' · ', $header) }} · Marhal · Sayfa <span class="pagenum"></span>
    </div>
</body>
</html>
