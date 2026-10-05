export type Gender = 'erkek' | 'kadin';

export type Option<T extends string = string> = {
    value: T;
    label: string;
};

export type PersonListItem = {
    id: string;
    full_name: string;
    gender: Gender;
    birth_date: string | null;
    masked_phone: string | null;
    masked_national_id: string | null;
    masked_passport_no: string | null;
    passport_expiry_date: string | null;
    // Pasaport no yok / bitiş tarihi yok / 6 aydan az geçerli; sorun yoksa null.
    passport_issue: string | null;
    on_tour: boolean;
    // İhtiyaç adları (yalnız Yolcular listesinde; aramada yok).
    needs?: string[];
    has_photo: boolean;
};

export type PersonDetail = {
    id: string;
    first_name: string;
    last_name: string;
    full_name: string;
    gender: Gender;
    birth_date: string | null;
    nationality: string;
    masked_national_id: string | null;
    masked_passport_no: string | null;
    passport_issue_date: string | null;
    passport_expiry_date: string | null;
    passport_expiring: boolean;
    phone: string | null;
    email: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_phone: string | null;
    notes: string | null;
    kvkk_consent: boolean;
    kvkk_consent_at: string | null;
    photo_url: string | null;
    created_at?: string | null;
};

export type PersonFormOptions = {
    genders: Option<Gender>[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
