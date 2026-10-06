<?php

namespace App\Enums;

/**
 * Paketlerle açılıp kapatılan modüller (SPEC.md §2). Sıra, platform panelindeki ve paket kartlarındaki sıradır.
 */
enum Feature: string
{
    case Passengers = 'passengers';
    case Payments = 'payments';
    case BasicReports = 'basic_reports';
    case RoomPlanning = 'room_planning';
    case BusPlanning = 'bus_planning';
    case BadgeGeneration = 'badge_generation';
    case Readiness = 'readiness';
    case FlightLists = 'flight_lists';
    case FlightSeats = 'flight_seats';
    case NeedRules = 'need_rules';
    case FamilyScreen = 'family_screen';
    case OnlineSignup = 'online_signup';
    case AdvancedReporting = 'advanced_reporting';
    case ApiAccess = 'api_access';

    public function label(): string
    {
        return match ($this) {
            self::Passengers => 'Yolcu, tur ve grup',
            self::Payments => 'Ödeme ve tahsilat',
            self::BasicReports => 'Excel / PDF çıktıları',
            self::RoomPlanning => 'Otel planı (sürükle-bırak)',
            self::BusPlanning => 'Araç planı (sürükle-bırak)',
            self::BadgeGeneration => 'Yaka kartı (bütün boylar)',
            self::Readiness => 'Hazırlık takibi',
            self::FlightLists => 'Uçuş listeleri',
            self::FlightSeats => 'Uçak koltuk planı',
            self::NeedRules => 'İhtiyaca göre yerleşim kuralları',
            self::FamilyScreen => 'Aile ekranı',
            self::OnlineSignup => 'Telefonla ön kayıt (fotoğraf)',
            self::AdvancedReporting => 'Gelişmiş raporlar',
            self::ApiAccess => 'Çoklu şube ve API',
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $f) => ['key' => $f->value, 'label' => $f->label()], self::cases());
    }
}
