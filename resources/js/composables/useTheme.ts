import type { ColorTheme } from '@/types';

/**
 * Tema <html data-theme> üzerinde durur; koyu / açık karakter temanın kendisinden gelir
 * (Gece Zümrüdü koyu, Şafak açık). Sunucu ilk çizimde doğru temayı basar (HandleAppearance).
 */
const darkThemes: ColorTheme[] = ['zumrut'];

const backgrounds: Record<ColorTheme, string> = {
    zumrut: '#0b1b16',
    safak: '#eef3f1',
};

export function isDarkTheme(): boolean {
    return (
        typeof document !== 'undefined' &&
        document.documentElement.classList.contains('dark')
    );
}

/** Seçilen temayı sayfa yenilenmeden uygular (kaydetme sunucuda). */
export function applyTheme(theme: ColorTheme): void {
    const root = document.documentElement;
    root.dataset.theme = theme;
    root.classList.toggle('dark', darkThemes.includes(theme));
    root.style.backgroundColor = backgrounds[theme];
    document
        .querySelector('meta[name="color-scheme"]')
        ?.setAttribute(
            'content',
            darkThemes.includes(theme) ? 'dark' : 'light',
        );
}

/**
 * Kart kenarındaki ışık fareyi izler: imlecin karta göre konumu --cx / --cy olarak yazılır,
 * CSS (yalnız Gece Zümrüdü'nde) bu konumda parlak bir kenar çizer. Tek dinleyici, kare başına bir güncelleme.
 */
export function initializeCardGlow(): void {
    if (
        typeof window === 'undefined' ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    let active: HTMLElement | null = null;
    let frame = 0;
    let last: PointerEvent | null = null;

    const update = () => {
        frame = 0;

        if (!last) {
            return;
        }

        const target = last.target instanceof Element ? last.target : null;
        const card =
            target?.closest<HTMLElement>('[data-slot="card"], .mx .card') ??
            null;

        if (active && active !== card) {
            active.style.removeProperty('--cx');
            active.style.removeProperty('--cy');
        }

        active = card;

        if (card) {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--cx', `${last.clientX - rect.left}px`);
            card.style.setProperty('--cy', `${last.clientY - rect.top}px`);
        }
    };

    document.addEventListener(
        'pointermove',
        (event) => {
            if (event.pointerType !== 'mouse') {
                return;
            }

            last = event;
            frame ||= requestAnimationFrame(update);
        },
        { passive: true },
    );
}
