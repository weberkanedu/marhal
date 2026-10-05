<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
</script>

<template>
    <div
        class="app-logo-mark flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5" />
    </div>
    <div class="ml-1 grid flex-1 text-left">
        <span
            class="truncate font-heading text-base leading-tight font-semibold"
        >
            {{ page.props.name }}
        </span>
        <span
            v-if="page.props.tenant"
            class="truncate text-xs leading-tight opacity-70"
        >
            {{ page.props.tenant.name }}
        </span>
    </div>
</template>
