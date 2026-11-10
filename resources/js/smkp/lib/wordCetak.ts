/**
 * Berkas cetak panjang (lib/blokCetak) → dokumen Word (.docx) yang dapat
 * disunting pengguna. Blok yang sama dengan lembar cetak disusun ulang
 * sebagai unsur Word: kop dokumen menjadi kepala halaman berulang dengan
 * "Halaman X dari Y" (halaman judul tidak ikut dihitung), judul bernomor
 * memakai gaya Heading, daftar memakai penomoran Word, dan tabel panjang
 * mengulang baris kepalanya di halaman berikutnya. Modul ini dimuat hanya
 * saat pengguna mengunduh (impor dinamis) karena pustaka docx cukup besar.
 */
import {
    AlignmentType, BorderStyle, Document, Header, HeadingLevel, HeightRule, ImageRun, LevelFormat, LineRuleType, OverlapType, Packer,
    PageNumber, Paragraph, RelativeVerticalPosition, ShadingType, Tab, Table, TableAnchorType, TableCell, TableLayoutType,
    TableRow, TabStopType, TextRun, VerticalAlignTable, WidthType,
    type ILevelsOptions, type IParagraphOptions, type ISectionOptions, type ParagraphChild,
} from 'docx';
import type { BlokCetak, Isi } from './blokCetak';
import type { KopDok } from '../types';

/** Penanda tangan satu blok `ttd`: Fungsi · Nama · gambar tanda tangan (URL). */
export interface PenandaWord { peran: string; nama: string; gambar: string | null }

export interface OpsiWord {
    namaBerkas: string;
    /** Judul dokumen pada properti berkas. */
    judul: string;
    dok: KopDok;
    blok: BlokCetak[];
    /** Halaman judul tanpa kop; baris rincian dicetak di dasar halaman. */
    sampul?: { judul: Isi[]; perusahaan: string; rincian: Array<[string, string]> };
    /** Isi blok `ttd` menurut `id`-nya. */
    ttd: Record<string, PenandaWord[]>;
}

/* Ukuran dalam twip (1/20 pt; 1 px lembar cetak = 15 twip). */
const A4 = { lebar: 11906, tinggi: 16838 };
const TEPI = 1134; // 20 mm kiri, kanan, bawah
const KEPALA = 567; // kop 10 mm dari tepi atas
// Isi mulai di bawah kop; kop yang lebih tinggi (judul panjang) mendorong isi turun dengan sendirinya.
const ATAS_ISI = 2560;
const LEBAR_ISI = A4.lebar - 2 * TEPI;
const INDEN = [0, 390, 660]; // = pl-[26px], pl-[44px] lembar cetak
const JARAK_TABEL = 200;

const NAVY = '0B2D4D';
const ABU = '64748B';
const GARIS = '64748B';
const GARIS_KOP = '94A3B8';

interface Gambar { data: Uint8Array; lebar: number; tinggi: number }

/**
 * Unduh gambar lalu ubah ke PNG; null bila gagal dimuat. URL berkas publik
 * dibentuk dari APP_URL — bila asalnya lain dari alamat yang sedang dibuka
 * (IP vs domain, www), jalur yang sama di asal halaman ini dicoba lebih dulu
 * karena permintaan lintas asal ditolak peramban.
 */
async function muatGambar(url: string): Promise<Gambar | null> {
    let calon: string[];
    try {
        const u = new URL(url, window.location.href);
        calon = u.origin === window.location.origin ? [u.href] : [window.location.origin + u.pathname + u.search, u.href];
    } catch {
        return null;
    }
    for (const alamat of calon) {
        const g = await bacaGambar(alamat);
        if (g) return g;
    }
    return null;
}

/** PNG/JPG/WebP/SVG → PNG lewat kanvas (docx hanya menerima PNG/JPG/GIF/BMP), sisi terpanjang ≤ `maks` px. */
async function bacaGambar(url: string, maks = 1200): Promise<Gambar | null> {
    try {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) return null;
        const src = URL.createObjectURL(await res.blob());
        try {
            const img = new Image();
            img.src = src;
            await img.decode();
            if (!img.naturalWidth || !img.naturalHeight) return null;
            const s = Math.min(1, maks / Math.max(img.naturalWidth, img.naturalHeight));
            const kanvas = document.createElement('canvas');
            kanvas.width = Math.max(1, Math.round(img.naturalWidth * s));
            kanvas.height = Math.max(1, Math.round(img.naturalHeight * s));
            kanvas.getContext('2d')?.drawImage(img, 0, 0, kanvas.width, kanvas.height);
            const png = await new Promise<Blob | null>((ok) => kanvas.toBlob(ok, 'image/png'));
            return png ? { data: new Uint8Array(await png.arrayBuffer()), lebar: kanvas.width, tinggi: kanvas.height } : null;
        } finally {
            URL.revokeObjectURL(src);
        }
    } catch {
        return null;
    }
}

