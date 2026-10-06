<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import TourController from '@/actions/App/Http/Controllers/TourController';
import Heading from '@/components/Heading.vue';
import TourForm from '@/components/tours/TourForm.vue';
import { Button } from '@/components/ui/button';
import { destroy, index, show } from '@/routes/tours';
import type { TourFormOptions, TourSummary } from '@/types/tour';

const props = defineProps<{
    tour: TourSummary;
    options: TourFormOptions;
    canDelete?: boolean;
}>();

function deleteTour(): void {
    if (confirm(`${props.tour.name} silinsin mi?`)) {
        router.delete(destroy.url(props.tour.id));
    }
}

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
                <Button
                    v-if="canDelete"
                    type="button"
                    variant="ghost"
                    class="ml-auto text-destructive"
                    @click="deleteTour"
                >
                    Turu sil
                </Button>
            </template>
        </TourForm>
    </div>
</template>
