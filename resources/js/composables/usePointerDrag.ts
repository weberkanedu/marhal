import { onBeforeUnmount, onMounted } from 'vue';
import type { Ref } from 'vue';

/**
 * Tasarımdaki sürükle-bırak (fare ve dokunmatik): [data-pid] öğesi tutulup 5 px oynatılınca adıyla
 * birlikte bir "hap" (.dragghost) imleci izler; [data-drop] üstünde .over sınıfı alır. Bırakınca
 * onDrop(pid, from, to) çağrılır: from = taşınan koltuk / yatak anahtarı (havuzdan geliyorsa null),
 * to = hedef anahtar ya da havuz için 'list'. Oynatmadan bırakılırsa onClick(pid, from).
 */
export function usePointerDrag(
    root: Ref<HTMLElement | null>,
    options: {
        label: (pid: string) => { initials: string; name: string } | null;
        onDrop: (pid: string, from: string | null, to: string) => void;
        onClick?: (pid: string, from: string | null) => void;
        // Sürüklerken bir kat / sekme üstünde beklenirse (ör. otel katı) çağrılır.
        onHover?: (element: HTMLElement) => void;
    },
): void {
    let drag: {
        pid: string;
        from: string | null;
        el: HTMLElement;
        x: number;
        y: number;
        ghost: HTMLElement | null;
        over: HTMLElement | null;
    } | null = null;

    const down = (e: PointerEvent) => {
        const src = (e.target as Element | null)?.closest<HTMLElement>(
            '[data-pid]',
        );

        if (
            !src ||
            e.button !== 0 ||
            !root.value?.contains(src) ||
            src.dataset.locked !== undefined
        ) {
            return;
        }

        drag = {
            pid: src.dataset.pid ?? '',
            from:
                src.dataset.from === 'slot' ? (src.dataset.key ?? null) : null,
            el: src,
            x: e.clientX,
            y: e.clientY,
            ghost: null,
            over: null,
        };
    };

    const move = (e: PointerEvent) => {
        if (!drag) {
            return;
        }

        if (!drag.ghost) {
            if (Math.hypot(e.clientX - drag.x, e.clientY - drag.y) < 5) {
                return;
            }

            const info = options.label(drag.pid);

            if (!info || !root.value) {
                drag = null;

                return;
            }

            const ghost = document.createElement('div');
            ghost.className = 'dragghost';
            const av = document.createElement('span');
            av.className = 'av';
            av.textContent = info.initials;
            ghost.append(av, document.createTextNode(info.name));
            root.value.appendChild(ghost);
            drag.ghost = ghost;
            drag.el.classList.add('dragging');
        }

        e.preventDefault();
        drag.ghost.style.left = `${e.clientX}px`;
        drag.ghost.style.top = `${e.clientY}px`;

        const target = document
            .elementFromPoint(e.clientX, e.clientY)
            ?.closest<HTMLElement>('[data-drop], [data-hover-drop]');

        if (target?.dataset.hoverDrop !== undefined && options.onHover) {
            options.onHover(target);
        }

        if (drag.over && drag.over !== target) {
            drag.over.classList.remove('over');
        }

        if (target?.dataset.drop && root.value?.contains(target)) {
            target.classList.add('over');
            drag.over = target;
        } else {
            drag.over = null;
        }
    };

    const up = () => {
        if (!drag) {
            return;
        }

        const d = drag;
        drag = null;

        if (!d.ghost) {
            options.onClick?.(d.pid, d.from);

            return;
        }

        d.ghost.remove();
        d.el.classList.remove('dragging');
        d.over?.classList.remove('over');

        const target = d.over;

        if (!target || !target.isConnected) {
            return;
        }

        if (target.dataset.drop === 'list') {
            if (d.from) {
                options.onDrop(d.pid, d.from, 'list');
            }

            return;
        }

        const key = target.dataset.key;

        if (key && key !== d.from) {
            options.onDrop(d.pid, d.from, key);
        }
    };

    const cancel = () => {
        drag?.ghost?.remove();
        drag?.el.classList.remove('dragging');
        drag = null;
    };

    onMounted(() => {
        root.value?.addEventListener('pointerdown', down);
        window.addEventListener('pointermove', move, { passive: false });
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', cancel);
    });

    onBeforeUnmount(() => {
        root.value?.removeEventListener('pointerdown', down);
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        window.removeEventListener('pointercancel', cancel);
    });
}
