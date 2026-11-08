/**
 * Pembagian baris tabel berkas cetak ke lembar A4.
 *
 * Setiap lembar mencetak kopnya sendiri (nomor halaman ikut berubah), jadi
 * tabel panjang tidak boleh dibiarkan meluber ke halaman fisik berikutnya
 * tanpa kop. Tinggi tiap baris diperkirakan dari jumlah baris teks pada sel
 * terpanjangnya — nama kriteria SMKP mengikuti teks resmi Kepdirjen dan
 * cukup panjang untuk terlipat dua atau tiga baris.
 */

/** Perkiraan jumlah baris teks bila `teks` dilipat per kata pada `lebar` karakter. */
export function jumlahBaris(teks: string | null | undefined, lebar: number): number {
    let n = 0;
    for (const paragraf of String(teks ?? '').split('\n')) {
        let baris = 1, isi = 0;
        for (const kata of paragraf.split(/\s+/).filter(Boolean)) {
            if (isi === 0) isi = kata.length;
            else if (isi + 1 + kata.length <= lebar) isi += 1 + kata.length;
            else { baris++; isi = kata.length; }
            while (isi > lebar) { baris++; isi -= lebar; }
        }
        n += baris;
    }
    return Math.max(1, n);
}

/**
 * Isi lembar demi lembar sampai ruangnya habis. `ruang(i)` = tinggi yang
 * tersedia untuk baris tabel pada lembar ke-i (px); `ekor` = tinggi penutup
 * yang harus muat di lembar terakhir (baris total, tanda tangan) — bila tidak
 * muat, penutup mendapat lembar sendiri.
 */
export function bagiLembar<T>(baris: T[], tinggi: (b: T) => number, ruang: (lembarKe: number) => number, ekor = 0): T[][] {
    const hasil: T[][] = [[]];
    let pakai = 0;
    for (const b of baris) {
        const t = tinggi(b);
        if (hasil[hasil.length - 1].length && pakai + t > ruang(hasil.length - 1)) {
            hasil.push([]);
            pakai = 0;
        }
        hasil[hasil.length - 1].push(b);
        pakai += t;
    }
    if (ekor && pakai + ekor > ruang(hasil.length - 1)) hasil.push([]);
    return hasil;
}

/**
 * Potong teks panjang per kata menjadi bagian yang masing-masing paling
 * banyak `maksBaris` baris pada `lebar` karakter — untuk isian yang lebih
 * tinggi daripada satu lembar sehingga harus berlanjut ke lembar berikutnya.
 */
export function potongTeks(teks: string | null | undefined, lebar: number, maksBaris: number): string[] {
    const token = String(teks ?? '').split(/(\n)|[ \t]+/).filter((t) => t !== undefined && t !== '');
    const hasil: string[] = [];
    let kini = '';
    for (const t of token) {
        const calon = t === '\n' ? `${kini}\n` : kini === '' || kini.endsWith('\n') ? `${kini}${t}` : `${kini} ${t}`;
        if (t !== '\n' && kini.trim() !== '' && jumlahBaris(calon, lebar) > maksBaris) {
            hasil.push(kini.trim());
            kini = t;
        } else {
            kini = calon;
        }
    }
    if (kini.trim() !== '' || !hasil.length) hasil.push(kini.trim());
    return hasil;
}

/**
 * Ukuran satu blok berkas cetak hasil pengukuran DOM (px). Tabel yang boleh
 * terbelah antar lembar membawa tinggi kepala dan tiap barisnya; kepala
 * tabel dicetak ulang di lembar lanjutan.
 */
export interface UkuranBlok {
    /** Tinggi seluruh blok, termasuk jarak bawahnya. */
    tinggi: number;
    kepala?: number;
    baris?: number[];
    /** Jarak bawah blok — hanya terpakai setelah baris terakhir tabel. */
    jarak?: number;
    /** Judul: harus selembar dengan awal blok sesudahnya. */
    lekat?: boolean;
}

/** Blok ke-`i` (atau baris `dari`..`sampai` tabelnya) pada satu lembar. */
export interface PotonganBlok { i: number; dari?: number; sampai?: number }

/** Bagi blok yang sudah diukur ke lembar-lembar dengan ruang isi `ruang` px. */
export function bagiBlok(ukuran: UkuranBlok[], ruang: number): PotonganBlok[][] {
    const hasil: PotonganBlok[][] = [[]];
    let pakai = 0;
    const kini = () => hasil[hasil.length - 1];
    const baru = () => { if (kini().length) { hasil.push([]); pakai = 0; } };
    // Tabel pendek (≤ sepertiga lembar) tidak dibelah — pindah utuh bila tak muat.
    const tabel = (u: UkuranBlok | undefined): u is UkuranBlok & { baris: number[] } => !!u?.baris?.length && u.tinggi > ruang / 3;
    // Tinggi minimum awal blok ke-j: kepala + baris pertama untuk tabel; judul ikut menyeret blok sesudahnya.
    const awal = (j: number): number => {
        const u = ukuran[j];
        if (!u) return 0;
        if (tabel(u)) return (u.kepala ?? 0) + u.baris[0] + (u.baris.length === 1 ? u.jarak ?? 0 : 0);
        return u.tinggi + (u.lekat ? awal(j + 1) : 0);
    };

    ukuran.forEach((u, i) => {
        if (!tabel(u)) {
            if (pakai + awal(i) > ruang) baru();
            kini().push({ i });
            pakai += u.tinggi;
            return;
        }
        const n = u.baris.length;
        let dari = 0;
        let kepala = true;
        for (let r = 0; r < n;) {
            const t = u.baris[r] + (kepala ? u.kepala ?? 0 : 0) + (r === n - 1 ? u.jarak ?? 0 : 0);
            // Baris yang tak muat pindah ke lembar baru — kecuali lembarnya memang masih kosong.
            if (pakai + t > ruang && (r > dari || kini().length)) {
                if (r > dari) kini().push({ i, dari, sampai: r });
                baru();
                dari = r;
                kepala = true;
                continue;
            }
            pakai += t;
            kepala = false;
            r++;
        }
        kini().push({ i, dari, sampai: n });
    });

    return hasil.filter((h) => h.length);
}
