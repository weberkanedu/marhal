<?php

namespace App\Reports\Exporters;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * "Yolcu aktarma şablonu": 1. sayfa doldurulacak tablo (sadece başlıklar), 2. sayfa açıklamalar ve örnek.
 * T.C., pasaport ve telefon sütunları metin biçiminde (Excel baştaki sıfırı silmesin, sayıyı bozmasın).
 */
class PersonImportTemplate implements Export, WithMultipleSheets
{
    public const HEADINGS = [
        'Ad', 'Soyad', 'Cinsiyet', 'Doğum Tarihi', 'Uyruk', 'T.C. Kimlik No', 'Pasaport No',
        'Pasaport Veriliş Tarihi', 'Pasaport Bitiş Tarihi', 'Telefon', 'E-posta', 'Adres',
        'Acil Durum Kişisi', 'Acil Durum Telefonu', 'Oda Tipi', 'Ücret', 'KVKK Onayı', 'Notlar',
    ];

    /**
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            new class implements FromArray, ShouldAutoSize, WithColumnFormatting, WithHeadings, WithStyles, WithTitle
            {
                public function title(): string
                {
                    return 'Yolcular';
                }

                /**
                 * @return list<string>
                 */
                public function headings(): array
                {
                    return PersonImportTemplate::HEADINGS;
                }

                /**
                 * Boş: örnekler "Açıklamalar" sayfasında (unutulup aktarılmasın).
                 *
                 * @return list<list<string>>
                 */
                public function array(): array
                {
                    return [];
                }

                /**
                 * @return array<string, string>
                 */
                public function columnFormats(): array
                {
                    return ['F' => NumberFormat::FORMAT_TEXT, 'G' => NumberFormat::FORMAT_TEXT, 'J' => NumberFormat::FORMAT_TEXT, 'N' => NumberFormat::FORMAT_TEXT];
                }

                /**
                 * @return array<int, array<string, mixed>>
                 */
                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true]]];
                }
            },
            new class implements FromArray, ShouldAutoSize, WithStyles, WithTitle
            {
                public function title(): string
                {
                    return 'Açıklamalar';
                }

                /**
                 * @return list<list<string>>
                 */
                public function array(): array
                {
                    return [
                        ['Marhal — Excel\'den yolcu aktarma'],
                        [''],
                        ['Zorunlu sütunlar: Ad, Soyad, Cinsiyet. Diğerleri boş bırakılabilir.'],
                        ['Cinsiyet: Erkek / Kadın (E / K, Bay / Bayan da olur).'],
                        ['Tarihler: GG.AA.YYYY (ör. 15.03.1965). Excel tarih biçimi de olur.'],
                        ['Uyruk: TR, SA, DE gibi 2 harfli ülke kodu; boşsa TR.'],
                        ['T.C. Kimlik No: Türk vatandaşları için doğruluğu kontrol edilir.'],
                        ['Oda Tipi: 2, 3, 4 veya 5 (kişilik). Ücret: rakam (ör. 1500). Sadece tura da kaydederken kullanılır.'],
                        ['KVKK Onayı: Evet yazılırsa aydınlatma / açık rıza onay tarihi olarak bugün kaydedilir.'],
                        ['Aynı T.C. veya pasaport no ile sistemde zaten olan kişiler yeniden oluşturulmaz, değiştirilmez.'],
                        ['Sütun başlıklarını değiştirmeyin; sıraları önemli değil. Kendi Excel\'inizi de başlıklar uyuyorsa yükleyebilirsiniz.'],
                        ['Bir seferde en fazla 1000 yolcu. Yüklemeden sonra her satır kontrol edilir, onaylamadan hiçbir şey kaydedilmez.'],
                        [''],
                        ['Örnek (bu sayfadaki satırlar aktarılmaz; "Yolcular" sayfasını doldurun):'],
                        PersonImportTemplate::HEADINGS,
                        ['Ahmet', 'Yılmaz', 'Erkek', '15.03.1965', 'TR', '', 'U12345678', '01.02.2022', '01.02.2032', '05321234567', '', 'Fatih, İstanbul', 'Ayşe Yılmaz', '05327654321', '2', '1500', 'Evet', ''],
                        ['Ayşe', 'Yılmaz', 'Kadın', '20.07.1968', 'TR', '', 'U87654321', '01.02.2022', '01.02.2032', '05327654321', '', 'Fatih, İstanbul', 'Ahmet Yılmaz', '05321234567', '2', '1500', 'Evet', ''],
                    ];
                }

                /**
                 * @return array<int, array<string, mixed>>
                 */
                public function styles(Worksheet $sheet): array
                {
                    return [1 => ['font' => ['bold' => true, 'size' => 13]]];
                }
            },
        ];
    }
}
