/** Format angka, persen, dan tanggal gaya Indonesia. */

export function angka(n: number | null | undefined, d = 2): string {
    if (n === null || n === undefined || Number.isNaN(n)) return '—';
    return Number(n).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
}

export function bulat(n: number | null | undefined): string {
    if (n === null || n === undefined || Number.isNaN(n)) return '—';
    return Number(n).toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

/** Rasio 0..1 menjadi persen. */
export function persen(r: number | null | undefined, d = 1): string {
    if (r === null || r === undefined || Number.isNaN(r)) return '—';
    return `${(Number(r) * 100).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d })}%`;
}

export function lebar(r: number | null | undefined): string {
    const v = Math.max(0, Math.min(1, Number(r ?? 0)));
    return `${(v * 100).toFixed(1)}%`;
}

export function tanggal(iso: string | null | undefined): string {
    if (!iso) return '—';
    const t = new Date(`${String(iso).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(t.getTime())) return String(iso);
    return t.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

export function tanggalPanjang(iso: string | null | undefined): string {
    if (!iso) return '—';
    const t = new Date(`${String(iso).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(t.getTime())) return String(iso);
    return t.toLocaleDateString('id-ID', { day: '2-digit', month: 'long', year: 'numeric' });
}

export function selisih(n: number | null | undefined, d = 3): string {
    if (n === null || n === undefined || Number.isNaN(n)) return '—';
    return (n >= 0 ? '+' : '−') + Math.abs(n).toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
}

/** Ukuran berkas ringkas: "820 KB", "3,4 MB". */
export function ukuranBerkas(n: number): string {
    return n >= 1024 * 1024
        ? `${(n / 1024 / 1024).toLocaleString('id-ID', { maximumFractionDigits: 1 })} MB`
        : `${Math.max(1, Math.round(n / 1024))} KB`;
}

const SATUAN = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

/** Bilangan bulat dalam kata: 1250 → "Seribu Dua Ratus Lima Puluh". */
function kata(n: number): string {
    if (n < 12) return SATUAN[n];
    if (n < 20) return `${SATUAN[n - 10]} Belas`;
    if (n < 100) return `${SATUAN[Math.floor(n / 10)]} Puluh ${kata(n % 10)}`.trim();
    if (n < 200) return `Seratus ${kata(n - 100)}`.trim();
    if (n < 1000) return `${SATUAN[Math.floor(n / 100)]} Ratus ${kata(n % 100)}`.trim();
    if (n < 2000) return `Seribu ${kata(n - 1000)}`.trim();
    if (n < 1e6) return `${kata(Math.floor(n / 1000))} Ribu ${kata(n % 1000)}`.trim();
    return `${kata(Math.floor(n / 1e6))} Juta ${kata(n % 1e6)}`.trim();
}

/** Angka berdesimal dalam kata, desimal dibaca per digit: 30,8 → "Tiga Puluh Koma Delapan". */
export function terbilang(n: number, desimal = 2): string {
    const [bulat, pecahan = ''] = Math.abs(n).toFixed(desimal).replace(/0+$/, '').replace(/\.$/, '').split('.');
    const depan = Number(bulat) === 0 ? 'Nol' : kata(Number(bulat));
    const DIGIT = ['Nol', ...SATUAN.slice(1, 10)];
    return pecahan ? `${depan} Koma ${[...pecahan].map((d) => DIGIT[Number(d)]).join(' ')}` : depan;
}
