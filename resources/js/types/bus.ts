import type { Gender } from '@/types/person';

// Koltuk düzeni hücresi: koltuk no, koridor, kapı veya boşluk (sunucudaki BusLayout ile aynı).
export type BusCell = number | 'aisle' | 'door' | null;

export type VehicleTypeRow = {
    id: string;
    name: string;
    left_seats: number;
    right_seats: number;
    rows: number;
    back_row_seats: number;
    door_row: number | null;
    notes: string | null;
    label: string;
    seat_count: number;
    buses_count: number;
};

export type VehicleTypeOption = { id: string; name: string; label: string };

export type TourBus = {
    id: string;
    name: string;
    vehicle_type_id: string | null;
    vehicle_type: string | null;
    label: string;
    plate: string | null;
    driver_name: string | null;
    driver_phone: string | null;
    notes: string | null;
    reserved: number[];
    groups: { id: string; name: string }[];
    seats: number;
    occupied: number;
};

export type SeatPassenger = {
    registration_id: string;
    full_name: string;
    gender: Gender;
    age: number | null;
    group_name: string | null;
};

export type SeatOccupant = Omit<
    SeatPassenger,
    'registration_id' | 'gender' | 'age'
> & {
    seat_id: string;
    // Rehber başka grubun yolcusunu sadece "Başka grup" olarak görür.
    registration_id: string | null;
    gender: Gender | null;
    age?: number | null;
    warnings: string[];
};

export type UnseatedPassenger = SeatPassenger & {
    family: { name: string; relation: string; seat_no: number | null }[];
};

export type OtherSeatPassenger = SeatPassenger & { elsewhere: string | null };

export type SeatPlanBus = {
    id: string;
    name: string;
    label: string;
    vehicle_type: string | null;
    plate: string | null;
    driver_name: string | null;
    driver_phone: string | null;
    reserved: number[];
    groups: string[];
    tour: { id: string; name: string };
};

export type SeatPlanStats = {
    seats: number;
    reserved: number;
    occupied: number;
    unassigned: number;
};

export type SeatAutoPreview = {
    placed: number;
    placements: { seat: number; name: string }[];
    unplaced: { name: string; reason: string }[];
};

/**
 * Araç tipi formundaki canlı önizleme için düzen hesabı (sunucudaki BusLayout::grid ile aynı kural).
 */
export function busGrid(
    left: number,
    right: number,
    rows: number,
    backRow = 0,
    doorRow: number | null = null,
): BusCell[][] {
    const grid: BusCell[][] = [];
    let no = 1;

    for (let row = 1; row <= rows; row++) {
        const cells: BusCell[] = [];

        for (let i = 0; i < left; i++) {
            cells.push(no++);
        }

        cells.push('aisle');

        for (let i = 0; i < right; i++) {
            cells.push(row === doorRow ? 'door' : no++);
        }

        grid.push(cells);
    }

    if (backRow > 0) {
        const width = left + 1 + right;
        const cells: BusCell[] = [];

        for (let i = 0; i < width; i++) {
            cells.push(i < backRow ? no++ : null);
        }

        grid.push(cells);
    }

    return grid;
}
