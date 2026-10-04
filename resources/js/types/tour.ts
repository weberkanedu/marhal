import type { Gender, Option } from '@/types/person';

export type TourStatus =
    | 'taslak'
    | 'satista'
    | 'kapandi'
    | 'tamamlandi'
    | 'iptal';

export type RegistrationStatus = 'on_kayit' | 'kesin_kayit' | 'iptal';

export type TourSummary = {
    id: string;
    name: string;
    type: 'umre' | 'hac';
    status: TourStatus;
    status_label: string;
    start_date: string;
    end_date: string;
    capacity: number | null;
    default_price: string | null;
    currency: string;
    notes?: string | null;
};

export type TourListItem = TourSummary & {
    groups_count: number;
    registrations_count: number;
};

export type TourFormOptions = {
    types: Option[];
    statuses: Option<TourStatus>[];
    currencies: string[];
};

export type TourGroup = {
    id: string;
    name: string;
    guide_user_id: number | null;
    guide_name: string | null;
    guide_phone: string | null;
    notes: string | null;
    registrations_count: number;
};

export type RegistrationRow = {
    id: string;
    person: {
        id: string;
        full_name: string;
        gender: Gender;
        phone: string | null;
        masked_passport_no: string | null;
        passport_expiring: boolean;
        passport_missing: boolean;
    };
    group_id: string | null;
    group_name: string | null;
    room_type: string | null;
    status: RegistrationStatus;
    price: string;
    discount: string;
    net_price: string;
    paid: string;
    balance: string;
    currency: string;
    cancel_reason: string | null;
    notes: string | null;
};

export type TourShowOptions = TourFormOptions & {
    roomTypes: Option[];
    registrationStatuses: Option<RegistrationStatus>[];
    guides: { id: number; name: string }[];
};

export type TourStats = {
    registered: number;
    confirmed: number;
    pending: number;
    cancelled: number;
    unassigned: number;
    total: string;
    paid: string;
    balance: string;
};

export const tourStatusVariant: Record<
    TourStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    taslak: 'outline',
    satista: 'default',
    kapandi: 'secondary',
    tamamlandi: 'secondary',
    iptal: 'destructive',
};
