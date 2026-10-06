import { usePage } from '@inertiajs/vue3';
import type { FeatureKey } from '@/types/tenant';

/**
 * Acentenin paketinde açık modüller (sunucu `features` olarak paylaşır; rotalar sunucuda ayrıca korunur).
 */
export function useFeatures(): { has: (feature: FeatureKey) => boolean } {
    const page = usePage();

    return {
        has: (feature) => (page.props.features ?? []).includes(feature),
    };
}
