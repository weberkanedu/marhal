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
    | 'badge_generation'
    | 'advanced_reporting'
    | 'api_access';
