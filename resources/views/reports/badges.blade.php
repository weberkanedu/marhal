<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    {{--
        Yaka kartı — tasarım sayfasındaki kartın (.bdg) birebir PDF hâli. Tasarımdaki piksel ölçüleri kartın gerçek
        boyuna oranlanır ($px: mm, $pt: yazı). Boy acentenin ayarından; A4'e kesik çizgili dizilir.
        Arka yüz açıksa her ön sayfanın ardından arka sayfa gelir; sütunlar ters (uzun kenardan çevirince doğru karta denk gelir).
        dompdf flexbox / grid / gölge desteklemez: düzen tablolarla, halka iki iç içe daireyle çizilir.
        Arapça satır App\Support\ArabicText ile hazırlanır (harfler birleşik, sağdan sola).
    --}}
    @php
        $g = $size->geometry();
        $w = $g['width'];
        $h = $g['height'];
        $f = $w / $size->designWidth();
        $px = fn (float $v) => round($v * $f, 2).'mm';
        $pt = fn (float $v) => round($v * $f * 2.8346, 2).'pt';
        $dikey = $size === \App\Enums\BadgeSize::Portrait;
        $kart = $size === \App\Enums\BadgeSize::Card;
        $yatay = ! $dikey && ! $kart;
        // Alt şerit: grup renginin %12'si beyazla karışık (tasarımdaki color-mix).
        $tint = function (string $hex): string {
            [$r, $gg, $b] = sscanf($hex, '#%02x%02x%02x');
            $mix = fn ($c) => (int) round($c * 0.12 + 255 * 0.88);
            return sprintf('#%02x%02x%02x', $mix($r), $mix($gg), $mix($b));
        };
        $langs = $settings->back_languages;
        $lostTitle = implode(' · ', array_filter([in_array('tr', $langs, true) ? 'KAYBOLURSANIZ' : null, in_array('en', $langs, true) ? 'IF LOST' : null])) ?: 'KAYBOLURSANIZ';
        $lostSub = implode(' · ', array_filter([in_array('tr', $langs, true) ? 'Bulursanız lütfen arayın' : null, in_array('en', $langs, true) ? 'If found, please call' : null]));
        // Dikeyde tasarımdaki uzun cümle (iki satır); küçük kartlarda tek satırlık kısası.
        $arabic = ! in_array('ar', $langs, true) ? null : ($dikey
            ? [\App\Support\ArabicText::forPdf('إذا وجدت هذا المعتمر تائها،'), \App\Support\ArabicText::forPdf('يرجى الاتصال بالرقم التالي')]
            : [\App\Support\ArabicText::forPdf('إذا ضل الطريق، يرجى الاتصال')]);
        // "1. Otobüs" → "1" (tasarımdaki "1 · koltuk 7").
        $busShort = fn (string $label) => preg_match('/^(\d+)/', $label, $m) ? $m[1] : $label;
        // Kartta otelin kısa adı (tasarımdaki "Ajyad · 1201" gibi): ilk kelime.
        $short = fn (string $hotel) => explode(' ', trim($hotel))[0];
        // dompdf'in yazı tipi (DejaVu) tasarımdakinden geniş; dikey kartta foto ve QR bir tık küçük ki 4 satır sığsın.
        $photo = $dikey ? 58 : ($kart ? 46 : 52);
        $qr = $dikey ? 24 : ($kart ? 14 : 20);
        $agencyInitial = mb_substr((string) ($tenant?->name ?? 'M'), 0, 1);
    @endphp
    <style>
        @page { margin: 5mm 0; }
        body { font-family: "DejaVu Sans", sans-serif; color: #15211b; margin: 0; }
        table { border-collapse: collapse; }
        table.sheet { margin: 0 auto; }
        td.cell { width: {{ $w }}mm; height: {{ $h }}mm; padding: 0; border: 0.25mm dashed #9aa59f; vertical-align: top; }
        .bdg { position: relative; width: {{ $w }}mm; height: {{ $h }}mm; overflow: hidden; background: #fff; }
        .hole { position: absolute; top: {{ $px(7) }}; left: {{ round($w / 2 - 17 * $f, 2) }}mm; width: {{ $px(34) }}; height: {{ $px(7) }}; border-radius: {{ $px(4) }}; background: #d6d6d6; }
        .band { color: #fff; padding: {{ $kart ? $px(7).' '.$px(10) : ($yatay ? $px(12).' '.$px(10).' '.$px(6) : $px(18).' '.$px(12).' '.$px(28)) }}; }
        .band td { color: #fff; vertical-align: middle; }
        .lg { width: {{ $px($kart ? 20 : 26) }}; height: {{ $px($kart ? 20 : 26) }}; border-radius: {{ $px(7) }}; background: #fff; text-align: center; vertical-align: middle; font-family: "DejaVu Serif", serif; font-weight: bold; font-size: {{ $pt($kart ? 11 : 14) }}; }
        .lg img { max-width: {{ $px($kart ? 18 : 24) }}; max-height: {{ $px($kart ? 18 : 24) }}; margin-top: {{ $px(1) }}; }
        .agency { font-weight: bold; font-size: {{ $pt(12) }}; line-height: 1.1; }
        .tourname { font-size: {{ $pt(9) }}; color: rgba(255, 255, 255, 0.85); }
        .ring { border-radius: 50%; text-align: center; }
        .ph { border-radius: 50%; background: #dfe8e2; color: #2a3b33; text-align: center; font-family: "DejaVu Serif", serif; font-weight: bold; overflow: hidden; }
        .fn { font-weight: bold; line-height: 1; }
        .ln { font-weight: bold; color: #3d4b44; line-height: 1.15; }
        .grp { font-size: {{ $pt(10) }}; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4pt; margin-top: {{ $px(2) }}; }
        .dot { display: inline-block; width: {{ $px(9) }}; height: {{ $px(9) }}; border-radius: 50%; margin-right: {{ $px(5) }}; vertical-align: middle; }
        .dl td { font-size: {{ $pt($dikey ? 9.5 : 8.5) }}; line-height: 1.15; padding: {{ $px(1) }} {{ $px(8) }} {{ $px(1) }} 0; vertical-align: top; }
        .dl .dt { color: #6a776f; font-weight: bold; white-space: nowrap; }
        .dl .dd { font-weight: bold; }
        .dl.tight .dd { white-space: nowrap; }
        .foot { position: absolute; left: 0; right: 0; bottom: 0; padding: {{ $kart ? $px(2).' '.$px(10) : ($dikey ? $px(4).' '.$px(10) : $px(6).' '.$px(10)) }}; font-size: {{ $pt(8.5) }}; color: #3d4b44; }
        .foot table { width: 100%; }
        .back { padding: {{ $dikey ? $px(16).' '.$px(12).' 0' : ($kart ? $px(8).' '.$px(10) : $px(10).' '.$px(12)) }}; }
        .back h6 { margin: 0 0 {{ $px(5) }}; font-size: {{ $pt($dikey ? 12 : 11.5) }}; font-weight: bold; white-space: nowrap; }
        .back .ar { font-size: {{ $pt($kart ? 10 : ($yatay ? 11 : 12)) }}; text-align: right; margin-bottom: {{ $px(3) }}; white-space: nowrap; line-height: 1.35; }
        .back .sub { font-size: {{ $pt(9.5) }}; color: #4b5852; }
        .back .tel { font-size: {{ $pt($kart ? 14 : ($yatay ? 16 : 18)) }}; font-weight: bold; letter-spacing: 0.3pt; margin: {{ $px($dikey ? 4 : 2) }} 0; }
        .back .small { font-size: {{ $pt(9) }}; line-height: 1.35; }
        .back .med { margin-top: {{ $px($dikey ? 5 : 3) }}; padding: {{ $px($dikey ? 6 : 3) }} {{ $px(8) }}; border-radius: {{ $px(7) }}; background: #fff3e6; color: #8a4b0c; font-size: {{ $pt($dikey ? 9.5 : 8.5) }}; font-weight: bold; line-height: 1.3; }
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
                @foreach (array_chunk($page, $g['columns']) as $row)
                    @php
                        $cells = array_pad($row, $g['columns'], null);
                        if ($side === 'back') { $cells = array_reverse($cells); }
                    @endphp
                    <tr>
                        @foreach ($cells as $b)
                            @if ($b === null)
                                <td class="cell" style="border: 0;"></td>
                                @continue
                            @endif
                            @php
                                $c = $b['color'];
                                $rows = [];
                                if ($settings->shows('hotels')) { foreach ($b['hotels'] as $hotel) { $rows[] = [$hotel['city'], $short($hotel['hotel']).' · '.($hotel['room'] ?? '—')]; } }
                                if ($settings->shows('bus')) { $rows[] = ['Otobüs', $b['bus'] ? $busShort($b['bus']['label']).' · koltuk '.$b['bus']['value'] : '—']; }
                                if ($settings->shows('guide') && ! $kart && $b['guide_name']) {
                                    // Dikeyde ad + telefon (sığmazsa yalnız ad; telefon arka yüzde de var).
                                    $guide = implode(' · ', array_filter([$b['guide_name'], $b['guide_phone']]));
                                    $rows[] = ['Rehber', $dikey && mb_strlen($guide) <= 24 ? $guide : $b['guide_name']];
                                }
                            @endphp
                            <td class="cell">
                                <div class="bdg">
                                    @if (! $kart)<div class="hole"></div>@endif

                                    @if ($side === 'front')
                                        <div class="band" style="background: {{ $c }};">
                                            <table>
                                                <tr>
                                                    <td style="width: {{ $px($kart ? 20 : 26) }};">
                                                        <table><tr><td class="lg" style="color: {{ $c }};">@if (! empty($logo))<img src="{{ $logo }}" alt="">@else{{ $agencyInitial }}@endif</td></tr></table>
                                                    </td>
                                                    <td style="padding-left: {{ $px(8) }};">
                                                        <div class="agency">{{ $tenant?->name }}</div>
                                                        <div class="tourname">{{ $tourName }}</div>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        @php
                                            $phStyle = 'width: '.$px($photo).'; height: '.$px($photo).'; font-size: '.$pt($photo * 0.33).'; vertical-align: middle;'.($kart ? ' border-radius: '.$px(8).';' : '');
                                            $photoHtml = $settings->shows('photo')
                                                ? '<div class="ring" style="display: inline-block; padding: '.$px(2).'; background: '.$c.';'.($kart ? ' border-radius: '.$px(10).';' : '').'"><div style="padding: '.$px(3).'; background: #fff; border-radius: '.($kart ? $px(9) : '50%').';"><table><tr><td class="ph" style="'.$phStyle.'">'
                                                    .($b['photo'] ? '<img src="'.$b['photo'].'" alt="" style="width: '.$px($photo).'; height: '.$px($photo).';">' : e($b['initials']))
                                                    .'</td></tr></table></div></div>'
                                                : '';
                                            $names = '<div class="fn" style="font-size: '.$pt($dikey ? 25 : ($kart ? 17 : 22)).';">'.e($b['first_name']).'</div>'
                                                .'<div class="ln" style="font-size: '.$pt($dikey ? 14 : ($kart ? 11 : 12)).';">'.e($b['last_name']).'</div>'
                                                .($b['group'] ? '<div class="grp" style="color: '.$c.';"><span class="dot" style="background: '.$c.';"></span>'.e($b['group']).'</div>' : '');
                                        @endphp

                                        @if ($dikey)
                                            <div style="text-align: center; padding: 0 {{ $px(12) }}; margin-top: -{{ $px(32) }};">
                                                {!! $photoHtml !!}
                                                <div style="margin-top: {{ $px(4) }};">{!! $names !!}</div>
                                                @if ($rows)
                                                    <table class="dl" style="margin-top: {{ $px(5) }}; text-align: left; width: 100%;">
                                                        @foreach ($rows as [$dt, $dd])<tr><td class="dt">{{ $dt }}</td><td class="dd">{{ $dd }}</td></tr>@endforeach
                                                    </table>
                                                @endif
                                            </div>
                                        @else
                                            <div style="padding: {{ $kart ? $px(4) : $px(8) }} {{ $px(10) }};">
                                                <table style="width: 100%;">
                                                    <tr>
                                                        @if ($settings->shows('photo'))
                                                            <td style="width: {{ $px($photo + 12) }}; vertical-align: top;" @if ($kart) rowspan="2" @endif>{!! $photoHtml !!}</td>
                                                        @endif
                                                        <td style="vertical-align: top; padding-left: {{ $px(6) }};">{!! $names !!}</td>
                                                    </tr>
                                                    @if ($kart && $rows)
                                                        <tr><td style="padding-left: {{ $px(6) }};">
                                                            <table class="dl tight">@foreach ($rows as [$dt, $dd])<tr><td class="dt">{{ $dt }}</td><td class="dd">{{ $dd }}</td></tr>@endforeach</table>
                                                        </td></tr>
                                                    @endif
                                                </table>
                                                @if ($yatay && $rows)
                                                    <table class="dl tight" style="margin-top: {{ $px(4) }};">
                                                        @foreach (array_chunk($rows, 2) as $pair)
                                                            <tr>
                                                                @foreach ($pair as [$dt, $dd])<td class="dt">{{ $dt }}</td><td class="dd">{{ $dd }}</td>@endforeach
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                @endif
                                            </div>
                                        @endif

                                        <div class="foot" style="background: {{ $tint($c) }}; border-top: {{ $px(3) }} solid {{ $c }};">
                                            <table>
                                                <tr>
                                                    <td>Seri no {{ $b['serial'] }}</td>
                                                    <td style="text-align: right;">@if ($b['qr'])<img src="{{ $b['qr'] }}" alt="" style="width: {{ $px($qr) }}; height: {{ $px($qr) }};">@endif</td>
                                                </tr>
                                            </table>
                                        </div>
                                    @else
                                        <div class="back">
                                            <h6 style="color: {{ $c }};">{{ $lostTitle }}</h6>
                                            @if ($arabic)<div class="ar">{!! implode('<br>', array_map('e', $arabic)) !!}</div>@endif
                                            @if ($lostSub && ! $kart && ! ($yatay && $b['health']))<div class="sub">{{ $lostSub }}</div>@endif
                                            <div class="tel">{{ $emergencyPhone ?? '—' }}</div>
                                            @if ($dikey)
                                                <table class="dl" style="width: 100%;">
                                                    @if ($b['guide_name'])<tr><td class="dt">Rehber</td><td class="dd">{{ implode(' · ', array_filter([$b['guide_name'], $b['guide_phone']])) }}</td></tr>@endif
                                                    @foreach ($b['hotels'] as $hotel)
                                                        <tr><td class="dt">{{ $hotel['city'] }}</td><td class="dd">{{ implode(', ', array_filter([$hotel['hotel'], $hotel['address']])) }}</td></tr>
                                                    @endforeach
                                                </table>
                                            @else
                                                <div class="small">
                                                    @if ($b['guide_name'])Rehber {{ implode(' · ', array_filter([$b['guide_name'], $b['guide_phone']])) }}<br>@endif
                                                    @unless ($b['health']){{ implode(' · ', array_map(fn ($hh) => $hh['city'].': '.$short($hh['hotel']), $b['hotels'])) }}@endunless
                                                </div>
                                            @endif
                                            @if ($b['health'])<div class="med">Sağlık: {{ $b['health'] }}</div>@endif
                                        </div>
                                        <div class="foot" style="background: {{ $tint($c) }}; border-top: {{ $px(3) }} solid {{ $c }};">
                                            <table>
                                                <tr>
                                                    <td>{{ $tenant?->name }} · 7/24</td>
                                                    <td style="text-align: right;">{{ $b['group'] }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </table>
            @if (! ($loop->parent->last && $loop->last))
                <div class="page-break"></div>
            @endif
        @endforeach
    @endforeach
</body>
</html>
