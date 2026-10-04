<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TourController from '@/actions/App/Http/Controllers/TourController';
import Heading from '@/components/Heading.vue';
import TourForm from '@/components/tours/TourForm.vue';
import { Button } from '@/components/ui/button';
import { create, index } from '@/routes/tours';
import type { TourFormOptions } from '@/types/tour';

defineProps<{
    options: TourFormOptions;
    defaults: { currency: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Turlar', href: index() },
            { title: 'Yeni tur', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Yeni tur" />

    <div class="mx-auto w-full max-w-3xl p-4">
        <Heading
            title="Yeni tur"
            description="Tur oluşturulunca içinde bir 'A Grubu' hazır gelir; ihtiyaca göre grup ekleyebilirsiniz."
        />

        <TourForm
            :form="TourController.store.form()"
            :options="options"
            :default-currency="defaults.currency"
            submit-label="Turu oluştur"
        >
            <template #actions>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Vazgeç</Link>
                </Button>
            </template>
        </TourForm>
    </div>
</template>
