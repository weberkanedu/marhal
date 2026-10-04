export function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return new Date(date).toLocaleDateString('tr-TR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

export function formatMoney(amount: string | number, currency: string): string {
    return new Intl.NumberFormat('tr-TR', {
        style: 'currency',
        currency,
    }).format(Number(amount));
}

export function ageFrom(birthDate: string | null | undefined): number | null {
    if (!birthDate) {
        return null;
    }

    const birth = new Date(birthDate);
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    const monthDiff = now.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && now.getDate() < birth.getDate())) {
        age--;
    }

    return age;
}
