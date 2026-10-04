<script setup lang="ts">
import {
    CalendarCheck,
    FileSpreadsheet,
    ShieldCheck,
    Users,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const highlights = [
    { icon: Users, text: 'Yolcu, tur ve grup yönetimi tek yerde' },
    { icon: CalendarCheck, text: 'Taksit, tahsilat ve gecikme takibi' },
    { icon: FileSpreadsheet, text: 'Tek tıkla Excel ve PDF listeler' },
    { icon: ShieldCheck, text: 'Kimlik bilgileri şifreli, erişimler kayıtlı' },
];
</script>

<template>
    <div class="grid min-h-svh bg-background lg:grid-cols-[1fr_1.1fr]">
        <!-- Marka paneli (geniş ekranda) -->
        <aside
            class="relative hidden flex-col justify-between bg-sidebar p-10 text-sidebar-foreground lg:flex"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground"
                >
                    <AppLogoIcon class="size-6" />
                </div>
                <span class="font-heading text-2xl font-semibold">Marhal</span>
            </div>

            <div class="max-w-md space-y-8">
                <p class="font-heading text-3xl leading-snug font-semibold">
                    Umre ve Hac organizasyonlarınız, kayıttan dönüşe kadar tek
                    ekranda.
                </p>
                <ul class="space-y-3 text-sm">
                    <li
                        v-for="item in highlights"
                        :key="item.text"
                        class="flex items-center gap-3"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 text-sidebar-primary"
                        />
                        {{ item.text }}
                    </li>
                </ul>
            </div>

            <p class="text-xs opacity-60">
                © {{ new Date().getFullYear() }} Marhal
            </p>
        </aside>

        <!-- Form -->
        <main class="flex items-center justify-center p-6 md:p-10">
            <div class="w-full max-w-sm space-y-8">
                <div class="flex items-center gap-3 lg:hidden">
                    <div
                        class="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-5" />
                    </div>
                    <span class="font-heading text-xl font-semibold"
                        >Marhal</span
                    >
                </div>
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ description }}
                    </p>
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
