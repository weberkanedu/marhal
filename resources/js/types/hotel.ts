export type HotelCity = 'mekke' | 'medine' | 'diger';

export type HotelRow = {
    id: string;
    name: string;
    city: HotelCity;
    address: string | null;
    phone: string | null;
    stars: number | null;
    notes: string | null;
    stays_count: number;
};

export type HotelOption = {
    id: string;
    name: string;
    city: HotelCity;
    city_label: string;
};

// Turun bir konaklaması (otel + tarihler + o otelde kalan gruplar).
export type TourStay = {
    id: string;
    hotel_id: string;
    hotel_name: string;
    city: HotelCity;
    city_label: string;
    check_in: string;
    check_out: string;
    nights: number;
    groups: { id: string; name: string }[];
    notes: string | null;
};