/** Gambar dalam kotak maksL × maksT px; `besarkan` = tinggi tetap seperti `h-*` lembar cetak. */
function gambarRun(g: Gambar, maksL: number, maksT: number, besarkan = false, nama = 'Gambar') {
    const s = Math.min(maksL / g.lebar, maksT / g.tinggi, besarkan ? Infinity : 1);
    return new ImageRun({
        type: 'png', data: g.data, altText: { name: nama, title: nama, description: nama },
        transformation: { width: Math.max(1, Math.round(g.lebar * s)), height: Math.max(1, Math.round(g.tinggi * s)) },
    });
}

interface Gaya { tebal?: boolean; ukuran?: number; warna?: string }

/** Ruas → TextRun; baris baru di dalam teks menjadi pindah baris (seperti whitespace-pre-line). */
function runs(isi: Isi, g: Gaya = {}): TextRun[] {
    return isi.flatMap((r) => {
        const o = typeof r === 'string' ? { t: r } : r;
        return o.t.split('\n').map((t, i) => new TextRun({
            text: t, break: i ? 1 : undefined, bold: o.b || g.tebal || undefined, italics: o.i || undefined,
            strike: o.s || undefined, size: g.ukuran, color: g.warna,
        }));
    });
}

const tab = () => new TextRun({ children: [new Tab()] });
const teksPolos = (isi: Isi) => isi.map((r) => (typeof r === 'string' ? r : r.t)).join('');

const sisi = (warna: string) => ({ style: BorderStyle.SINGLE, size: 4, color: warna });
const garis = (warna: string) => ({
    top: sisi(warna), bottom: sisi(warna), left: sisi(warna), right: sisi(warna), insideHorizontal: sisi(warna), insideVertical: sisi(warna),
});
const tanpa = { style: BorderStyle.NONE, size: 0, color: 'auto' };
const TANPA_GARIS = { top: tanpa, bottom: tanpa, left: tanpa, right: tanpa, insideHorizontal: tanpa, insideVertical: tanpa };

interface OpsiSel {
    lebar: number;
    rentang?: number;
    latar?: string;
    tebal?: boolean;
    rata?: (typeof AlignmentType)[keyof typeof AlignmentType];
    tengahV?: boolean;
}

function sel(anak: ParagraphChild[], o: OpsiSel) {
    return new TableCell({
        width: { size: o.lebar, type: WidthType.DXA },
        columnSpan: o.rentang,
        shading: o.latar ? { type: ShadingType.CLEAR, fill: o.latar, color: 'auto' } : undefined,
        verticalAlign: o.tengahV ? VerticalAlignTable.CENTER : VerticalAlignTable.TOP,
        children: [new Paragraph({ style: 'IsiTabel', alignment: o.rata, children: anak })],
    });
}

const selIsi = (isi: Isi, o: OpsiSel) => sel(runs(isi, { tebal: o.tebal }), o);

const kepalaTabel = (judul: Isi[], lebar: number[]) => new TableRow({
    tableHeader: true, cantSplit: true, keepNext: true,
    children: judul.map((j, i) => selIsi(j, { lebar: lebar[i], latar: 'F1F5F9', tebal: true, rata: AlignmentType.CENTER, tengahV: true })),
});

/** Blok cetak → paragraf & tabel Word, berurutan. */
class Penyusun {
    isi: Array<Paragraph | Table> = [];
    awalan = new Set<string>();
    private jarak = 0;
    private daftar = 0;

    constructor(private ttd: Record<string, PenandaWord[]>, private gambar: Map<string, Gambar>) {}

    private p(o: IParagraphOptions) {
        const atas = Math.max(this.jarak, o.spacing?.before ?? 0);
        this.jarak = 0;
        this.isi.push(new Paragraph(atas ? { ...o, spacing: { ...o.spacing, before: atas } } : o));
    }

