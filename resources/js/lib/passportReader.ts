import { parse } from 'mrz';

/**
 * Pasaport fotoğrafından alttaki iki satırı (MRZ) tarayıcıda okur. Fotoğraf sunucuya gönderilmez.
 * Açık kaynak: tesseract.js (yazı tanıma; sayfa açılınca yüklenir) + mrz (satırları ayrıştırır, kontrol
 * hanelerini doğrular). Yanlış okunan alanı yolcu bir sonraki adımda düzeltir.
 */
export type PassportFields = {
    first_name: string;
    last_name: string;
    gender: 'erkek' | 'kadin' | '';
    birth_date: string;
    nationality: string;
    passport_no: string;
    passport_expiry_date: string;
};

export type PassportReadResult = {
    fields: PassportFields;
    valid: boolean;
} | null;

// MRZ'deki üç harfli ülke kodu → iki harf (sık gelenler; bilinmeyende ilk iki harf).
const COUNTRIES: Record<string, string> = {
    TUR: 'TR',
    DEU: 'DE',
    D: 'DE',
    NLD: 'NL',
    FRA: 'FR',
    AUT: 'AT',
    BEL: 'BE',
    GBR: 'GB',
    USA: 'US',
    AZE: 'AZ',
    BIH: 'BA',
    BGR: 'BG',
    CHE: 'CH',
    DNK: 'DK',
    SWE: 'SE',
    NOR: 'NO',
    ITA: 'IT',
    ESP: 'ES',
    GRC: 'GR',
    MKD: 'MK',
    ALB: 'AL',
    KAZ: 'KZ',
    UZB: 'UZ',
    TKM: 'TM',
    KGZ: 'KG',
    SYR: 'SY',
    IRQ: 'IQ',
    IRN: 'IR',
    SAU: 'SA',
    EGY: 'EG',
    PAK: 'PK',
    AFG: 'AF',
    RUS: 'RU',
    UKR: 'UA',
    GEO: 'GE',
    CAN: 'CA',
    AUS: 'AU',
};

// YYAAGG → YYYY-AA-GG; doğum tarihinde gelecek yıl olamaz, geçerlilikte hep 20xx.
function date(
    value: string | null | undefined,
    kind: 'birth' | 'expiry',
): string {
    if (!value || !/^\d{6}$/.test(value)) {
        return '';
    }

    const yy = Number(value.slice(0, 2));
    const now = new Date().getFullYear() % 100;
    const century = kind === 'expiry' ? 2000 : yy > now ? 1900 : 2000;

    return `${century + yy}-${value.slice(2, 4)}-${value.slice(4, 6)}`;
}

