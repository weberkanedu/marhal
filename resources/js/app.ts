import { createInertiaApp } from '@inertiajs/vue3';
import { initializeCardGlow } from '@/composables/useTheme';
import AppLayout from '@/layouts/AppLayout.vue';
import AgencyLayout from '@/layouts/agency/Layout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { agencySettingsPages } from '@/lib/agencySettings';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Marhal';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case agencySettingsPages.some((prefix) => name.startsWith(prefix)):
                return [AppLayout, AgencyLayout];
            default:
                return AppLayout;
        }
    },
    withApp: (app) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });
    },
    progress: {
        color: '#c99f30',
    },
});

// Gece Zümrüdü: kart kenarı fareyi izleyerek parlar.
initializeCardGlow();

// This will listen for flash toast data from the server...
initializeFlashToast();
