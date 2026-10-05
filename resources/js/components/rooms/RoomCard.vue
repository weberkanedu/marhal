<script setup lang="ts">
import { AlertTriangle, ArrowUpDown, Pencil, X } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { roomKindVariant } from '@/types/room';
import type { PlanRoom, RoomOccupant } from '@/types/room';

const props = defineProps<{
    room: PlanRoom;
    kindLabel: string;
    canUpdate: boolean;
    // Seçili yolcu varsa bu oda hedef olarak gösterilir.
    selecting: boolean;
    selectedId: string | null;
}>();

const emit = defineEmits<{
    place: [room: PlanRoom];
    pick: [occupant: RoomOccupant];
    remove: [occupant: RoomOccupant];
    edit: [room: PlanRoom];
}>();

const free = computed(() => props.room.capacity - props.room.occupants.length);
const isTarget = computed(
    () =>
        props.selecting &&
        free.value > 0 &&
        !props.room.occupants.some(
            (o) => o.registration_id === props.selectedId,
        ),
);
const genderShort = (gender: string | null) =>
    gender === 'erkek' ? 'E' : gender === 'kadin' ? 'K' : '·';
</script>

<template>
    <div
        class="flex flex-col rounded-lg border bg-card text-sm shadow-xs transition"
        :class="{
            'cursor-pointer border-primary ring-2 ring-primary/30': isTarget,
            'opacity-60': selecting && !isTarget,
        }"
        :role="isTarget ? 'button' : undefined"
        :tabindex="isTarget ? 0 : undefined"
        :aria-label="
            isTarget ? `${room.room_no} numaralı odaya yerleştir` : undefined
        "
        @click="isTarget && emit('place', room)"
        @keydown.enter="isTarget && emit('place', room)"
    >
        <div class="flex items-center justify-between gap-2 border-b px-3 py-2">
            <div class="flex items-center gap-2">
                <span class="text-base font-semibold">{{ room.room_no }}</span>
                <span v-if="room.floor" class="text-xs text-muted-foreground">
                    {{ room.floor }}. kat
                </span>
                <Badge :variant="roomKindVariant[room.kind]">
                    {{ kindLabel }}
                </Badge>
                <span
                    v-if="room.near_elevator"
                    class="flex items-center gap-0.5 text-[11px] text-muted-foreground"
                    title="Asansöre yakın"
                >
                    <ArrowUpDown class="size-3" /> Asansör
                </span>
            </div>
            <div class="flex items-center gap-1">
                <span
                    class="text-xs"
                    :class="
                        free === 0 ? 'text-muted-foreground' : 'text-success'
                    "
                >
                    {{ room.occupants.length }}/{{ room.capacity }}
                </span>
                <button
                    v-if="canUpdate"
                    type="button"
                    class="rounded p-1 text-muted-foreground hover:bg-muted"
                    title="Odayı düzenle"
                    @click.stop="emit('edit', room)"
                >
                    <Pencil class="size-3.5" />
                </button>
            </div>
        </div>

        <ul class="flex flex-col gap-0.5 p-2">
            <li
                v-for="occupant in room.occupants"
                :key="occupant.assignment_id"
                class="group/occ flex items-start gap-2 rounded-md px-1.5 py-1"
                :class="{
                    'bg-primary/10': occupant.registration_id === selectedId,
                }"
            >
                <span
                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-[10px] font-semibold"
                    :class="
                        occupant.gender === 'kadin'
                            ? 'bg-women/20 text-women'
                            : occupant.gender === 'erkek'
                              ? 'bg-men/20 text-men'
                              : 'bg-muted'
                    "
                    :title="occupant.gender === 'erkek' ? 'Erkek' : 'Kadın'"
                >
                    {{ genderShort(occupant.gender) }}
                </span>
                <button
                    type="button"
                    class="min-w-0 flex-1 text-left"
                    :disabled="!canUpdate || occupant.registration_id === null"
                    :title="
                        canUpdate ? 'Başka odaya taşımak için seçin' : undefined
                    "
                    @click.stop="emit('pick', occupant)"
                >
                    <div class="truncate">{{ occupant.full_name }}</div>
                    <div
                        v-if="occupant.group_name || occupant.room_type_label"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            [occupant.group_name, occupant.room_type_label]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </div>
                    <div
                        v-if="occupant.needs?.length"
                        class="mt-0.5 flex flex-wrap gap-1"
                    >
                        <span
                            v-for="need in occupant.needs"
                            :key="need"
                            class="rounded-full bg-accent px-1.5 text-[10.5px] font-semibold text-accent-foreground"
                            >{{ need }}</span
                        >
                    </div>
                    <div
                        v-for="warning in occupant.warnings"
                        :key="warning"
                        class="flex items-center gap-1 text-xs text-warning"
                    >
                        <AlertTriangle class="size-3 shrink-0" /> {{ warning }}
                    </div>
                </button>
                <button
                    v-if="canUpdate && occupant.registration_id !== null"
                    type="button"
                    class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-destructive"
                    title="Odadan çıkar"
                    @click.stop="emit('remove', occupant)"
                >
                    <X class="size-3.5" />
                </button>
            </li>
            <li
                v-for="n in free"
                :key="`free-${n}`"
                class="rounded-md border border-dashed px-2 py-1 text-xs text-muted-foreground"
            >
                Boş yatak
            </li>
        </ul>
        <p
            v-if="room.notes"
            class="border-t px-3 py-1.5 text-xs text-muted-foreground"
        >
            {{ room.notes }}
        </p>
    </div>
</template>
