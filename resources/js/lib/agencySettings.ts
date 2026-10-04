import { Building2, BusFront, Hotel, ScrollText, UserCog } from '@lucide/vue';
import { edit as agencyEdit } from '@/routes/agency';
import { index as auditIndex } from '@/routes/audit';
import { index as hotelsIndex } from '@/routes/hotels';
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
    'audit/',
];
