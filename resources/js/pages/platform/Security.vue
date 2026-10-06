<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { toast } from 'vue-sonner';
import SecurityController from '@/actions/App/Http/Controllers/Platform/SecurityController';
import MockTop from '@/components/mock/MockTop.vue';
import { index } from '@/routes/platform/security';

type Settings = {
    single_session: boolean;
    device_limit: number;
    new_device_code: boolean;
    admin_two_factor: boolean;
    suspicious_alerts: boolean;
    device_limit_alert: boolean;
};

type Toggle = Exclude<keyof Settings, 'device_limit'>;

const props = defineProps<{ settings: Settings; mailReady: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Güvenlik', href: index() }],
    },
});

/**
 * Paketler tasarım sayfası → Platform paneli → Güvenlik: her değişiklik hemen kaydedilir.
 * "Yeni cihazda e-posta kodu" e-posta servisi bağlanana kadar açılamaz (10d-2).
 */
const form = reactive<Settings>({ ...props.settings });
watch(
    () => props.settings,
    (s) => Object.assign(form, s),
);

function save(): void {
    router.put(SecurityController.update.url(), form, {
        preserveScroll: true,
        onError: (errors) => {
            toast.error(Object.values(errors)[0] ?? 'Kaydedilemedi.');
            Object.assign(form, props.settings);
        },
    });
}

function toggle(key: Toggle): void {
    if (key === 'new_device_code' && !props.mailReady) {
        toast.error(
            'E-posta servisi bağlanınca açılabilir (şu an e-posta gönderilmiyor).',
        );

        return;
    }

    form[key] = !form[key];
    save();
}

function step(delta: number): void {
    const next = Math.min(10, Math.max(1, form.device_limit + delta));

    if (next !== form.device_limit) {
        form.device_limit = next;
        save();
    }
}
</script>

<template>
    <Head title="Güvenlik" />

    <div class="mx">
        <div class="main">
            <MockTop :crumbs="[{ label: 'Platform' }]" title="Güvenlik" />
            <p class="lbl">Bu kurallar bütün acentelere uygulanır.</p>

            <div class="sec">
                <div class="card">
                    <h4>Oturum</h4>
                    <div class="feat">
                        <span
                            >Kullanıcı başına aynı anda tek oturum<br /><span
                                class="lbl"
                                >Yeni girişte eski oturum kapanır, kullanıcıya
                                bildirim gider</span
                            ></span
                        >
                        <span
                            class="sw"
                            :class="{ on: form.single_session }"
                            role="switch"
                            tabindex="0"
                            :aria-checked="form.single_session"
                            aria-label="Tek oturum"
                            @click="toggle('single_session')"
                            @keydown.enter.prevent="toggle('single_session')"
                            @keydown.space.prevent="toggle('single_session')"
                        />
                    </div>
                    <div class="feat">
                        <span>Kullanıcı başına kayıtlı cihaz sınırı</span>
                        <span class="stepper">
                            <button
                                type="button"
                                aria-label="Azalt"
                                @click="step(-1)"
                            >
                                −</button
                            ><b>{{ form.device_limit }}</b
                            ><button
                                type="button"
                                aria-label="Artır"
                                @click="step(1)"
                            >
                                +
                            </button>
                        </span>
                    </div>
                    <div class="feat" :class="{ off: !mailReady }">
                        <span
                            >Yeni cihazda e-posta ile doğrulama kodu<template
                                v-if="!mailReady"
                                ><br /><span class="lbl"
                                    >E-posta servisi bağlanınca açılabilir</span
                                ></template
                            ></span
                        >
                        <span
                            class="sw"
                            :class="{ on: form.new_device_code }"
                            role="switch"
                            tabindex="0"
                            :aria-checked="form.new_device_code"
                            :aria-disabled="!mailReady"
                            aria-label="Yeni cihaz doğrulaması"
                            @click="toggle('new_device_code')"
                            @keydown.enter.prevent="toggle('new_device_code')"
                            @keydown.space.prevent="toggle('new_device_code')"
                        />
                    </div>
                    <div class="feat">
                        <span
                            >Yöneticiler için iki adımlı doğrulama zorunlu</span
                        >
                        <span
                            class="sw"
                            :class="{ on: form.admin_two_factor }"
                            role="switch"
                            tabindex="0"
                            :aria-checked="form.admin_two_factor"
                            aria-label="İki adımlı doğrulama"
                            @click="toggle('admin_two_factor')"
                            @keydown.enter.prevent="toggle('admin_two_factor')"
                            @keydown.space.prevent="toggle('admin_two_factor')"
                        />
                    </div>
                </div>

                <div class="card">
                    <h4>Şüpheli kullanım</h4>
                    <div class="feat">
                        <span
                            >Kısa sürede çok cihazdan ya da farklı ağlardan
                            giriş uyarısı<br /><span class="lbl"
                                >2 saat içinde 4 ve üstü cihaz ya da ağ. Şehir
                                tespiti sonraya bırakıldı.</span
                            ></span
                        >
                        <span
                            class="sw"
                            :class="{ on: form.suspicious_alerts }"
                            role="switch"
                            tabindex="0"
                            :aria-checked="form.suspicious_alerts"
                            aria-label="Şüpheli giriş uyarısı"
                            @click="toggle('suspicious_alerts')"
                            @keydown.enter.prevent="toggle('suspicious_alerts')"
                            @keydown.space.prevent="toggle('suspicious_alerts')"
                        />
                    </div>
                    <div class="feat">
                        <span
                            >Cihaz sınırı aşılınca acente yöneticisine
                            bildir</span
                        >
                        <span
                            class="sw"
                            :class="{ on: form.device_limit_alert }"
                            role="switch"
                            tabindex="0"
                            :aria-checked="form.device_limit_alert"
                            aria-label="Cihaz uyarısı"
                            @click="toggle('device_limit_alert')"
                            @keydown.enter.prevent="
                                toggle('device_limit_alert')
                            "
                            @keydown.space.prevent="
                                toggle('device_limit_alert')
                            "
                        />
                    </div>
                    <div class="feat">
                        <span
                            >Çıktılarda acente kimliği (vergi no, TÜRSAB
                            no)</span
                        >
                        <span class="chip ok">Her zaman açık</span>
                    </div>
                    <p class="lbl">
                        Önce uyarı, sonra doğrulama ister, en son kilitler.
                        Dürüst kullanıcı farkına bile varmaz.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
