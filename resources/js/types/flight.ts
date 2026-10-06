export type FlightDirection = 'gidis' | 'donus' | 'aktarma';

export type FlightSummary = {
    id: string;
    direction: FlightDirection;
    direction_label: string;
    airline: string;
    flight_no: string;
    departure_airport: string;
    arrival_airport: string;
    // "2026-11-01T10:00" (havalimanı yerel saati)
    departure_at: string;
    arrival_at: string;
    pnr: string | null;
    baggage: string | null;
    notes: string | null;
};

export type TourFlight = FlightSummary & {
    passengers_count: number;
    seated_count: number;
    aircraft: string | null;
};

export type FlightPassengerRow = {
    id: string;
    registration_id: string;
    title: string;
    full_name: string;
    group_name: string | null;
    // Rehber için null.
    masked_passport_no: string | null;
    passport_expiry: string | null;
    pnr: string | null;
    ticket_no: string | null;
    warnings: string[];
};

// Sık kullanılan havalimanları (öneri listesi; başka kod da yazılabilir).
export const commonAirports: { code: string; name: string }[] = [
    { code: 'IST', name: 'İstanbul' },
    { code: 'SAW', name: 'İstanbul Sabiha Gökçen' },
    { code: 'ESB', name: 'Ankara Esenboğa' },
    { code: 'ADB', name: 'İzmir' },
    { code: 'AYT', name: 'Antalya' },
    { code: 'ADA', name: 'Adana' },
    { code: 'GZT', name: 'Gaziantep' },
    { code: 'TZX', name: 'Trabzon' },
    { code: 'KYA', name: 'Konya' },
    { code: 'JED', name: 'Cidde' },
    { code: 'MED', name: 'Medine' },
    { code: 'TIF', name: 'Taif' },
    { code: 'RUH', name: 'Riyad' },
];

/**
 * "2026-11-01T10:00" → "01.11.2026 10:00"
 */
export function formatFlightTime(value: string): string {
    const [date, time] = value.split('T');
    const [y, m, d] = date.split('-');

    return `${d}.${m}.${y} ${time}`;
}
