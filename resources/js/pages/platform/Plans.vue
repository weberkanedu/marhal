<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import PlanController from '@/actions/App/Http/Controllers/Platform/PlanController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { selectClass } from '@/lib/formClasses';
import { index } from '@/routes/platform/plans';

type PlanRow = {
    id: string;
    name: string;
    price_monthly: string;
    price_yearly: string;
    currency: string;
    user_limit: number | null;
    active_tour_limit: number | null;
    tenants_count: number;
    features: string[];
};

defineProps<{
    plans: PlanRow[];
    features: { key: string; label: string }[];
    currencies: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Paketler', href: index() }],
    },
});
</script>

<template>
    <Head title="Paketler" />

    <div class="flex flex-col gap-4 p-4">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Paketler</h1>
            <p class="text-sm text-muted-foreground">
                Değişiklik, o paketi kullanan bütün acentelere hemen yansır.
                Sınırı boş bırakmak "sınırsız" demektir.
            </p>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <Card v-for="plan in plans" :key="plan.id">
                <CardHeader>
                    <CardTitle>{{ plan.name }}</CardTitle>
                    <CardDescription>
                        {{ plan.tenants_count }} acente bu paketi kullanıyor
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="PlanController.update.form(plan.id)"
                        class="space-y-4"
                        :options="{ preserveScroll: true }"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label :for="`name-${plan.id}`">Paket adı</Label>
                            <Input
                                :id="`name-${plan.id}`"
                                name="name"
                                :default-value="plan.name"
                                required
                            />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid grid-cols-[1fr_1fr_5.5rem] gap-2">
                            <div class="grid gap-2">
                                <Label :for="`pm-${plan.id}`">Aylık</Label>
                                <Input
                                    :id="`pm-${plan.id}`"
                                    name="price_monthly"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :default-value="plan.price_monthly"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`py-${plan.id}`">Yıllık</Label>
                                <Input
                                    :id="`py-${plan.id}`"
                                    name="price_yearly"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :default-value="plan.price_yearly"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`cur-${plan.id}`">Birim</Label>
                                <select
                                    :id="`cur-${plan.id}`"
                                    name="currency"
                                    :class="selectClass"
                                >
                                    <option
                                        v-for="c in currencies"
                                        :key="c"
                                        :value="c"
                                        :selected="plan.currency === c"
                                    >
                                        {{ c }}
                                    </option>
                                </select>
                            </div>
                            <InputError
                                class="col-span-3"
                                :message="
                                    errors.price_monthly ?? errors.price_yearly
                                "
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="grid gap-2">
                                <Label :for="`ul-${plan.id}`"
                                    >Kullanıcı sınırı</Label
                                >
                                <Input
                                    :id="`ul-${plan.id}`"
                                    name="user_limit"
                                    type="number"
                                    min="1"
                                    placeholder="Sınırsız"
                                    :default-value="
                                        plan.user_limit ?? undefined
                                    "
                                />
                                <InputError :message="errors.user_limit" />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`tl-${plan.id}`"
                                    >Aktif tur sınırı</Label
                                >
                                <Input
                                    :id="`tl-${plan.id}`"
                                    name="active_tour_limit"
                                    type="number"
                                    min="1"
                                    placeholder="Sınırsız"
                                    :default-value="
                                        plan.active_tour_limit ?? undefined
                                    "
                                />
                                <InputError
                                    :message="errors.active_tour_limit"
                                />
                            </div>
                        </div>
                        <fieldset class="grid gap-1.5">
                            <legend class="mb-1 text-sm font-medium">
                                Modüller
                            </legend>
                            <label
                                v-for="feature in features"
                                :key="feature.key"
                                class="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    name="features[]"
                                    :value="feature.key"
                                    :checked="
                                        plan.features.includes(feature.key)
                                    "
                                />
                                {{ feature.label }}
                            </label>
                        </fieldset>
                        <Button type="submit" :disabled="processing"
                            >Kaydet</Button
                        >
                    </Form>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
