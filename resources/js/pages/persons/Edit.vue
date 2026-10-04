<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/Heading.vue';
import PersonForm from '@/components/persons/PersonForm.vue';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/persons';
import type { PersonDetail, PersonFormOptions } from '@/types/person';

const props = defineProps<{
    person: PersonDetail;
    options: PersonFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Yolcular', href: index() }],
    },
});
</script>

<template>
    <Head :title="`${props.person.full_name} — Düzenle`" />

    <div class="mx-auto w-full max-w-4xl p-4">
        <Heading
            :title="props.person.full_name"
            description="Bilgileri düzenle"
        />

        <PersonForm
            :form="PersonController.update.form(props.person.id)"
            :options="options"
            :person="props.person"
            submit-label="Değişiklikleri kaydet"
        >
            <template #actions>
                <Button variant="ghost" as-child>
                    <Link :href="show(props.person.id)">Vazgeç</Link>
                </Button>
            </template>
        </PersonForm>
    </div>
</template>
