import type { Option } from '@/types/person';

export type PaymentRow = {
    id: string;
    type: 'tahsilat' | 'iade';
    amount: string;
    currency: string;
    exchange_rate: string;
    amount_in_registration_currency: string;
    method: string;
    method_label: string;
    paid_at: string;
    reference: string | null;
    notes: string | null;
    received_by: string | null;
};

export type InstallmentRow = {
    id?: string;
    due_date: string;
    amount: string;
    notes: string | null;
};

export type RegistrationDetail = {
    id: string;
    status: string;
    status_label: string;
    room_type: string | null;
    currency: string;
    price: string;
    discount: string;
    net_price: string;
    paid: string;
    balance: string;
    due_total: string;
    overdue: string;
    group: string | null;
};

export type PaymentOptions = {
    methods: Option[];
    types: Option[];
    currencies: string[];
};
