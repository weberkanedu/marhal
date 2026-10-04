<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/Heading.vue';
import PersonForm from '@/components/persons/PersonForm.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/persons';
import type { PersonFormOptions } from '@/types/person';

defineProps<{
    options: PersonFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Yolcular', href: index() },
            { title: 'Yeni yolcu', href: PersonController.create() },
        ],
    },
});
</script>

<template>
    <Head title="Yeni yolcu" />

    <div class="mx-auto w-full max-w-4xl p-4">
        <Heading
            title="Yeni yolcu"
            description="Kişi bilgileri turdan bağımsızdır; tura kayıt ayrıca yapılır."
        />

        <PersonForm
            :form="PersonController.store.form()"
            :options="options"
            submit-label="Kaydet"
        >
            <template #actions>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Vazgeç</Link>
                </Button>
            </template>
        </PersonForm>
    </div>
</template>
