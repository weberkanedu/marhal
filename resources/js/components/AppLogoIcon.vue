<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Marhal işareti: sivri kemer (mihrap / kapı) — rengi bulunduğu yerin metin rengini alır -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            fill-rule="evenodd"
            d="M5 29V14.5C5 8.6 9.6 4.3 16 1.5c6.4 2.8 11 7.1 11 13V29H5Zm6.5 0V16.6c0-3.3 1.8-5.8 4.5-7.3 2.7 1.5 4.5 4 4.5 7.3V29h-9Z"
        />
        <rect x="3" y="28" width="26" height="2.5" rx="1" fill="currentColor" />
    </svg>
</template>
