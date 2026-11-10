/*
 * Metode & sampel audit per kriteria untuk Rencana Audit cetak.
 *
 * Isian bebas auditor dipecah agar tiap metode berdiri sendiri dan sampelnya
 * sejajar: "Tinjauan dokumen & rekaman, wawancara, observasi" menjadi tiga
 * metode; sampel "dokumen …; KTT, HSE Manager; area tambang" menjadi tiga
 * kelompok — kelompok ke-n milik metode ke-n. Daftar satu butir per baris
 * tetap satu kelompok. Isi dalam kurung tidak ikut
 * dipecah, mis. "Struktur organisasi (KTT, pengawas, teknis)".
 */

/** Kata awal yang menandai metode audit tersendiri setelah "&" atau "dan". */
const KATA_METODE = /^(tinjauan|peninjauan|wawancara|observasi|pengamatan|inspeksi|verifikasi|pemeriksaan|pengujian|uji|survei|kunjungan|simulasi|demonstrasi)\b/i;

/** Pecah teks pada pemisah yang berada di luar tanda kurung. */
function pecah(teks: string, pemisah: string): string[] {
    const hasil: string[] = [];
    let kini = '';
    let dalam = 0;
    for (const c of teks) {
        if (c === '(' || c === '[') dalam++;
        else if ((c === ')' || c === ']') && dalam > 0) dalam--;
        if (dalam === 0 && pemisah.includes(c)) { hasil.push(kini); kini = ''; } else kini += c;
    }
    hasil.push(kini);
    return hasil;
}

/** Rapikan satu butir: penanda daftar ketikan, spasi ganda, tanda baca di ujung, huruf awal kapital. */
function rapi(x: string): string {
    const t = x.replace(/^\s*(?:[-•*·]|\d+[.)])\s+/, '').replace(/\s+/g, ' ').trim().replace(/[\s.,;:]+$/, '');
    return t ? t.charAt(0).toUpperCase() + t.slice(1) : '';
}

/** Daftar metode: dipisah koma/titik koma/baris, serta "&"/"dan" bila diikuti kata metode. */
export function pisahMetode(v: unknown): string[] {
    const out: string[] = [];
    for (const bagian of pecah(String(v ?? ''), ',;\n')) {
        const potong = bagian.trim().split(/\s+(&|dan)\s+/i);
        let kini = potong[0] ?? '';
        for (let i = 1; i < potong.length; i += 2) {
            const kata = potong[i + 1] ?? '';
            if (KATA_METODE.test(kata)) { out.push(kini); kini = kata; } else kini += ` ${potong[i]} ${kata}`;
        }
        out.push(kini);
    }
    return out.map(rapi).filter(Boolean);
}

/** Kelompok sampel (pemisah ";"), tiap kelompok berisi butir (pemisah koma atau baris baru). */
export function pisahSampel(v: unknown): string[][] {
    return pecah(String(v ?? ''), ';')
        .map((g) => pecah(g, ',\n').map(rapi).filter(Boolean))
        .filter((g) => g.length);
}

export type BarisMS = { no: number; metode: string; sampel: string[]; rentang: number };

/**
 * Baris metode–sampel yang sejajar. `rentang` = jumlah baris yang dicakup sel
 * sampel (rowspan); 0 berarti sel sampel milik baris di atasnya. Satu kelompok
 * sampel untuk beberapa metode dibentangkan ke semua metode; kelompok sampel
 * yang lebih banyak daripada metode ikut ke metode terakhir.
 */
export function susunMetodeSampel(metode: unknown, sampel: unknown): BarisMS[] {
    const m = pisahMetode(metode);
    const g = pisahSampel(sampel);
    if (!m.length) return [{ no: 0, metode: '', sampel: g.flat(), rentang: 1 }];

    // "Wawancara KTT, HSE Manager" di baris metode Wawancara cukup "KTT, HSE Manager".
    const tanpaUlang = (metodeIni: string, butir: string[]) => {
        const kata = metodeIni.split(' ')[0].toLowerCase();
        if (!butir.length || kata.length < 4) return butir;
        const [awal, ...sisa] = butir;
        const lepas = awal.toLowerCase().startsWith(`${kata} `) || awal.toLowerCase().startsWith(`${kata}:`)
            ? rapi(awal.slice(kata.length).replace(/^\s*:?/, '')) : awal;
        return [lepas, ...sisa].filter(Boolean);
    };

    if (g.length <= 1) {
        return m.map((x, i) => ({ no: m.length > 1 ? i + 1 : 0, metode: x, sampel: i ? [] : (g[0] ?? []), rentang: i ? 0 : m.length }));
    }
    return m.map((x, i) => {
        const milik = i === m.length - 1 ? g.slice(i).flat() : (g[i] ?? []);
        return { no: m.length > 1 ? i + 1 : 0, metode: x, sampel: tanpaUlang(x, milik), rentang: 1 };
    });
}
