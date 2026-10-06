import {
    Building2,
    BusFront,
    HeartPulse,
    ListChecks,
    Package,
    Hotel,
    Plane,
    ScrollText,
    UserCog,
} from '@lucide/vue';
import { index as aircraftTypesIndex } from '@/routes/aircraft-types';
import { edit as agencyEdit, plan as agencyPlan } from '@/routes/agency';
import { index as auditIndex } from '@/routes/audit';
import { index as hotelsIndex } from '@/routes/hotels';
import { index as needTypesIndex } from '@/routes/need-types';
import { index as readinessItemsIndex } from '@/routes/readiness-items';
import { index as usersIndex } from '@/routes/users';
import { index as vehicleTypesIndex } from '@/routes/vehicle-types';
import type { NavItem } from '@/types';

/**
 * "Acente ayarları" sekmeleri. Menüdeki "Acente ayarları" bağlantısı kullanıcının görebildiği
 * ilk sekmeye gider; yetkiler sunucuda da ayrıca korunur.
 */
export function agencySettingsTabs(
    role: string | undefined,
    features: string[],
): NavItem[] {
    const admin = role === 'admin';
    const staff = admin || role === 'operasyon';
    const tabs: NavItem[] = [];

    if (admin) {
        tabs.push({
            title: 'Acente bilgileri',
            href: agencyEdit(),
            icon: Building2,
        });
        tabs.push({ title: 'Personel', href: usersIndex(), icon: UserCog });
        tabs.push({ title: 'Paketim', href: agencyPlan(), icon: Package });
    }

    if (staff && features.includes('need_rules')) {
        tabs.push({
            title: 'İhtiyaç türleri',
            href: needTypesIndex(),
            icon: HeartPulse,
        });
    }

    if (staff && features.includes('readiness')) {
        tabs.push({
            title: 'Hazırlık maddeleri',
            href: readinessItemsIndex(),
            icon: ListChecks,
        });
    }

    if (staff && features.includes('room_planning')) {
        tabs.push({ title: 'Oteller', href: hotelsIndex(), icon: Hotel });
    }

    if (staff && features.includes('bus_planning')) {
        tabs.push({
            title: 'Araç tipleri',
            href: vehicleTypesIndex(),
            icon: BusFront,
        });
    }

    if (staff && features.includes('flight_seats')) {
        tabs.push({
            title: 'Uçak tipleri',
            href: aircraftTypesIndex(),
            icon: Plane,
        });
    }

    if (admin) {
        tabs.push({
            title: 'Erişim kayıtları',
            href: auditIndex(),
            icon: ScrollText,
        });
    }

    return tabs;
}

/**
 * Bu sayfa adları acente ayarları düzeniyle (sekmeli) açılır.
 */
export const agencySettingsPages = [
    'agency/',
    'users/',
    'hotels/',
    'vehicle-types/',
    'aircraft-types/',
    'need-types/',
    'readiness-items/',
    'audit/',
];
