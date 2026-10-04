<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { KeyRound, Pencil, UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/UserController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';
import { selectClass } from '@/lib/formClasses';
import { index } from '@/routes/users';

type Role = { value: string; label: string; description: string };
type UserRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    must_change_password: boolean;
    last_login_at: string | null;
    is_me: boolean;
};

const props = defineProps<{
    users: UserRow[];
    limit: { active: number; max: number | null };
    roles: Role[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Acente ayarları', href: index() },
            { title: 'Personel', href: index() },
        ],
    },
});

const roleLabel = (value: string) =>
    props.roles.find((r) => r.value === value)?.label ?? value;

const createOpen = ref(false);
const editOpen = ref(false);
const editing = ref<UserRow | null>(null);
const newRole = ref('operasyon');

function openEdit(user: UserRow): void {
    editing.value = user;
    editOpen.value = true;
}

function resetPassword(user: UserRow): void {
    if (
        confirm(
            `${user.name} için yeni geçici şifre oluşturulsun mu? Açık oturumları kapatılır.`,
        )
    ) {
        router.post(
            UserController.resetPassword.url(user.id),
            {},
            { preserveScroll: true },
        );
    }
}

const limitReached = () =>
    props.limit.max !== null && props.limit.active >= props.limit.max;
</script>

<template>
    <Head title="Personel" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Personel</h2>
                <p class="text-sm text-muted-foreground">
                    Aktif kullanıcı: {{ limit.active }}
                    <template v-if="limit.max !== null">
                        / {{ limit.max }} (paket sınırı)
                    </template>
                </p>
            </div>
            <Button :disabled="limitReached()" @click="createOpen = true">
                <UserPlus /> Kullanıcı ekle
            </Button>
        </div>

        <p
            v-if="limitReached()"
            class="rounded-md border border-warning/40 bg-warning-soft p-3 text-sm text-warning"
        >
            Paketinizdeki kullanıcı sınırına ulaştınız. Yeni kullanıcı için
            kullanmadığınız bir hesabı pasif yapın veya paketinizi yükseltin.
        </p>

        <Card class="py-0">
            <CardContent class="overflow-x-auto p-0">
                <table class="w-full text-sm whitespace-nowrap">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Ad Soyad</th>
                            <th class="px-4 py-2 font-medium">E-posta</th>
                            <th class="px-4 py-2 font-medium">Rol</th>
                            <th class="px-4 py-2 font-medium">Durum</th>
                            <th class="px-4 py-2 font-medium">Son giriş</th>
                            <th class="w-0 px-2 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="border-t"
                            :class="{ 'opacity-50': !user.is_active }"
                        >
                            <td class="px-4 py-2 font-medium">
                                {{ user.name }}
                                <span
                                    v-if="user.is_me"
                                    class="text-xs text-muted-foreground"
                                >
                                    (siz)
                                </span>
                            </td>
                            <td class="px-4 py-2">{{ user.email }}</td>
                            <td class="px-4 py-2">
                                <Badge variant="secondary">
                                    {{ roleLabel(user.role) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-2">
                                <Badge v-if="!user.is_active" variant="outline">
                                    Pasif
                                </Badge>
                                <Badge
                                    v-else-if="user.must_change_password"
                                    variant="outline"
                                    class="text-warning"
                                >
                                    İlk giriş bekleniyor
                                </Badge>
                                <span v-else class="text-success">Aktif</span>
                            </td>
                            <td class="px-4 py-2 text-muted-foreground">
                                {{
                                    user.last_login_at
                                        ? formatDate(user.last_login_at)
                                        : '—'
                                }}
                            </td>
                            <td class="px-2 py-2">
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Düzenle"
                                    @click="openEdit(user)"
                                >
                                    <Pencil />
                                </Button>
                                <Button
                                    v-if="!user.is_me && user.is_active"
                                    variant="ghost"
                                    size="icon-sm"
                                    title="Yeni geçici şifre"
                                    @click="resetPassword(user)"
                                >
                                    <KeyRound />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>
    </div>

    <!-- Yeni kullanıcı -->
    <Dialog v-model:open="createOpen">
        <DialogContent>
            <Form
                v-bind="UserController.store.form()"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="createOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>Kullanıcı ekle</DialogTitle>
                    <DialogDescription>
                        Hesap geçici bir şifreyle oluşturulur; şifre bir sonraki
                        ekranda bir kez gösterilir.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="name">Ad Soyad *</Label>
                    <Input id="name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">E-posta *</Label>
                    <Input id="email" name="email" type="email" required />
                    <InputError :message="errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="role">Rol</Label>
                    <select
                        id="role"
                        v-model="newRole"
                        name="role"
                        :class="selectClass"
                    >
                        <option
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>
                    <p class="text-xs text-muted-foreground">
                        {{
                            roles.find((r) => r.value === newRole)?.description
                        }}
                    </p>
                    <InputError :message="errors.role" />
                </div>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="createOpen = false"
                    >
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing"
                        >Oluştur</Button
                    >
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>

    <!-- Düzenle -->
    <Dialog v-model:open="editOpen">
        <DialogContent v-if="editing">
            <Form
                v-bind="UserController.update.form(editing.id)"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="editOpen = false"
            >
                <DialogHeader>
                    <DialogTitle>{{ editing.name }}</DialogTitle>
                    <DialogDescription>{{ editing.email }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-2">
                    <Label for="edit-name">Ad Soyad</Label>
                    <Input
                        id="edit-name"
                        name="name"
                        :default-value="editing.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="edit-role">Rol</Label>
                    <select
                        id="edit-role"
                        name="role"
                        :class="selectClass"
                        :disabled="editing.is_me"
                    >
                        <option
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                            :selected="editing.role === role.value"
                        >
                            {{ role.label }}
                        </option>
                    </select>
                    <input
                        v-if="editing.is_me"
                        type="hidden"
                        name="role"
                        :value="editing.role"
                    />
                    <InputError :message="errors.role" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0" />
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        :checked="editing.is_active"
                        :disabled="editing.is_me"
                    />
                    Hesap aktif
                    <input
                        v-if="editing.is_me"
                        type="hidden"
                        name="is_active"
                        value="1"
                    />
                </label>
                <InputError :message="errors.is_active" />
                <p class="text-xs text-muted-foreground">
                    Pasif kullanıcı giriş yapamaz ve paket sınırına sayılmaz.
                    Geçmiş kayıtlar silinmez.
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="editOpen = false"
                    >
                        Vazgeç
                    </Button>
                    <Button type="submit" :disabled="processing">Kaydet</Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
