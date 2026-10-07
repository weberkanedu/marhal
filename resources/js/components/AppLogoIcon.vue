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
    <!--
        Marhal işareti "Tavaf" (kullanıcı seçimi 2026-10-07): merkezde kare, çevresinde düzenle dönen yedi nokta —
        her yolcu yerinde. Noktalar saat yönünün tersine küçülerek akar (tavaf yönü). Rengi bulunduğu yerin metin
        rengini alır; kare istenirse --logo-accent ile ayrı renklenir. Favicon: public/favicon.svg (scripts/make-icons.php).
    -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 48 48"
        :class="className"
        v-bind="$attrs"
    >
        <path
            :style="{ fill: 'var(--logo-accent, currentColor)' }"
            fill-rule="evenodd"
            d="M17 17h14v14H17ZM17 20.6v1.8h14v-1.8Z"
        />
        <g fill="currentColor">
            <circle cx="24" cy="5.5" r="3" />
            <circle cx="37.1" cy="10.9" r="2.75" />
            <circle cx="42.5" cy="24" r="2.5" />
            <circle cx="37.1" cy="37.1" r="2.25" />
            <circle cx="24" cy="42.5" r="2" />
            <circle cx="10.9" cy="37.1" r="1.75" />
            <circle cx="5.5" cy="24" r="1.5" />
        </g>
    </svg>
</template>
