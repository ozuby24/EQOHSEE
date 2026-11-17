/**
 * Pembagian berkas cetak panjang ke lembar A4 dari tinggi isi yang diukur:
 * blok dirender sekali di wadah ukur tersembunyi selebar A4 (`ukurEl`),
 * tiap blok/baris tabel diukur, lalu dibagi dengan bagiBlok. Dipakai Berita
 * Acara Tahap I dan Laporan Audit.
 */
import { nextTick, onBeforeUnmount, onMounted, ref, shallowRef, watch, type ComputedRef } from 'vue';
import { bagiBlok, bagiLembar, type PotonganBlok, type UkuranBlok } from './halaman';
import type { BlokCetak } from './blokCetak';

/**
 * Jalankan `susun` setelah huruf wadah ukur termuat, lalu ulangi setiap kali
 * huruf baru selesai dimuat. Tata letak wadah ukur dipaksa lebih dulu agar
 * peramban mulai memuat hurufnya: `fonts.ready` yang ditunggu sebelum itu
 * sudah selesai, dan pengukuran pertama memakai huruf cadangan yang lebih
 * sempit — lembar lalu tercetak lebih panjang dari perkiraan.
 */
function ukurSetelahHuruf(ukurEl: { value: HTMLElement | null }, susun: () => Promise<void>) {
    const ulang = () => { void susun(); };
    onMounted(async () => {
        await nextTick();
        void ukurEl.value?.offsetHeight;
        await document.fonts?.ready;
        await susun();
        document.fonts?.addEventListener?.('loadingdone', ulang);
    });
    onBeforeUnmount(() => document.fonts?.removeEventListener?.('loadingdone', ulang));
}

// Judul, kalimat pengantar bertanda lekat, dan tanda tangan tidak dipisah dari blok sesudahnya.
const lekat = (b: BlokCetak | undefined) => !!b && (b.k === 'judul' || b.k === 'ttd' || ((b.k === 'teks' || b.k === 'daftar') && !!b.lekat));

export function useSusunLembar(blok: ComputedRef<BlokCetak[]>) {
    const ukurEl = shallowRef<HTMLElement | null>(null);
    const lembar = ref<PotonganBlok[][] | null>(null);

    async function susun() {
        await nextTick();
        const akar = ukurEl.value;
        const hal = akar?.querySelector<HTMLElement>('.smkp-lembar');
        const awal = akar?.querySelector<HTMLElement>('[data-awal]');
        if (!akar || !hal || !awal) return;
        const kotak = hal.getBoundingClientRect();
        const mm = kotak.width / 210;
        // Tinggi A4 − bantalan bawah 16 mm − kop & bantalan atas; disisakan sedikit untuk pembulatan cetak.
        const ruang = 297 * mm - 16 * mm - (awal.getBoundingClientRect().top - kotak.top) - 6;
        const ukuran: UkuranBlok[] = Array.from(akar.querySelectorAll<HTMLElement>('[data-blok]')).map((el, i) => {
            const tinggi = el.getBoundingClientRect().height;
            const tbl = el.querySelector<HTMLElement>('table[data-pecah]');
            if (!tbl) return { tinggi, lekat: lekat(blok.value[i]) };
            return {
                tinggi,
                kepala: tbl.querySelector('thead')?.getBoundingClientRect().height ?? 0,
                baris: Array.from(tbl.querySelectorAll(':scope > tbody > tr')).map((tr) => tr.getBoundingClientRect().height),
                jarak: tinggi - tbl.getBoundingClientRect().height,
            };
        });
        lembar.value = bagiBlok(ukuran, ruang);
    }

    ukurSetelahHuruf(ukurEl, susun);
    watch(blok, susun);

    return { ukurEl, lembar };
}

/**
 * Pembagian baris tabel formulir dari tinggi terukur: satu lembar contoh
 * berisi seluruh baris dirender tersembunyi — badan tabelnya bertanda
 * `data-baris`, penutupnya (jumlah, tanda tangan) `data-penutup`, dan bagian
 * yang hanya dicetak di lembar pertama (judul & identitas) `data-hanya-awal`.
 * Ruang baris = batas bawah isi lembar − puncak badan tabel (lembar
 * berikutnya mendapat tambahan setinggi bagian lembar pertama tadi);
 * penutup harus muat di lembar terakhir.
 */
