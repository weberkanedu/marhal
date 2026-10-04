<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Kişiler arası yakınlık. Kayıt "ilgili kişi, kişinin X'idir" anlamındadır
 * (person → related_person, relation = X). Her yakınlık iki yönlü saklanır.
 *
 * "Diğer" dışındaki tüm yakınlıklar aile odası kuralında aile sayılır (müşteri kararı, FAZ2.md).
 */
enum Relation: string
{
    use HasOptions;

    case Spouse = 'es';
    case Mother = 'anne';
    case Father = 'baba';
    case Child = 'cocuk';
    case Sibling = 'kardes';
    case MotherInLaw = 'kayinvalide';
    case FatherInLaw = 'kayinpeder';
    case DaughterInLaw = 'gelin';
    case SonInLaw = 'damat';
    case Grandmother = 'buyukanne';
    case Grandfather = 'buyukbaba';
    case Grandchild = 'torun';
    case Uncle = 'amca_dayi';
    case Aunt = 'hala_teyze';
    case Nephew = 'yegen';
    case Other = 'diger';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'Eşi',
            self::Mother => 'Annesi',
            self::Father => 'Babası',
            self::Child => 'Çocuğu',
            self::Sibling => 'Kardeşi',
            self::MotherInLaw => 'Kayınvalidesi',
            self::FatherInLaw => 'Kayınpederi',
            self::DaughterInLaw => 'Gelini',
            self::SonInLaw => 'Damadı',
            self::Grandmother => 'Büyükannesi',
            self::Grandfather => 'Büyükbabası',
            self::Grandchild => 'Torunu',
            self::Uncle => 'Amcası / dayısı',
            self::Aunt => 'Halası / teyzesi',
            self::Nephew => 'Yeğeni',
            self::Other => 'Diğer (aile sayılmaz)',
        };
    }

    /**
     * Aile odasında birlikte kalabilir mi?
     */
    public function isFamily(): bool
    {
        return $this !== self::Other;
    }

    /**
     * Ters yön: "B, A'nın annesi" ise "A, B'nin çocuğu". Cinsiyete bağlı olanlarda
     * A'nın cinsiyeti kullanılır (ör. kayınvalidenin karşılığı gelin veya damat).
     */
    public function inverse(Gender $personGender): self
    {
        $male = $personGender === Gender::Male;

        return match ($this) {
            self::Spouse, self::Sibling, self::Other => $this,
            self::Mother, self::Father => self::Child,
            self::Child => $male ? self::Father : self::Mother,
            self::MotherInLaw, self::FatherInLaw => $male ? self::SonInLaw : self::DaughterInLaw,
            self::DaughterInLaw, self::SonInLaw => $male ? self::FatherInLaw : self::MotherInLaw,
            self::Grandmother, self::Grandfather => self::Grandchild,
            self::Grandchild => $male ? self::Grandfather : self::Grandmother,
            self::Uncle, self::Aunt => self::Nephew,
            self::Nephew => $male ? self::Uncle : self::Aunt,
        };
    }
}
