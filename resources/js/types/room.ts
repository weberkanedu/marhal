import type { Gender, Option } from '@/types/person';

export type RoomKind = 'erkek' | 'kadin' | 'aile';

export type RoomPassenger = {
    registration_id: string;
    full_name: string;
    gender: Gender;
    group_name: string | null;
    room_type: string | null;
    room_type_label: string | null;
    // İhtiyaç adları (tekerlekli sandalye, diyabet …).
    needs?: string[];
};

export type RoomOccupant = Omit<RoomPassenger, 'registration_id' | 'gender'> & {
    assignment_id: string;
    // Rehber başka grubun yolcusunu sadece "Başka grup" olarak görür (null).
    registration_id: string | null;
    gender: Gender | null;
    warnings: string[];
};

export type PlanRoom = {
    id: string;
    room_no: string;
    floor: string | null;
    capacity: number;
    kind: RoomKind;
    notes: string | null;
    near_elevator: boolean;
    occupants: RoomOccupant[];
};

export type UnassignedPassenger = RoomPassenger & {
    family: { name: string; relation: string; room_no: string | null }[];
};

export type OtherPassenger = RoomPassenger & {
    // Aynı tarihlerde başka otelde kalıyorsa otelin adı.
    elsewhere: string | null;
};

export type PlanStay = {
    id: string;
    hotel_name: string;
    city_label: string;
    check_in: string;
    check_out: string;
    nights: number;
    groups: string[];
    tour: { id: string; name: string };
    // Binanın kat sayısı (otel geneli) ve bu konaklamada bize verilen katlar.
    floors_count: number | null;
    used_floors: number[];
};

export type PlanStats = {
    rooms: number;
    beds: number;
    occupied: number;
    unassigned: number;
};

export type PlanOptions = {
    kinds: Option<RoomKind>[];
    roomTypes: Option[];
};

export type AutoAssignPreview = {
    placements: { room_no: string; kind: string; names: string[] }[];
    placed: number;
    unplaced: { name: string; reason: string }[];
};

export const roomKindVariant: Record<
    RoomKind,
    'default' | 'secondary' | 'outline'
> = {
    erkek: 'secondary',
    kadin: 'outline',
    aile: 'default',
};
