<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    {{--
        Yaka kartı: A4'e 2 × 4 kart (90 × 64 mm). Kesik çizgiler kesme yeridir.
        dompdf flexbox desteklemez; düzen tablolarla kurulur.
    --}}
    <style>
        @page { margin: 10mm 12mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #111; margin: 0; }
        table.sheet { border-collapse: collapse; margin: 0 auto; }
        table.sheet > tbody > tr > td { width: 90mm; height: 64mm; padding: 0; border: 0.4mm dashed #999; vertical-align: top; }
        .card { height: 64mm; position: relative; }
        .top { background: #1f3d33; color: #fff; padding: 2mm 3mm; height: 9mm; }
        .top table { width: 100%; border-collapse: collapse; }
        .top td { vertical-align: middle; color: #fff; }
        .logo { max-height: 8mm; max-width: 20mm; background: #fff; padding: 0.5mm; }
        .agency { font-size: 8.5pt; font-weight: bold; }
        .agency-phone { font-size: 7pt; }
        .tour { font-size: 6.5pt; text-align: right; }
        .body { padding: 2.5mm 3mm 0 3mm; }
        .body table { border-collapse: collapse; width: 100%; }
        .photo { width: 22mm; height: 28mm; border: 0.3mm solid #ccc; text-align: center; vertical-align: middle; }
        .photo img { width: 22mm; height: 28mm; }
        .initials { font-size: 18pt; color: #888; }
        .info { padding-left: 3mm; vertical-align: top; }
        .name { font-size: 11pt; font-weight: bold; line-height: 1.15; }
        .group { font-size: 8pt; color: #1f3d33; font-weight: bold; margin-top: 1mm; }
        .line { font-size: 7.2pt; margin-top: 0.8mm; line-height: 1.2; }
        .label { color: #666; }
        .bottom { position: absolute; bottom: 0; left: 0; right: 0; border-top: 0.3mm solid #1f3d33; padding: 1.2mm 3mm; font-size: 7pt; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @if (count($badges) === 0)
        <p style="text-align: center; color: #666; margin-top: 40mm;">Yaka kartı basılacak yolcu yok.</p>
    @endif

    @foreach (array_chunk($badges, 8) as $pageIndex => $page)
        <table class="sheet">
            <tbody>
                @foreach (array_chunk($page, 2) as $row)
                    <tr>
                        @foreach ($row as $badge)
                            <td>
                                <div class="card">
                                    <div class="top">
                                        <table>
                                            <tr>
                                                @if (! empty($logo))
                                                    <td style="width: 22mm;"><img class="logo" src="{{ $logo }}" alt=""></td>
                                                @endif
                                                <td>
                                                    <div class="agency">{{ $tenant?->name }}</div>
                                                    @if ($tenant?->phone)<div class="agency-phone">{{ $tenant->phone }}</div>@endif
                                                </td>
                                                <td class="tour">{{ $tourName }}<br>{{ $tourDates }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="body">
                                        <table>
                                            <tr>
                                                <td class="photo">
                                                    @if ($badge['photo'])
                                                        <img src="{{ $badge['photo'] }}" alt="">
                                                    @else
                                                        <span class="initials">{{ $badge['initials'] }}</span>
                                                    @endif
                                                </td>
                                                <td class="info">
                                                    <div class="name">{{ $badge['name'] }}</div>
                                                    @if ($badge['group'])<div class="group">{{ $badge['group'] }}</div>@endif
                                                    @if ($badge['guide'])
                                                        <div class="line"><span class="label">Rehber:</span> {{ $badge['guide'] }}</div>
                                                    @endif
                                                    @foreach ($badge['lines'] as $line)
                                                        <div class="line"><span class="label">{{ $line['label'] }}:</span> {{ $line['value'] }}</div>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="bottom">
                                        Kaybolursanız / acil durumda: <strong>{{ $emergencyPhone ?? '—' }}</strong>
                                    </div>
                                </div>
                            </td>
                        @endforeach
                        @if (count($row) === 1)
                            <td style="border: 0;"></td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