// "YILMAZ<<AYSE" → "Yılmaz", "Ayse" (Türkçe büyük / küçük harf kuralıyla; MRZ'de ş, ğ, ü yoktur, yolcu düzeltir).
const tr = (s: string) =>
    s
        .replace(/</g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLocaleLowerCase('tr')
        .replace(
            /(^|\s)(\S)/g,
            (_m, space: string, c: string) => space + c.toLocaleUpperCase('tr'),
        );

// Fotoğrafın alt kısmını (MRZ bölgesi) büyütüp gri tonlu, yüksek kontrastlı hale getirir.
function prepare(img: ImageBitmap, part: number): HTMLCanvasElement {
    const width = Math.min(1600, img.width);
    const scale = width / img.width;
    const height = img.height * scale;
    const top = height * (1 - part);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height * part;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    if (!ctx) {
        return canvas;
    }

    ctx.drawImage(
        img,
        0,
        top / scale,
        img.width,
        (height * part) / scale,
        0,
        0,
        canvas.width,
        canvas.height,
    );
    const data = ctx.getImageData(0, 0, canvas.width, canvas.height);

    for (let i = 0; i < data.data.length; i += 4) {
        const g =
            0.299 * data.data[i] +
            0.587 * data.data[i + 1] +
            0.114 * data.data[i + 2];
        const v = g > 140 ? 255 : g < 90 ? 0 : (g - 90) * 5.1;
        data.data[i] = data.data[i + 1] = data.data[i + 2] = v;
    }

    ctx.putImageData(data, 0, 0);

    return canvas;
}

// Ad satırının sonundaki dolgu işaretleri (<<<) sık sık L / K / C olarak okunur: 3 ve daha uzun bu tür
// diziler dolguya çevrilir: '<' ile başlıyorsa ya da aynı harf en az beş kez tekrar ediyorsa.
function fixFiller(line: string): string {
    return (
        line.slice(0, 5) +
        line
            .slice(5)
            .replace(/[<LKC]{3,}/g, (run) =>
                run.startsWith('<') || /^(.)\1{4,}$/.test(run)
                    ? '<'.repeat(run.length)
                    : run,
            )
    );
}

// Yazı tanımanın çıktısından iki MRZ satırını (44 karakter) bulur.
function lines(text: string): string[] | null {
    const candidates = text
        .split('\n')
        .map((l) =>
            l
                .replace(/\s/g, '')
                .replace(/[«‹([{]/g, '<')
                .toUpperCase(),
        )
        .filter((l) => l.length >= 36 && /^[A-Z0-9<]+$/.test(l));

    for (let i = candidates.length - 2; i >= 0; i--) {
        const first = candidates[i];

        if (first.startsWith('P')) {
            return [fixFiller(first), candidates[i + 1]].map((l) =>
                (l + '<'.repeat(44)).slice(0, 44),
            );
        }
    }

    return candidates.length >= 2
        ? candidates.slice(-2).map((l) => (l + '<'.repeat(44)).slice(0, 44))
        : null;
}

export async function readPassport(
    file: File,
    onProgress?: (p: number) => void,
): Promise<PassportReadResult> {
    // createImageBitmap ekrana çizmeden açar (telefon yatay / dikey EXIF yönünü de uygular).
    const img = await createImageBitmap(file, {
        imageOrientation: 'from-image',
    });
    const { createWorker } = await import('tesseract.js');
    // Dosyalar kendi sunucumuzdan (vite.config.ts → public/vendor/tesseract), başka siteye istek gitmez.
    const worker = await createWorker('eng', 1, {
        workerPath: '/vendor/tesseract/worker.min.js',
        corePath: '/vendor/tesseract',
        langPath: '/vendor/tesseract/lang',
        workerBlobURL: false,
        logger: (m: { status: string; progress: number }) =>
            m.status === 'recognizing text' && onProgress?.(m.progress),
    });

    try {
        await worker.setParameters({
            tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
        });

        // Önce alt üçte bir (MRZ), bulunamazsa alt yarı.
        for (const part of [0.32, 0.5]) {
            const { data } = await worker.recognize(prepare(img, part));
            const mrz = lines(data.text);

            if (!mrz) {
                continue;
            }

            const result = parse(mrz, { autocorrect: true });
            const f = result.fields;

            if (!f.lastName && !f.documentNumber) {
                continue;
            }

            const nat = (f.nationality ?? f.issuingState ?? 'TUR').replace(
                /</g,
                '',
            );

            return {
                valid: result.valid,
                fields: {
                    first_name: tr(f.firstName ?? ''),
                    last_name: tr(f.lastName ?? ''),
                    gender:
                        f.sex === 'female'
                            ? 'kadin'
                            : f.sex === 'male'
                              ? 'erkek'
                              : '',
                    birth_date: date(f.birthDate, 'birth'),
                    nationality: COUNTRIES[nat] ?? nat.slice(0, 2),
                    passport_no: (f.documentNumber ?? '').replace(/</g, ''),
                    passport_expiry_date: date(f.expirationDate, 'expiry'),
                },
            };
        }

        return null;
    } finally {
        await worker.terminate();
    }
}
