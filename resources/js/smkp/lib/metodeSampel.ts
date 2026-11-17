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

/* ───────── Rincian sampel wawancara (port DBEST MetodeSampel::cekRincian) ─────────
 * "Wawancara 5 orang:" lalu baris "2 orang KTT", "3 orang Operator" — jumlah
 * pada judul dibandingkan dengan jumlah rinciannya agar sampel yang tertulis
 * tidak berselisih dengan yang akan diwawancarai. */

const KATA_ORANG = /\b(wawancara|responden|pekerja|karyawan|personil|personel|interview)\b/i;

/** Jumlah orang pada judul rincian ("Wawancara 5 orang:") atau butir rinciannya ("3 orang Operator"). */
export function jumlahOrang(teks: string, judul = false): number | null {
    const t = String(teks ?? '').trim();
    if (judul) {
        if (/^\d/.test(t) || !KATA_ORANG.test(t)) return null;
        const m = t.match(/\b(\d{1,3})\s*orang\b/i);
        return m ? Number(m[1]) : null;
    }
    const m = t.match(/^(\d{1,3})\s*orang\b/i);
    return m ? Number(m[1]) : null;
}

export type CekWawancara = { judul: string; jumlah: number; rinci: number };

/** Pemeriksaan tiap judul wawancara berjumlah dalam teks sampel; judul tanpa rincian berangka dilewati. */
export function cekWawancara(sampel: unknown): CekWawancara[] {
    const out: CekWawancara[] = [];
    let kini: { judul: string; jumlah: number; rinci: number; anak: number; sah: boolean } | null = null;
    const tutup = () => { if (kini && kini.sah && kini.anak) out.push({ judul: kini.judul, jumlah: kini.jumlah, rinci: kini.rinci }); kini = null; };
    const butir = (x: string) => x.replace(/^\s*(?:[-•*·–]|\d{1,2}[.)]|[a-z][.)])\s+/i, '').trim();

    for (const mentah of String(sampel ?? '').split(/\r?\n/)) {
        const baris = butir(mentah);
        if (!baris) continue;
        const [kepala, ...sisa] = baris.split(':');
        const n = jumlahOrang(kepala, true);
        if (n !== null) {
            tutup();
            kini = { judul: kepala.trim(), jumlah: n, rinci: 0, anak: 0, sah: true };
            // Rincian sebaris: "Wawancara 5 orang: 2 orang KTT, 3 orang Operator".
            for (const x of sisa.join(':').split(/[,;]/).map(butir).filter(Boolean)) {
                const j = jumlahOrang(x);
                if (j === null) kini.sah = false; else { kini.rinci += j; kini.anak++; }
            }
            continue;
        }
        if (!kini) continue;
        const j = jumlahOrang(baris);
        if (j === null) { tutup(); continue; }
        kini.rinci += j;
        kini.anak++;
    }
    tutup();
    return out;
}