    private tabel(t: Table) {
        // Dua tabel berdampingan digabung Word menjadi satu; diberi paragraf pemisah.
        if (this.isi[this.isi.length - 1] instanceof Table) this.isi.push(new Paragraph({ spacing: { after: 0 } }));
        this.isi.push(t);
        this.jarak = JARAK_TABEL;
    }

    tambah(b: BlokCetak) {
        switch (b.k) {
            case 'judul': return this.judul(b);
            case 'teks': return this.p({
                alignment: AlignmentType.JUSTIFIED, indent: { left: INDEN[b.inden ?? 2] }, keepNext: b.lekat || undefined, children: runs(b.isi),
            });
            case 'daftar': return this.daftarBlok(b);
            case 'rincian': return this.rincian(b);
            case 'tabel': return this.tabelBlok(b);
            case 'ttd': return this.tandaTangan(this.ttd[b.id ?? ''] ?? []);
        }
    }

    private judul(b: Extract<BlokCetak, { k: 'judul' }>) {
        if (b.tingkat === 0) {
            b.isi.forEach((r, i) => this.p({
                alignment: AlignmentType.CENTER, keepNext: true, spacing: { after: i === b.isi.length - 1 ? 240 : 0 },
                children: runs([typeof r === 'string' ? r.toUpperCase() : { ...r, t: r.t.toUpperCase() }], { tebal: true, ukuran: 22 }),
            }));
            return;
        }
        const heading = [HeadingLevel.HEADING_1, HeadingLevel.HEADING_2, HeadingLevel.HEADING_3][b.tingkat - 1];
        const awal = [0, INDEN[1], INDEN[2]][b.tingkat - 1];
        if (!b.nomor) return this.p({ heading, indent: { left: awal }, children: runs(b.isi) });
        const lebar = [INDEN[1], 570, 700][b.tingkat - 1];
        this.p({
            heading, indent: { left: awal + lebar, hanging: lebar }, tabStops: [{ type: TabStopType.LEFT, position: awal + lebar }],
            children: [new TextRun(b.nomor), tab(), ...runs(b.isi)],
        });
    }

    private daftarBlok(b: Extract<BlokCetak, { k: 'daftar' }>) {
        const acuan = b.awalan ? `awalan:${b.awalan}` : b.gaya === '•' ? 'titik' : b.gaya === '1)' ? 'kurung' : 'angka';
        if (b.awalan) this.awalan.add(b.awalan);
        // Tiap blok daftar bernomor mulai dari 1 (contoh daftar Word tersendiri); butir titik cukup satu daftar.
        const contoh = b.gaya === '•' && !b.awalan && !b.butir.some((x) => x.anak?.length) ? 0 : ++this.daftar;
        const awal = INDEN[b.inden ?? 2];
        const w = b.awalan ? 600 : 400;
        const butir = b.butir.flatMap((x) => [{ isi: x.isi, anak: false }, ...(x.anak ?? []).map((a) => ({ isi: a, anak: true }))]);
        // Daftar pendek tidak dipisah antarhalaman, seperti satu blok pada lembar cetak.
        const utuh = butir.length <= 12;
        butir.forEach((x, i) => {
            const akhir = i === butir.length - 1;
            this.p({
                numbering: { reference: acuan, level: x.anak ? 1 : 0, instance: contoh }, alignment: AlignmentType.JUSTIFIED,
                indent: x.anak ? { left: awal + w + 400, hanging: 400 } : { left: awal + w, hanging: w },
                spacing: { after: akhir ? 120 : 40 }, keepNext: b.lekat || (utuh && !akhir) || undefined, children: runs(x.isi),
            });
        });
    }

    /** Label : nilai — kolom label 192 px seperti lembar cetak, nilai berlipat rata di bawahnya. */
    private rincian(b: Extract<BlokCetak, { k: 'rincian' }>) {
        const awal = INDEN[2];
        const titik = b.tanpaTitik ? 0 : 300;
        const titikDua = awal + titik + 2880;
        const nilai = titikDua + 180;
        b.baris.forEach(([label, isi], i) => this.p({
            indent: { left: nilai, hanging: nilai - awal },
            tabStops: [...(titik ? [{ type: TabStopType.LEFT, position: awal + titik }] : []),
                { type: TabStopType.LEFT, position: titikDua }, { type: TabStopType.LEFT, position: nilai }],
            spacing: { after: i === b.baris.length - 1 ? 120 : 40 },
            keepNext: i < b.baris.length - 1 || undefined,
            children: [...(titik ? [new TextRun('•'), tab()] : []), new TextRun(label), tab(), new TextRun(':'), tab(), ...runs(isi)],
        }));
    }

