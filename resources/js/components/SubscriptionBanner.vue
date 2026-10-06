<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { plan as agencyPlan } from '@/routes/agency';

/**
 * Deneme / gecikmede / salt okunur uyarı şeridi (sunucu: SubscriptionSummary::banner). Yönetici "Paketim"e gider.
 */
const page = usePage();
const banner = computed(() => page.props.subscription);
const isAdmin = computed(() => page.props.auth.user?.role === 'admin');
</script>

<template>
    <div v-if="banner" class="mx sub-banner">
        <div class="aitem" role="status">
            <span class="chip" :class="banner.tone">{{ banner.label }}</span>
            <p>{{ banner.message }}</p>
            <Link v-if="isAdmin" :href="agencyPlan()">Paketim →</Link>
        </div>
    </div>
</template>
