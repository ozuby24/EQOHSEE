/**
 * Simpan otomatis formulir Audit SMKP — port DBEST (smkp/include/autosimpan).
 *
 * Beberapa detik setelah auditor berhenti mengetik, isian dikirim ke alamat
 * simpan yang SAMA dengan tombol Simpan, ditandai kepala `X-Autosimpan: 1`
 * (App\Support\SmkpAutosimpan). Server menjawab JSON berisi versi baru, atau
 * 409 bila isinya sudah diubah orang/tab lain — simpan otomatis lalu berhenti
 * agar tidak saling menimpa; tombol Simpan tetap menimpa seperti biasa.
 *
 * Cadangan ketikan disimpan di peramban (localStorage) selama belum
 * tersimpan, sehingga ketikan yang hilang karena tab tertutup atau sambungan
 * putus ditawarkan kembali saat halaman dibuka lagi.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch, type Ref } from 'vue';

export type KeadaanSimpan = 'diam' | 'menunggu' | 'menyimpan' | 'tersimpan' | 'galat' | 'bentrok';

interface FormSimpan {
    isDirty: boolean;
    processing: boolean;
    data(): Record<string, any>;
    defaults(): void;
}

export interface OpsiAutosimpan {
    form: FormSimpan & Record<string, any>;
    /** Alamat simpan (sama dengan tombol Simpan). */
    url: string;
    /** Versi isi saat halaman dibuka (prop `versiSimpan`). */
    versi: string;
    /** Isi yang dikirim — bawaan form.data(); halaman yang memakai transform() memberi fungsinya sendiri. */
    muatan?: () => Record<string, any>;
    /** Boleh menyimpan (mis. bisaSunting). */
    aktif?: () => boolean;
    /** Jeda setelah ketikan terakhir (ms). */
    jeda?: number;
    /** Dipanggil setelah tersimpan otomatis (mis. menyegarkan pembanding). */
    sesudah?: () => void;
}

function tokenXsrf(): string {
    const baris = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='));
    return baris ? decodeURIComponent(baris.slice('XSRF-TOKEN='.length)) : '';
}

function bacaCadangan(kunci: string): { versi: string; waktu: string; isi: Record<string, any> } | null {
    try {
        const t = window.localStorage.getItem(kunci);
        return t ? JSON.parse(t) : null;
    } catch {
        return null;
    }
}

function tulisCadangan(kunci: string, nilai: unknown | null): void {
    try {
        if (nilai === null) window.localStorage.removeItem(kunci);
        else window.localStorage.setItem(kunci, JSON.stringify(nilai));
    } catch {
        // Penyimpanan peramban tidak tersedia (mode privat) — simpan otomatis tetap berjalan.
    }
}

export function useAutosimpan(o: OpsiAutosimpan) {
    const keadaan = ref<KeadaanSimpan>('diam');
    const pesan = ref('');
    const pukul = ref('');
    const versi = ref(o.versi);
    const sunting = Math.random().toString(36).slice(2, 12);
    const kunci = `eq-autosimpan:${o.url}`;
    const pulihan: Ref<{ waktu: string; isi: Record<string, any> } | null> = ref(null);
    const jeda = o.jeda ?? 4000;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let berjalan = false;

    const boleh = () => (o.aktif ? o.aktif() : true) && keadaan.value !== 'bentrok';

    async function simpanSekarang(): Promise<void> {
        if (!boleh() || !o.form.isDirty || o.form.processing || berjalan) return;
        berjalan = true;
        keadaan.value = 'menyimpan';
        const isi = (o.muatan ?? (() => o.form.data()))();
        try {
            const res = await fetch(o.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': tokenXsrf(), 'X-Autosimpan': '1',
                },
                body: JSON.stringify({ ...isi, _versi: versi.value, _sunting: sunting }),
            });
            let j: any = null;
            try { j = await res.json(); } catch { /* bukan JSON */ }
            if (res.ok && j?.ok) {
                versi.value = j.versi;
                pukul.value = j.pukul;
                keadaan.value = 'tersimpan';
                pesan.value = '';
                o.form.defaults();
                tulisCadangan(kunci, null);
                o.sesudah?.();
            } else if (res.status === 409) {
                keadaan.value = 'bentrok';
                pesan.value = j?.pesan ?? 'Isian sudah berubah di server. Simpan otomatis dihentikan.';
            } else {
                keadaan.value = 'galat';
                const validasi = j?.errors ? (Object.values(j.errors as Record<string, string[]>)[0]?.[0] ?? null) : null;
                pesan.value = validasi ?? j?.pesan ?? 'Belum tersimpan otomatis — tekan Simpan.';
            }
        } catch {
            keadaan.value = 'galat';
            pesan.value = 'Sambungan terputus — ketikan disimpan di peramban, akan dikirim lagi.';
        } finally {
            berjalan = false;
        }
    }

    watch(() => JSON.stringify(o.form.data()), () => {
        if (!o.form.isDirty || !boleh()) return;
        tulisCadangan(kunci, { versi: versi.value, waktu: new Date().toISOString(), isi: o.form.data() });
        keadaan.value = 'menunggu';
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => { void simpanSekarang(); }, jeda);
    });

    onMounted(() => {
        const c = bacaCadangan(kunci);
        // Ketikan lama hanya ditawarkan bila isi server belum berubah sejak itu.
        if (c && c.versi === o.versi && JSON.stringify(c.isi) !== JSON.stringify(o.form.data())) {
            pulihan.value = { waktu: c.waktu, isi: c.isi };
        } else if (c) {
            tulisCadangan(kunci, null);
        }
    });
    onBeforeUnmount(() => { if (timer) clearTimeout(timer); });

    /** Pasang kembali ketikan dari cadangan peramban (belum tersimpan sampai dikirim). */
    function pulihkan(): void {
        if (!pulihan.value) return;
        for (const [k, v] of Object.entries(pulihan.value.isi)) o.form[k] = v;
        pulihan.value = null;
    }
    function buangPulihan(): void {
        pulihan.value = null;
        tulisCadangan(kunci, null);
    }
    /** Tombol Simpan berhasil: cadangan dibuang, simpan otomatis boleh berjalan lagi. */
    function sudahDisimpan(versiBaru?: string): void {
        tulisCadangan(kunci, null);
        if (versiBaru) versi.value = versiBaru;
        keadaan.value = 'diam';
        pesan.value = '';
    }

    const teks = computed(() => ({
        diam: '', menunggu: 'Menunggu Anda selesai mengetik…', menyimpan: 'Menyimpan otomatis…',
        tersimpan: `Tersimpan otomatis pukul ${pukul.value}`, galat: pesan.value, bentrok: pesan.value,
    })[keadaan.value]);

    return { keadaan, teks, pulihan, pulihkan, buangPulihan, sudahDisimpan, simpanSekarang, versi };
}