    private tabelBlok(b: Extract<BlokCetak, { k: 'tabel' }>) {
        const lebar = LEBAR_ISI - INDEN[2];
        const kolom = b.kolom.map((k) => Math.round(((parseFloat(k.lebar) || 100 / b.kolom.length) / 100) * lebar));
        // Tabel pendek tidak dipisah antarhalaman — tetap bersama kalimat pengantarnya.
        const pendek = b.baris.length <= 8;
        const baris: TableRow[] = b.kepala ? [kepalaTabel(b.kolom.map((k) => k.judul ?? []), kolom)] : [];
        b.baris.forEach((isi, r) => {
            const kelompok = isi.length === 1 && b.kolom.length > 1;
            baris.push(new TableRow({
                // Baris yang sangat panjang boleh terpotong antarhalaman agar tidak terpangkas Word.
                cantSplit: isi.reduce((n, s) => n + teksPolos(s).length, 0) < 1500 || undefined,
                keepNext: kelompok || (pendek && r < b.baris.length - 1) || undefined,
                children: kelompok
                    ? [selIsi(isi[0], { lebar, rentang: b.kolom.length, latar: 'F8FAFC', tebal: true })]
                    : b.kolom.map((k, j) => selIsi(isi[j] ?? [], {
                        lebar: kolom[j], rata: k.tengah ? AlignmentType.CENTER : k.rata ? AlignmentType.JUSTIFIED : AlignmentType.LEFT,
                    })),
            }));
        });
        this.tabel(new Table({
            rows: baris, width: { size: lebar, type: WidthType.DXA }, columnWidths: kolom, layout: TableLayoutType.FIXED,
            indent: { size: INDEN[2], type: WidthType.DXA }, borders: garis(GARIS), margins: { top: 60, bottom: 60, left: 90, right: 90 },
        }));
    }

    /** No · Fungsi · Nama · Tanda Tangan (FormTtd `daftar`); satu tabel utuh di satu halaman. */
    private tandaTangan(pihak: PenandaWord[]) {
        const kolom = [0.07, 0.25, 0.36, 0.32].map((x) => Math.round(x * LEBAR_ISI));
        const baris = [kepalaTabel([['No'], ['Fungsi'], ['Nama'], ['Tanda Tangan']], kolom)];
        pihak.forEach((x, i) => {
            const g = x.gambar ? this.gambar.get(x.gambar) : undefined;
            baris.push(new TableRow({
                cantSplit: true, keepNext: i < pihak.length - 1 || undefined, height: { value: 960, rule: HeightRule.ATLEAST },
                children: [
                    selIsi([String(i + 1)], { lebar: kolom[0], rata: AlignmentType.CENTER, tengahV: true }),
                    selIsi([x.peran], { lebar: kolom[1], tengahV: true }),
                    selIsi([x.nama], { lebar: kolom[2], tebal: true, tengahV: true }),
                    sel(g ? [gambarRun(g, 200, 48, false, `Tanda tangan ${x.nama || x.peran}`)] : [], { lebar: kolom[3], rata: AlignmentType.CENTER, tengahV: true }),
                ],
            }));
        });
        this.tabel(new Table({
            rows: baris, width: { size: LEBAR_ISI, type: WidthType.DXA }, columnWidths: kolom, layout: TableLayoutType.FIXED,
            borders: garis(GARIS), margins: { top: 60, bottom: 60, left: 90, right: 90 },
        }));
    }
}

