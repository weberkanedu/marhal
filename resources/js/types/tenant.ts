export type UserRole = 'super_admin' | 'admin' | 'operasyon' | 'rehber';

export type Tenant = {
    id: string;
    name: string;
    default_currency: string;
};

export type FeatureKey =
    | 'passengers'
    | 'payments'
    | 'basic_reports'
    | 'room_planning'
    | 'bus_planning'
    | 'flight_lists'
    | 'flight_seats'
    | 'need_rules'
    | 'badge_generation'
    | 'readiness'
    | 'family_screen'
    | 'online_signup'
    | 'advanced_reporting'
    | 'api_access';
