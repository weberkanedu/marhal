export type ReadinessCheck = {
    // rooms | seats | flights
    key: string;
    label: string;
    done: number;
    total: number;
    tab: string;
};

export type DashboardTour = {
    id: string;
    name: string;
    status_label: string;
    start_date: string;
    end_date: string;
    days_left: number;
    capacity: number | null;
    // Ödeme modülü kapalıysa null.
    collection: { currency: string; paid: string; total: string } | null;
    registered: number;
    pending: number;
    ungrouped: number;
    passport_issues: number;
    checks: ReadinessCheck[];
    // Ravza maddesi takip ediliyorsa randevu bekleyenler (cinsiyete göre).
    ravza_waiting?: { men: number; women: number } | null;
};

export type DashboardPayments = {
    overdue_count: number;
    overdue: Record<string, string>;
    due_soon_count: number;
};

export type ActivityItem = {
    id: number;
    text: string;
    user: string | null;
    at: string;
    tone: 'neutral' | 'success' | 'warning' | 'danger';
};

export const percent = (done: number, total: number): number =>
    total <= 0 ? 100 : Math.round((done / total) * 100);
