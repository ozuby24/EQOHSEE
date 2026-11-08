import { ref, shallowRef } from 'vue';

/**
 * Permintaan ke endpoint Asisten AI (/asisten/*): JSON lewat fetch dengan
 * token XSRF dari cookie — sama dengan yang dipakai Inertia, tanpa axios.
 * Galat apa pun diubah menjadi pesan berbahasa Indonesia yang siap tampil.
 */

export class GalatAsisten extends Error {
    constructor(pesan: string, public status: number, public jenis: string | null) {
        super(pesan);
    }
}

function tokenXsrf(): string {
    const baris = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return baris ? decodeURIComponent(baris.slice('XSRF-TOKEN='.length)) : '';
}

/* Pesan cadangan bila server tidak mengirim pesan sendiri (mis. 404 bawaan, 504 Nginx). */
const UMUM: Record<number, string> = {
    401: 'Sesi Anda berakhir. Muat ulang halaman lalu masuk kembali.',
    403: 'Akun Anda tidak berhak memakai Asisten AI.',
    404: 'Data tidak ditemukan atau di luar jangkauan akun Anda.',
    419: 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    429: 'Terlalu banyak permintaan. Coba lagi nanti.',
    504: 'Asisten AI tidak menjawab dalam batas waktu. Coba lagi beberapa saat lagi.',
};

export async function mintaAsisten<T>(url: string, data: Record<string, unknown>, sinyal?: AbortSignal): Promise<T> {
    let res: Response;
    try {
        res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            signal: sinyal,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': tokenXsrf(),
            },
            body: JSON.stringify(data),
        });
    } catch (e) {
        if ((e as Error).name === 'AbortError') throw e;
        throw new GalatAsisten('Tidak dapat menghubungi server. Periksa sambungan internet lalu coba lagi.', 0, 'jaringan');
    }

    let isi: any = null;
    try {
        isi = await res.json();
    } catch {
        // bukan JSON — mis. halaman galat Nginx
    }

    if (res.ok && isi) return isi as T;

    const validasi = isi?.errors ? (Object.values(isi.errors as Record<string, string[]>)[0]?.[0] ?? null) : null;
    const pesan = isi?.pesan ?? validasi ?? UMUM[res.status] ?? 'Asisten AI sedang tidak dapat dipakai. Coba lagi beberapa saat lagi.';
    throw new GalatAsisten(pesan, res.status, isi?.jenis ?? null);
}

/** Salin ke papan klip; jatuh balik ke textarea sementara bila Clipboard API tidak tersedia. */
export async function salinTeks(teks: string): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(teks);
        return true;
    } catch {
        const t = document.createElement('textarea');
        t.value = teks;
        t.setAttribute('readonly', '');
        t.style.position = 'fixed';
        t.style.opacity = '0';
        document.body.appendChild(t);
        t.select();
        const ok = document.execCommand('copy');
        t.remove();
        return ok;
    }
}

/** Tambahkan teks di akhir isian tanpa menggandakan, dijepit ke panjang kolom. */
export function tambahkan(lama: string, baru: string, maks: number): string {
    const a = (lama ?? '').trim();
    const b = (baru ?? '').trim();
    const hasil = !a ? b : a.includes(b) ? a : `${a}\n${b}`;
    return hasil.length > maks ? hasil.slice(0, maks) : hasil;
}

/**
 * Status satu permintaan asisten untuk sebuah laci: hasil, memuat, galat.
 * Permintaan baru membatalkan yang masih berjalan.
 */
export function gunakanAsisten<T>(url: () => string, muatan: () => Record<string, unknown>) {
    const hasil = shallowRef<T | null>(null);
    const memuat = ref(false);
    const galat = ref<string | null>(null);
    let aktif: AbortController | null = null;

    async function minta() {
        aktif?.abort();
        const c = new AbortController();
        aktif = c;
        memuat.value = true;
        galat.value = null;
        try {
            hasil.value = await mintaAsisten<T>(url(), muatan(), c.signal);
        } catch (e) {
            if ((e as Error).name === 'AbortError') return;
            galat.value = e instanceof GalatAsisten ? e.message : 'Asisten AI sedang tidak dapat dipakai. Coba lagi beberapa saat lagi.';
        } finally {
            if (aktif === c) memuat.value = false;
        }
    }

    return { hasil, memuat, galat, minta };
}
