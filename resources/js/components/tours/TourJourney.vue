<script setup lang="ts">
import {
    Building2,
    ClipboardCheck,
    Home,
    PlaneLanding,
    PlaneTakeoff,
} from '@lucide/vue';
import { computed } from 'vue';
import { formatDate } from '@/lib/format';
import type { JourneyStep } from '@/types/tour';

/**
 * Turun yolculuk çizelgesi (sunucu: App\Support\Tours\TourJourney). Bugünkü adım nabız gibi atar.
 */
const props = defineProps<{
    steps: JourneyStep[];
}>();

const icons = {
    prep: ClipboardCheck,
    outbound: PlaneTakeoff,
    stay: Building2,
    return: PlaneLanding,
};

// Çizginin dolu kısmı: geçilen adımlar + bugünkü adıma kadar.
const progress = computed(() => {
    const last = props.steps.length - 1;
    const now = props.steps.findIndex((s) => s.state === 'now');
    const reached =
        now === -1
            ? props.steps.every((s) => s.state === 'done')
                ? last
                : 0
            : now;

    return last > 0 ? reached / last : 0;
});

function daysUntil(date: string): number {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return Math.round(
        (new Date(`${date}T00:00:00`).getTime() - today.getTime()) / 86_400_000,
    );
}

function subtitle(step: JourneyStep): string {
    if (step.kind === 'prep') {
        const next = props.steps[1];
        const left = next ? daysUntil(next.start) : 0;

        return step.state === 'done'
            ? 'Tamamlandı'
            : left > 0
              ? `${left} gün kaldı`
              : 'Bugün yola çıkılıyor';
    }

    const range =
        step.start === step.end
            ? formatDate(step.start)
            : `${formatDate(step.start)} – ${formatDate(step.end)}`;

    return range;
}
</script>

<template>
    <div
        class="journey"
        :style="{ '--steps': steps.length, '--prog': progress }"
        aria-label="Yolculuk çizelgesi"
    >
        <div
            v-for="step in steps"
            :key="step.key"
            class="st"
            :class="step.state"
            :aria-current="step.state === 'now' ? 'step' : undefined"
        >
            <div class="dot">
                <component
                    :is="
                        step.kind === 'return' && step.state === 'done'
                            ? Home
                            : icons[step.kind]
                    "
                    class="size-[18px]"
                />
            </div>
            <div class="flex min-w-0 flex-col gap-0.5">
                <b class="text-[13px]">{{ step.title }}</b>
                <small class="text-[11px] leading-snug text-muted-foreground">
                    {{ subtitle(step) }}
                    <template v-if="step.detail">
                        <br />{{ step.detail }}
                    </template>
                </small>
            </div>
        </div>
    </div>
</template>
