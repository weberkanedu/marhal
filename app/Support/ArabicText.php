<?php

namespace App\Support;

/**
 * PDF için Arapça metin hazırlama. dompdf Arapça harfleri birleştirmez ve sağdan sola dizmez;
 * bu sınıf her harfin bağlı biçimini (Unicode "Arabic Presentation Forms-B", DejaVu Sans'ta var)
 * seçer ve satırı görsel sıraya (sağdan sola) çevirir. Rakamlar / Latin harfler soldan sağa kalır.
 * Yalnız kısa, sabit cümleler için (yaka kartı arka yüzü); hareke desteklenmez.
 */
final class ArabicText
{
    /** harf → [ayrı, son, baş, orta]; son ikisi null ise sonraki harfe bağlanmaz. */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],
        0x0622 => [0xFE81, 0xFE82, null, null],
        0x0623 => [0xFE83, 0xFE84, null, null],
        0x0624 => [0xFE85, 0xFE86, null, null],
        0x0625 => [0xFE87, 0xFE88, null, null],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, null, null],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, null, null],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, null, null],
        0x0630 => [0xFEAB, 0xFEAC, null, null],
        0x0631 => [0xFEAD, 0xFEAE, null, null],
        0x0632 => [0xFEAF, 0xFEB0, null, null],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, null, null],
        0x0649 => [0xFEEF, 0xFEF0, null, null],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
    ];

    /** lam + elif çeşitleri → [ayrı, son] bitişik biçim */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    /**
     * Mantıksal sıradaki Arapça metni PDF'te doğru görünecek biçime çevirir.
     */
    public static function forPdf(string $text): string
    {
        $chars = mb_str_split($text);
        $codes = array_map('mb_ord', $chars);
        $shaped = [];
        $count = count($codes);

        for ($i = 0; $i < $count; $i++) {
            $code = $codes[$i];

            if (! isset(self::FORMS[$code])) {
                $shaped[] = $chars[$i];

                continue;
            }

            $joinsPrev = $i > 0 && self::joinsNext($codes[$i - 1]);

            // Lam + elif tek bitişik harf olur.
            if ($code === 0x0644 && isset($codes[$i + 1], self::LAM_ALEF[$codes[$i + 1]])) {
                $shaped[] = mb_chr(self::LAM_ALEF[$codes[$i + 1]][$joinsPrev ? 1 : 0]);
                $i++;

                continue;
            }

            $joinsNext = self::joinsNext($code) && isset($codes[$i + 1], self::FORMS[$codes[$i + 1]]);
            [$isolated, $final, $initial, $medial] = self::FORMS[$code];

            $form = match (true) {
                $joinsPrev && $joinsNext => $medial,
                $joinsPrev => $final,
                $joinsNext => $initial,
                default => $isolated,
            };

            $shaped[] = mb_chr($form ?? $isolated);
        }

        return self::visualOrder($shaped);
    }

    private static function joinsNext(int $code): bool
    {
        return isset(self::FORMS[$code]) && self::FORMS[$code][2] !== null;
    }

    /**
     * Satırı sağdan sola diz; içindeki rakam / Latin parçaları kendi içinde soldan sağa kalsın.
     *
     * @param  list<string>  $chars
     */
    private static function visualOrder(array $chars): string
    {
        $runs = [];

        foreach ($chars as $char) {
            $ltr = preg_match('/[0-9A-Za-z+]/u', $char) === 1;
            $last = array_key_last($runs);

            if ($last !== null && $runs[$last]['ltr'] === $ltr) {
                $runs[$last]['text'] .= $char;
            } else {
                $runs[] = ['ltr' => $ltr, 'text' => $char];
            }
        }

        return implode('', array_map(
            fn (array $run) => $run['ltr'] ? $run['text'] : implode('', array_reverse(mb_str_split($run['text']))),
            array_reverse($runs),
        ));
    }
}