/** Kop dokumen terkendali (Components/Lembar) sebagai kepala halaman setiap halaman isi. */
function kop(dok: KopDok, logo: Gambar | null) {
    const kolom = [0.17, 0.5, 0.33].map((x) => Math.round(x * LEBAR_ISI));
    const label = 1380;
    const nilai = 1520;
    const info = (judul: string, isi: TextRun) => new Paragraph({
        style: 'Kop', indent: { left: nilai, hanging: nilai },
        tabStops: [{ type: TabStopType.LEFT, position: label }, { type: TabStopType.LEFT, position: nilai }],
        children: [new TextRun({ text: judul, color: ABU }), tab(), new TextRun({ text: ':', color: GARIS_KOP }), tab(), isi],
    });
    const tebal = (t: string) => new TextRun({ text: t, bold: true, color: NAVY });
    const unit = (judul: string, isi: string) => new Paragraph({
        style: 'Kop', tabStops: [{ type: TabStopType.LEFT, position: 1440 }],
        children: [new TextRun({ text: judul, color: ABU }), tab(), new TextRun(`: ${isi}`)],
    });
    const sel1 = new TableCell({
        width: { size: kolom[0], type: WidthType.DXA }, verticalAlign: VerticalAlignTable.CENTER,
        children: [new Paragraph({
            style: 'Kop', alignment: AlignmentType.CENTER,
            children: logo ? [gambarRun(logo, Math.floor((kolom[0] - 240) / 15), 44, true, 'Logo')] : [new TextRun({ text: dok.perusahaan, bold: true, color: NAVY, size: 18 })],
        })],
    });
    const sel2 = new TableCell({
        width: { size: kolom[1], type: WidthType.DXA }, verticalAlign: VerticalAlignTable.CENTER,
        children: [
            new Paragraph({ style: 'Kop', alignment: AlignmentType.CENTER, children: [new TextRun({ text: dok.jenis.toUpperCase(), bold: true, color: ABU })] }),
            new Paragraph({ style: 'Kop', alignment: AlignmentType.CENTER, spacing: { before: 60 }, children: [new TextRun({ text: dok.judul.toUpperCase(), bold: true, color: NAVY, size: 18 })] }),
        ],
    });
    const sel3 = new TableCell({
        width: { size: kolom[2], type: WidthType.DXA }, verticalAlign: VerticalAlignTable.CENTER,
        children: [
            info('No. Dokumen', tebal(dok.nomor)),
            info('Tgl Penerbitan', tebal(dok.terbit ?? '—')),
            info('Tgl Persetujuan', tebal(dok.setuju ?? '—')),
            info('No. Revisi', tebal(dok.revisi)),
            info('Halaman', new TextRun({ children: [PageNumber.CURRENT, ' dari ', PageNumber.TOTAL_PAGES_IN_SECTION], bold: true, color: NAVY })),
        ],
    });
    return new Header({
        children: [
            new Table({
                rows: [
                    new TableRow({ children: [sel1, sel2, sel3] }),
                    new TableRow({ children: [new TableCell({
                        columnSpan: 3, width: { size: LEBAR_ISI, type: WidthType.DXA }, margins: { top: 90, bottom: 90, left: 120, right: 120 },
                        children: [unit('Divisi', dok.divisi), unit('Departemen', dok.departemen)],
                    })] }),
                ],
                width: { size: LEBAR_ISI, type: WidthType.DXA }, columnWidths: kolom, layout: TableLayoutType.FIXED,
                borders: garis(GARIS_KOP), margins: { top: 120, bottom: 120, left: 120, right: 120 },
            }),
            new Paragraph({ style: 'Kop', spacing: { after: 120 } }),
        ],
    });
}

function halamanJudul(s: NonNullable<OpsiWord['sampul']>, logo: Gambar | null) {
    const kolom = [Math.round(0.34 * LEBAR_ISI), 240];
    kolom.push(LEBAR_ISI - kolom[0] - kolom[1]);
    const besar = (isi: Isi) => isi.map((r) => (typeof r === 'string' ? r.toUpperCase() : { ...r, t: r.t.toUpperCase() }));
    const isi: Array<Paragraph | Table> = [
        new Paragraph({
            alignment: AlignmentType.CENTER, spacing: { before: 600, after: 0, line: 300, lineRule: LineRuleType.AUTO },
            children: s.judul.flatMap((baris, i) => [...(i ? [new TextRun({ break: 1 })] : []), ...runs(besar(baris), { tebal: true, ukuran: 30 })]),
        }),
    ];
    if (logo) isi.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 1100 }, children: [gambarRun(logo, Math.floor((0.6 * LEBAR_ISI) / 15), 128, true, 'Logo')] }));
    isi.push(new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: logo ? 520 : 1100 }, children: [new TextRun({ text: s.perusahaan.toUpperCase(), bold: true, size: 26 })] }));
    // Rincian alamat & pelaksanaan menempel di dasar halaman (tabel mengambang pada tepi bawah).
    isi.push(new Table({
        rows: s.rincian.map(([label, nilai]) => new TableRow({ children: [
            selIsi([label.toUpperCase()], { lebar: kolom[0], tebal: true }),
            selIsi([':'], { lebar: kolom[1], tebal: true }),
            selIsi([nilai || '……'], { lebar: kolom[2], tebal: true }),
        ] })),
        width: { size: LEBAR_ISI, type: WidthType.DXA }, columnWidths: kolom, layout: TableLayoutType.FIXED, borders: TANPA_GARIS,
        margins: { top: 50, bottom: 50, left: 0, right: 60 },
        float: {
            horizontalAnchor: TableAnchorType.MARGIN, verticalAnchor: TableAnchorType.MARGIN,
            relativeVerticalPosition: RelativeVerticalPosition.BOTTOM, absoluteHorizontalPosition: 0, overlap: OverlapType.NEVER,
        },
    }));
    return isi;
}

