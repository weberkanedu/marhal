<?php

namespace App\Enums;

/**
 * Paketlerle açılıp kapatılan modüller (SPEC.md §2).
 */
enum Feature: string
{
    case Passengers = 'passengers';
    case Payments = 'payments';
    case BasicReports = 'basic_reports';
    case RoomPlanning = 'room_planning';
    case BusPlanning = 'bus_planning';
    case FlightLists = 'flight_lists';
    case BadgeGeneration = 'badge_generation';
    case Readiness = 'readiness';
    case FamilyScreen = 'family_screen';
    case OnlineSignup = 'online_signup';
    case AdvancedReporting = 'advanced_reporting';
    case ApiAccess = 'api_access';
}
