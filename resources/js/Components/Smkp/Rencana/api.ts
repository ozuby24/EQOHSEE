/**
 * Pakai ulang Rencana Audit: permintaan JSON ke /smkp/{id}/rencana/* dan
 * /asisten/smkp/{id}/rencana lewat fetch dengan token XSRF dari cookie
 * (sama dengan Inertia), serta aturan menggabungkan isian lama dan baru.
 * Galat apa pun diubah menjadi pesan berbahasa Indonesia yang siap tampil.
 */

export interface MetodeSampel { metode: string; sampel: string }
export interface BarisMetode extends MetodeSampel { kode: string }
export type BarisTabel = Record<string, string>;

export interface SumberRencana {
    id: number;
    judul: string;
    perusahaan: string;
    tahun: number;
    status: string;
    labelStatus: string;
    terisi: number;
    risiko: number;
    tim: number;
    samaPerusahaan: boolean;
}

export interface IsiSalin {
    metode_sampel: Record<string, MetodeSampel>;
    metode: string; sampel: string; tujuan: string; kriteria: string; ruang_lingkup: string; pengecualian: string;
    dokumentasi: string; sumberdaya: string; distribusi: string; jenis_audit: string;
    tugas: BarisTabel[];
    risiko: { present: BarisTabel[]; future: BarisTabel[] };
}

export interface HasilImpor {
    baris: BarisMetode[];
    sumber: 'tabel' | 'ai';
    berkas: string;
    dilewati?: number;
    kosong?: number;
    catatan?: string;
    model?: string;
}

export class GalatRencana extends Error {
    constructor(pesan: string, public status: number, public jenis: string | null) {
        super(pesan);
    }
}

function tokenXsrf(): string {
    const baris = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return baris ? decodeURIComponent(baris.slice('XSRF-TOKEN='.length)) : '';
}

/* Pesan cadangan bila server tidak mengirim pesan sendiri (mis. 413/504 dari Nginx). */
const UMUM: Record<number, string> = {
    401: 'Sesi Anda berakhir. Muat ulang halaman lalu masuk kembali.',
    403: 'Akun Anda tidak berhak mengubah Rencana Audit.',
    404: 'Audit tidak ditemukan atau di luar jangkauan akun Anda.',
    413: 'Berkas terlalu besar untuk diunggah (maks. 10 MB).',
    419: 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    429: 'Terlalu banyak permintaan. Coba lagi beberapa saat lagi.',
    504: 'Server tidak menjawab dalam batas waktu. Coba lagi beberapa saat lagi.',
};

async function minta<T>(url: string, init: RequestInit): Promise<T> {
    let res: Response;
    try {
        res = await fetch(url, {
            ...init,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': tokenXsrf() },
        });
    } catch (e) {
        if ((e as Error).name === 'AbortError') throw e;
        throw new GalatRencana('Tidak dapat menghubungi server. Periksa sambungan internet lalu coba lagi.', 0, 'jaringan');
    }

    let isi: any = null;
    try {
        isi = await res.json();
    } catch {
        // bukan JSON — mis. halaman galat Nginx
    }

    if (res.ok && isi) return isi as T;

    const validasi = isi?.errors ? (Object.values(isi.errors as Record<string, string[]>)[0]?.[0] ?? null) : null;
    const pesan = isi?.pesan ?? validasi ?? UMUM[res.status] ?? 'Permintaan gagal diproses. Coba lagi beberapa saat lagi.';
    throw new GalatRencana(pesan, res.status, isi?.jenis ?? null);
}

export function ambilJson<T>(url: string, sinyal?: AbortSignal): Promise<T> {
    return minta<T>(url, { method: 'GET', signal: sinyal });
}

export function kirimBerkas<T>(url: string, berkas: File, sinyal?: AbortSignal): Promise<T> {
    const data = new FormData();
    data.append('berkas', berkas);
    return minta<T>(url, { method: 'POST', body: data, signal: sinyal });
}

export function pesanGalat(e: unknown): string {
    return e instanceof GalatRencana ? e.message : 'Permintaan gagal diproses. Coba lagi beberapa saat lagi.';
}

/**
 * Nilai satu kolom setelah digabung. Isian baru yang kosong tidak pernah
 * menghapus isian lama; mode "isi kosong" hanya mengisi kolom yang masih kosong.
 */
export function gabungNilai(lama: string, baru: string, timpa: boolean): string {
    const l = (lama ?? '').trim();
    const b = (baru ?? '').trim();
    if (!b) return lama ?? '';
    return timpa || !l ? b : lama;
}

/** Tabel isian dianggap kosong bila tidak ada sel yang terisi. */
export function tabelKosong(rows: BarisTabel[]): boolean {
    return rows.every((r) => Object.values(r).every((v) => !String(v ?? '').trim()));
}

/** Kelompok bagian Rencana Audit yang dapat disalin dari audit lain. */
export const BAGIAN_SALIN = [
    { kunci: 'kriteria', judul: 'Metode & sampel per kriteria', ket: 'Metode audit dan sampel tiap sub-elemen.' },
    { kunci: 'metode', judul: 'Metode audit & dasar sampel umum', ket: 'Uraian metode dan dasar pengambilan sampel.' },
    { kunci: 'lingkup', judul: 'Jenis audit, tujuan, kriteria, lingkup & pengecualian', ket: 'Komponen 1–3 dan identitas audit.' },
    { kunci: 'risiko', judul: 'Top Risks', ket: 'Risk of present dan risk of future.' },
    { kunci: 'sumberdaya', judul: 'Dokumentasi, sumber daya & distribusi', ket: 'Fasilitas, dokumen relevan, penerima laporan.' },
    { kunci: 'tim', judul: 'Tim audit', ket: 'Pembagian tugas & nomor registrasi auditor.' },
] as const;

export type KunciBagian = (typeof BAGIAN_SALIN)[number]['kunci'];
