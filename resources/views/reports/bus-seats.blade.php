<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 28px 34px 28px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #111; }
        .header { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .header td { vertical-align: top; }
        h1 { font-size: 15px; margin: 0 0 2px 0; }
        .muted { color: #666; }
        .agency { font-size: 11px; font-weight: bold; }
        .front { text-align: center; font-weight: bold; border: 1px solid #999; border-radius: 4px; padding: 3px; margin: 0 auto 6px auto; width: 60%; }
        table.bus { margin: 0 auto; border-collapse: separate; border-spacing: 4px; }
        table.bus td { width: 100px; height: 34px; vertical-align: top; padding: 2px 4px; }
        td.seat { border: 1px solid #555; border-radius: 4px; }
        td.reserved { border: 1px dashed #555; background: #eee; }
        td.aisle { width: 18px; }
        td.door { width: 100px; text-align: center; color: #888; vertical-align: middle; }
        .no { font-weight: bold; font-size: 8px; color: #555; }
        .name { font-size: 8.5px; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7.5px; color: #888; }
    </style>
</head>
<body>
    <table class="header" width="100%">
        <tr>
            <td>
                <h1>{{ $title }}</h1>
                @foreach ($subtitle as $line)
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
                @endif
            </td>
        </tr>
    </table>

    <div class="front">ÖN — Şoför</div>

    <table class="bus">
        @foreach ($grid as $cells)
            <tr>
                @foreach ($cells as $cell)
                    @if (is_int($cell))
                        @php($occupant = $names[$cell] ?? null)
                        <td class="seat {{ in_array($cell, $reserved, true) ? 'reserved' : '' }}">
                            <div class="no">{{ $cell }}</div>
                            <div class="name">{{ $occupant ?? (in_array($cell, $reserved, true) ? 'Rehber / görevli' : '') }}</div>
                        </td>
                    @elseif ($cell === 'aisle')
                        <td class="aisle"></td>
                    @elseif ($cell === 'door')
                        <td class="door">kapı</td>
                    @elseif ($cell === 'driver')
                        <td class="door">şoför</td>
                    @else
                        <td></td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </table>

    <div class="footer">
        {{ $tenant?->name }} · Oluşturma: {{ $generatedAt->format('d.m.Y H:i') }} · Marhal
    </div>
</body>
</html>
