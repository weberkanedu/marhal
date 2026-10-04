<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TourController from '@/actions/App/Http/Controllers/TourController';
import Heading from '@/components/Heading.vue';
import TourForm from '@/components/tours/TourForm.vue';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/tours';
import type { TourFormOptions, TourSummary } from '@/types/tour';

const props = defineProps<{
    tour: TourSummary;
    options: TourFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Turlar', href: index() }],
    },
});
</script>

<template>
    <Head :title="`${props.tour.name} — Düzenle`" />

    <div class="mx-auto w-full max-w-3xl p-4">
        <Heading
            :title="props.tour.name"
            description="Tur bilgilerini düzenle"
        />

        <TourForm
            :form="TourController.update.form(props.tour.id)"
            :options="options"
            :tour="props.tour"
            submit-label="Değişiklikleri kaydet"
        >
            <template #actions>
                <Button variant="ghost" as-child>
                    <Link :href="show(props.tour.id)">Vazgeç</Link>
                </Button>
            </template>
        </TourForm>
    </div>
</template>
