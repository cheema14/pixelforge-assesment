export function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    }).format(new Date(value));
}

export function timeAgo(value: string | null): string {
    if (!value) {
        return '';
    }

    const seconds = Math.round((Date.now() - new Date(value).getTime()) / 1000);

    if (seconds < 45) {
        return 'just now';
    }

    const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
        ['year', 60 * 60 * 24 * 365],
        ['month', 60 * 60 * 24 * 30],
        ['week', 60 * 60 * 24 * 7],
        ['day', 60 * 60 * 24],
        ['hour', 60 * 60],
        ['minute', 60],
    ];

    const formatter = new Intl.RelativeTimeFormat('en-US', { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (seconds >= size) {
            return formatter.format(-Math.floor(seconds / size), unit);
        }
    }

    return formatter.format(-seconds, 'second');
}

export function sizeLabel(size: string): string {
    const [width, height] = size.split('x');

    return `${width} × ${height}`;
}