const tingkatDaftar = (teks: string, format: (typeof LevelFormat)[keyof typeof LevelFormat]): ILevelsOptions[] => [
    { level: 0, format, text: teks, alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 400, hanging: 400 } } } },
    { level: 1, format: LevelFormat.LOWER_LETTER, text: '%2.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 800, hanging: 400 } } } },
];

export async function unduhWord(o: OpsiWord): Promise<void> {
    const url = [o.dok.logo, ...Object.values(o.ttd).flat().map((x) => x.gambar)].filter((x): x is string => !!x);
    const gambar = new Map<string, Gambar>();
    await Promise.all([...new Set(url)].map(async (u) => {
        const g = await muatGambar(u);
        if (g) gambar.set(u, g);
    }));
    const logo = (o.dok.logo && gambar.get(o.dok.logo)) || null;

    const penyusun = new Penyusun(o.ttd, gambar);
    o.blok.forEach((b) => penyusun.tambah(b));

    const halaman = {
        size: { width: A4.lebar, height: A4.tinggi },
        margin: { top: TEPI, right: TEPI, bottom: TEPI, left: TEPI, header: KEPALA, footer: KEPALA },
    };
    const bagian: ISectionOptions[] = [];
    if (o.sampul) bagian.push({ properties: { page: halaman }, children: halamanJudul(o.sampul, logo) });
    bagian.push({
        properties: { page: { ...halaman, margin: { ...halaman.margin, top: ATAS_ISI }, pageNumbers: { start: 1 } } },
        headers: { default: kop(o.dok, logo) },
        children: penyusun.isi,
    });

    const dokumen = new Document({
        creator: 'EQOHSEE',
        title: o.judul,
        compatibility: { doNotExpandShiftReturn: true },
        styles: {
            default: {
                document: { run: { font: 'Arial', size: 20, language: { value: 'id-ID' } }, paragraph: { spacing: { after: 120, line: 276, lineRule: LineRuleType.AUTO } } },
                heading1: { run: { bold: true, allCaps: true, size: 22 }, paragraph: { keepNext: true, keepLines: true, spacing: { before: 240, after: 120 } } },
                heading2: { run: { bold: true, size: 21 }, paragraph: { keepNext: true, keepLines: true, spacing: { before: 120, after: 100 } } },
                heading3: { run: { bold: true, size: 20 }, paragraph: { keepNext: true, keepLines: true, spacing: { before: 60, after: 80 } } },
            },
            paragraphStyles: [
                { id: 'IsiTabel', name: 'Isi Tabel', basedOn: 'Normal', quickFormat: true, run: { size: 19 }, paragraph: { spacing: { after: 0, line: 252, lineRule: LineRuleType.AUTO } } },
                { id: 'Kop', name: 'Kop Dokumen', basedOn: 'Normal', run: { size: 16 }, paragraph: { spacing: { after: 0, line: 240, lineRule: LineRuleType.AUTO } } },
            ],
        },
        numbering: {
            config: [
                { reference: 'angka', levels: tingkatDaftar('%1.', LevelFormat.DECIMAL) },
                { reference: 'kurung', levels: tingkatDaftar('%1)', LevelFormat.DECIMAL) },
                { reference: 'titik', levels: tingkatDaftar('•', LevelFormat.BULLET) },
                ...[...penyusun.awalan].map((a) => ({ reference: `awalan:${a}`, levels: tingkatDaftar(`${a}%1.`, LevelFormat.DECIMAL) })),
            ],
        },
        sections: bagian,
    });

    const berkas = await Packer.toBlob(dokumen);
    const tautan = URL.createObjectURL(berkas);
    const a = document.createElement('a');
    a.href = tautan;
    a.download = `${o.namaBerkas.replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim()}.docx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(tautan), 60_000);
}
