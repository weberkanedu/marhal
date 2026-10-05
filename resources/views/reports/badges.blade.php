<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    {{--
        Yaka kartı: boy acentenin ayarından (dikey A6 2×2, yatay 9×6,4 2×4, plastik 8,6×5,4 2×5). Kesik çizgiler kesme yeri.
        Arka yüz açıksa her ön sayfanın ardından arka sayfa gelir; sütunlar ters dizilir (uzun kenardan çevirerek çift taraflı
        baskıda kartın arkası doğru yere düşer). dompdf flexbox desteklemez; düzen tablolarla kurulur.
        Arapça satır App\Support\ArabicText ile hazırlanır (harfler birleşik, sağdan sola).
    --}}
    @php
        $g = $size->geometry();
        $w = $g['width'];
        $h = $g['height'];
        $big = $size === \App\Enums\BadgeSize::Portrait;
        $small = $size === \App\Enums\BadgeSize::Card;
        // Yazı ölçeği: dikey kartta büyük, plastikte küçük.
        $k = $big ? 1.35 : ($small ? 0.85 : 1);
        $pt = fn (float $v) => round($v * $k, 1).'pt';
        $mm = fn (float $v) => round($v * $k, 1).'mm';
        $lost = [
            'tr' => ['KAYBOLURSANIZ', 'Lütfen bu numarayı arayın'],
            'en' => ['IF LOST', 'Please call this number'],
            'ar' => [\App\Support\ArabicText::forPdf('إذا ضللت الطريق'), \App\Support\ArabicText::forPdf('يرجى الاتصال بهذا الرقم')],
        ];
    @endphp
    <style>
        @page { margin: 6mm 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #111; margin: 0; }
        table.sheet { border-collapse: collapse; margin: 0 auto; }
        table.sheet > tbody > tr > td.cell { width: {{ $w }}mm; height: {{ $h }}mm; padding: 0; border: 0.3mm dashed #aaa; vertical-align: top; overflow: hidden; }
        .card { height: {{ $h }}mm; position: relative; overflow: hidden; }
        .band { color: #fff; padding: {{ $mm(1.6) }} {{ $mm(2.6) }}; }
        .band table { width: 100%; border-collapse: collapse; }
        .band td { color: #fff; vertical-align: middle; }
        .logo { max-height: {{ $mm(7) }}; max-width: {{ $mm(18) }}; background: #fff; padding: 0.4mm; border-radius: 1mm; }
        .agency { font-size: {{ $pt(7.6) }}; font-weight: bold; }
        .group { font-size: {{ $pt(6.6) }}; text-transform: uppercase; letter-spacing: 0.4pt; opacity: 0.95; }
        .tour { font-size: {{ $pt(6) }}; text-align: right; }
        .body { padding: {{ $mm(2) }} {{ $mm(2.6) }} 0; }
        .body table { border-collapse: collapse; width: 100%; }
        .photo { width: {{ $mm(18) }}; height: {{ $mm(23) }}; border: 0.3mm solid #ddd; text-align: center; vertical-align: middle; background: #f4f4f4; }
        .photo img { width: {{ $mm(18) }}; height: {{ $mm(23) }}; }
        .initials { font-size: {{ $pt(15) }}; color: #999; }
        .info { padding-left: {{ $mm(2.4) }}; vertical-align: top; }
        .first { font-size: {{ $pt(12.5) }}; font-weight: bold; line-height: 1.05; }
        .last { font-size: {{ $pt(10) }}; font-weight: bold; line-height: 1.1; }
        .line { font-size: {{ $pt(6.4) }}; margin-top: {{ $mm(0.6) }}; line-height: 1.2; }
        .label { color: #666; }
        .qr { width: {{ $mm(13) }}; height: {{ $mm(13) }}; }
        .foot { position: absolute; bottom: 0; left: 0; right: 0; padding: {{ $mm(1) }} {{ $mm(2.6) }}; font-size: {{ $pt(5.8) }}; color: #555; }
        .foot table { width: 100%; border-collapse: collapse; }
        .back { padding: {{ $mm(2.4) }} {{ $mm(3) }}; text-align: center; }
        .lost { font-size: {{ $pt(9) }}; font-weight: bold; letter-spacing: 0.6pt; margin-top: {{ $mm(0.8) }}; }
        .lost-sub { font-size: {{ $pt(6) }}; color: #444; }
        .lost-line { font-size: {{ $pt(6.4) }}; line-height: 1.35; }
        .lost-line b { letter-spacing: 0.4pt; }
        .phone { font-size: {{ $pt($big ? 14 : 12) }}; font-weight: bold; margin: {{ $mm($big ? 1.6 : 0.8) }} 0; }
        .nowrap { white-space: nowrap; }
        .addr { font-size: {{ $pt(5.8) }}; text-align: left; margin-top: {{ $mm(0.6) }}; line-height: 1.2; }
        .health { margin-top: {{ $mm(1.2) }}; border: 0.3mm solid #c63d33; color: #8a1f17; font-size: {{ $pt(6) }}; padding: {{ $mm(0.8) }}; text-align: left; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @if (count($badges) === 0)
        <p style="text-align: center; color: #666; margin-top: 40mm;">Yaka kartı basılacak yolcu yok.</p>
    @endif

    @foreach (array_chunk($badges, $size->perPage()) as $page)
        @foreach (($settings->back_side ? ['front', 'back'] : ['front']) as $side)
            <table class="sheet">
                <tbody>
                    @foreach (array_chunk($page, $g['columns']) as $row)
                        @php
                            $cells = array_pad($row, $g['columns'], null);
                            // Arka yüzde sütunlar ters: çift taraflı baskıda kartın arkası aynı yere gelir.
                            if ($side === 'back') { $cells = array_reverse($cells); }
                        @endphp
                        <tr>
                            @foreach ($cells as $badge)
                                @if ($badge === null)
                                    <td class="cell" style="border: 0;"></td>
                                    @continue
                                @endif
                                <td class="cell">
                                    <div class="card">
                                        <div class="band" style="background: {{ $badge['color'] }};">
                                            <table>
                                                <tr>
                                                    @if (! empty($logo))
                                                        <td style="width: {{ $mm(19) }};"><img class="logo" src="{{ $logo }}" alt=""></td>
                                                    @endif
                                                    <td>
                                                        <div class="agency">{{ $tenant?->name }}</div>
                                                        @if ($badge['group'])<div class="group">{{ $badge['group'] }}</div>@endif
                                                    </td>
                                                    <td class="tour">{{ $tourName }}<br>{{ $tourDates }}</td>
                                                </tr>
                                            </table>
                                        </div>

                                        @if ($side === 'front')
                                            <div class="body">
                                                <table>
                                                    <tr>
                                                        @if ($settings->shows('photo'))
                                                            <td class="photo">
                                                                @if ($badge['photo'])
                                                                    <img src="{{ $badge['photo'] }}" alt="">
                                                                @else
                                                                    <span class="initials">{{ $badge['initials'] }}</span>
                                                                @endif
                                                            </td>
                                                        @endif
                                                        <td class="info">
                                                            <div class="first">{{ $badge['first_name'] }}</div>
                                                            <div class="last">{{ $badge['last_name'] }}</div>
                                                            @if ($settings->shows('guide') && $badge['guide'])
                                                                <div class="line"><span class="label">Rehber:</span> {{ $badge['guide'] }}</div>
                                                            @endif
                                                            @foreach ($badge['hotels'] as $hotel)
                                                                <div class="line"><span class="label">{{ $hotel['city'] }}:</span> {{ $hotel['hotel'] }}@if ($hotel['room']) · <strong class="nowrap">Oda {{ $hotel['room'] }}</strong>@endif</div>
                                                            @endforeach
                                                            @if ($badge['bus'])
                                                                <div class="line"><span class="label">Otobüs:</span> {{ $badge['bus']['label'] }} · <strong>Koltuk {{ $badge['bus']['value'] }}</strong></div>
                                                            @endif
                                                        </td>
                                                        @if ($badge['qr'] && ! $small)
                                                            <td style="width: {{ $mm(13) }}; vertical-align: top;"><img class="qr" src="{{ $badge['qr'] }}" alt=""></td>
                                                        @endif
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="foot" style="border-top: 0.4mm solid {{ $badge['color'] }};">
                                                <table>
                                                    <tr>
                                                        <td>Acil: <strong>{{ $emergencyPhone ?? '—' }}</strong></td>
                                                        <td style="text-align: right;">No {{ $badge['serial'] }}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @else
                                            <div class="back">
                                                @foreach ($settings->back_languages as $lang)
                                                    @continue(! isset($lost[$lang]))
                                                    @if ($big)
                                                        <div class="lost">{{ $lost[$lang][0] }}</div>
                                                        <div class="lost-sub">{{ $lost[$lang][1] }}</div>
                                                    @elseif ($lang === 'ar')
                                                        {{-- Sağdan sola: başlık sağda kalsın diye önce açıklama yazılır. --}}
                                                        <div class="lost-line">{{ $lost[$lang][1] }} · <b>{{ $lost[$lang][0] }}</b></div>
                                                    @else
                                                        <div class="lost-line"><b>{{ $lost[$lang][0] }}</b> · {{ $lost[$lang][1] }}</div>
                                                    @endif
                                                @endforeach
                                                <div class="phone">{{ $emergencyPhone ?? '—' }}</div>
                                                @foreach ($badge['hotels'] as $hotel)
                                                    <div class="addr"><strong>{{ $hotel['city'] }}:</strong> {{ $hotel['hotel'] }}@if ($hotel['room']) · <span class="nowrap">Oda {{ $hotel['room'] }}</span>@endif @if ($big && $hotel['address'])<br>{{ $hotel['address'] }}@endif</div>
                                                @endforeach
                                                @if ($badge['health'])
                                                    <div class="health"><strong>Sağlık / Health:</strong> {{ $badge['health'] }}</div>
                                                @endif
                                            </div>
                                            <div class="foot" style="border-top: 0.4mm solid {{ $badge['color'] }};">
                                                <table>
                                                    <tr>
                                                        <td>{{ $badge['name'] }}</td>
                                                        <td style="text-align: right;">No {{ $badge['serial'] }}</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if (! ($loop->parent->last && $loop->last))
                <div class="page-break"></div>
            @endif
        @endforeach
    @endforeach
</body>
</html>
