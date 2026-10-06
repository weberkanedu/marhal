import type { DashboardTour } from '@/types/dashboard';
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
    whatsapp_link: string | null;
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
    // Kart bandı rengi; color_chosen false ise paletten otomatik verildi.
    color: string;
    color_chosen: boolean;
    registrations_count: number;
};

export type RegistrationRow = {
    id: string;
    person: {
        id: string;
        full_name: string;
        gender: Gender;
        age: number | null;
        phone: string | null;
        emergency_contact: string | null;
        masked_passport_no: string | null;
        passport_expiring: boolean;
        passport_missing: boolean;
    };
    group_id: string | null;
    group_name: string | null;
    room_type: string | null;
    // Oda ve koltuk: [{label: "Mekke", value: "501"}, {label: "1. Otobüs", value: "12"}]
    placements: { label: string; value: string }[];
    rooms: { label: string; value: string }[];
    seat: { label: string; value: string } | null;
    // Aile ekranı linki (etkinse; yalnız personel, modül açıksa).
    family_link?: {
        id: string;
        url: string;
        views: number;
        expires_at: string;
    } | null;
    // İhtiyaç adları (tekerlekli sandalye, diyabet …); notlar yolcu sayfasında.
    needs: string[];
    status: RegistrationStatus;
    // Rehber (finans yetkisi yok) için null gelir.
    price: string | null;
    discount: string | null;
    net_price: string | null;
    paid: string | null;
    balance: string | null;
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
    total: string | null;
    paid: string | null;
    balance: string | null;
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

/** Yolculuk çizelgesinin bir adımı (App\Support\Tours\TourJourney). */
export type JourneyStep = {
    key: string;
    kind: 'prep' | 'outbound' | 'stay' | 'return';
    title: string;
    detail: string | null;
    start: string;
    end: string;
    state: 'done' | 'now' | 'next';
};

/** Tur sayfasındaki hazırlık özeti (ana paneldeki tur satırıyla aynı hesap). */
export type TourReadinessSummary = Pick<
    DashboardTour,
    | 'collection'
    | 'registered'
    | 'pending'
    | 'ungrouped'
    | 'passport_issues'
    | 'checks'
>;

/** Tur → "Hazırlık" sekmesi (App\Support\Readiness\ReadinessBoard). */
export type ReadinessCell = {
    status: 'tamam' | 'sorun' | null;
    // Pasaport / fotoğraf: yolcu bilgisinden kendiliğinden dolar, tıklanmaz.
    auto: boolean;
    title: string | null;
};

export type ReadinessBoardData = {
    items: { id: string; name: string; kind: string; automatic: boolean }[];
    rows: {
        registration_id: string;
        name: string;
        gender: string;
        age: number | null;
        group_id: string | null;
        group_name: string | null;
        cells: Record<string, ReadinessCell>;
        done: number;
    }[];
    ready: number;
    total: number;
    ravza: {
        men: { at: string | null; done: number; waiting: number };
        women: { at: string | null; done: number; waiting: number };
    } | null;
    // Personel için acentenin bütün açık maddeleri (madde hapları); rehberde boş.
    all_items: { id: string; name: string }[];
};

/** Turun gün gün programındaki etkinlik (aile ekranı ve "Tur programı" çıktısı). */
export type ProgramItem = {
    id: string;
    day: string;
    time: string | null;
    title: string;
    place: string | null;
};