export function useBarisLembar<T>(baris: ComputedRef<T[]>, lanskap = false) {
    const ukurEl = shallowRef<HTMLElement | null>(null);
    const halaman = shallowRef<T[][] | null>(null);

    async function susun() {
        await nextTick();
        const akar = ukurEl.value;
        const hal = akar?.querySelector<HTMLElement>('.smkp-lembar');
        const badan = akar?.querySelector<HTMLElement>('[data-baris]');
        if (!akar || !hal || !badan) return;
        const kotak = hal.getBoundingClientRect();
        const mm = kotak.width / (lanskap ? 297 : 210);
        // Batas bawah isi = tinggi lembar − bantalan bawah 16 mm; disisakan sedikit untuk pembulatan cetak.
        const ruang = (lanskap ? 210 : 297) * mm - 16 * mm - (badan.getBoundingClientRect().top - kotak.top) - 6;
        const tinggi = Array.from(badan.querySelectorAll(':scope > tr')).map((tr) => tr.getBoundingClientRect().height);
        const penutup = akar.querySelector<HTMLElement>('[data-penutup]')?.getBoundingClientRect().height ?? 0;
        const kepala = akar.querySelector<HTMLElement>('[data-hanya-awal]')?.getBoundingClientRect().height ?? 0;
        halaman.value = bagiLembar(baris.value.map((b, i) => ({ b, i })), (x) => tinggi[x.i] ?? 0, (k) => (k ? ruang + kepala : ruang), penutup)
            .map((h) => h.map((x) => x.b));
    }

    ukurSetelahHuruf(ukurEl, susun);
    watch(baris, susun);

    return { ukurEl, halaman };
}

/**
 * Formulir per butir (Formulir Ketidaksesuaian per temuan) yang seksinya
 * mengalir ke lembar sebanyak yang diperlukan: tiap grup selalu mulai di
 * lembar baru, seksinya ditempatkan berurutan menurut tinggi terukur
 * (`data-seksi="grup:seksi"` di wadah ukur, bertanda `data-awal` di puncak
 * isi lembar), dan seksi bertanda lekat tidak dipisah dari seksi sesudahnya.
 */
export interface GrupSeksi { seksi: string[]; lekat?: string[] }

export function useSeksiLembar(grup: ComputedRef<GrupSeksi[]>) {
    const ukurEl = shallowRef<HTMLElement | null>(null);
    const halaman = shallowRef<Array<{ grup: number; seksi: string[] }> | null>(null);

    async function susun() {
        await nextTick();
        const akar = ukurEl.value;
        const hal = akar?.querySelector<HTMLElement>('.smkp-lembar');
        const awal = akar?.querySelector<HTMLElement>('[data-awal]');
        if (!akar || !hal || !awal) return;
        const kotak = hal.getBoundingClientRect();
        const mm = kotak.width / 210;
        // Lembar lanjutan suatu grup diberi keterangan "(lanjutan)" (`data-lanjutan`); ruangnya disisihkan di semua lembar.
        const lanjutan = akar.querySelector<HTMLElement>('[data-lanjutan]')?.getBoundingClientRect().height ?? 0;
        const ruang = 297 * mm - 16 * mm - (awal.getBoundingClientRect().top - kotak.top) - lanjutan - 6;
        const tinggi = new Map<string, number>();
        akar.querySelectorAll<HTMLElement>('[data-seksi]').forEach((el) => tinggi.set(el.dataset.seksi ?? '', el.getBoundingClientRect().height));

        const hasil: Array<{ grup: number; seksi: string[] }> = [];
        grup.value.forEach((g, gi) => {
            const ukuran = g.seksi.map((s) => ({ tinggi: tinggi.get(`${gi}:${s}`) ?? 0, lekat: !!g.lekat?.includes(s) }));
            for (const h of bagiBlok(ukuran, ruang)) hasil.push({ grup: gi, seksi: h.map((c) => g.seksi[c.i]) });
        });
        halaman.value = hasil;
    }

    ukurSetelahHuruf(ukurEl, susun);
    watch(grup, susun);

    return { ukurEl, halaman };
}
