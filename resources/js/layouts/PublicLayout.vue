<script setup lang="ts">
import AppBackdrop from '@/components/AppBackdrop.vue';

/**
 * Giriş gerektirmeyen, linkle açılan sayfalar (aile ekranı, ön kayıt): menü yok, sadece zemin.
 */
</script>

<template>
    <AppBackdrop />
    <slot />
</template>
