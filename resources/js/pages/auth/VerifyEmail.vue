<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'E-posta doğrulama',
        description:
            'Size gönderdiğimiz bağlantıya tıklayarak e-posta adresinizi doğrulayın.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="E-posta doğrulama" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        Kayıtlı e-posta adresinize yeni bir doğrulama bağlantısı gönderildi.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Doğrulama e-postasını yeniden gönder
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Çıkış yap
        </TextLink>
    </Form>
</template>
