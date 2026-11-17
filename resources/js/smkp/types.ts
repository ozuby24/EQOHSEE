/** Bentuk data bersama yang dikirim server ke beberapa halaman. */

export interface RingkasAudit {
    id: number;
    judul: string;
    tahun: number;
    status: string;
    labelStatus: string;
    tahap: number;
    labelTahap: string;
    ketua: string | null;
    mulai: string | null;
    selesai: string | null;
    periode: string;
    perusahaan: {
        id: number | null;
        nama: string;
        kategori: string | null;
        /** Kode kategori mentah (induk/pjp/sister); `kategori` berisi labelnya. */
        kodeKategori: string | null;
        izin: string | null;
        komoditas: string | null;
        alamat: string | null;
        ktt: string | null;
        logo: string | null;
    };
}

export interface KopDok {
    jenis: string;
    judul: string;
    nomor: string;
    terbit: string | null;
    setuju: string | null;
    revisi: string;
    divisi: string;
    departemen: string;
    logo: string | null;
    perusahaan: string;
}

/** Isian tanda tangan formulir audit SMKP (halaman cetak). 'pjo' untuk perusahaan jasa pertambangan; 'auditor2'… anggota tim audit pada Laporan Audit. */
export type PihakTtd = 'ketua' | 'ktt' | 'pjo' | 'auditor2' | 'auditor3' | 'auditor4' | 'auditor5' | 'auditor6' | 'auditor7' | 'auditor8';
export interface TtdForm {
    ketua: string;
    ktt: string;
    /** bagian → pihak → tanggal penandatanganan */
    tanggal: Record<string, Partial<Record<PihakTtd, { nilai: string; teks: string }>>>;
    gambar: Record<PihakTtd, string | null>;
    /** bagian tindak lanjut KTS → pihak yang sudah membubuhkan tanda tangannya */
    bubuh: Record<string, Partial<Record<PihakTtd, true>>>;
    bisaSunting: boolean;
    bisaGambar: Record<PihakTtd, boolean>;
    bisaBubuh: Record<PihakTtd, boolean>;
    url: string;
}

export interface Picker {
    halaman: string;
    tahun: number;
    daftarTahun: number[];
    perusahaan: { id: number; nama: string | null; kategori: string | null; logo: string | null };
    daftarPerusahaan: Array<{ id: number; nama: string; kategori: string }>;
    status: string;
    labelStatus: string;
    judul: string | null;
}

export interface Halaman<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    from: number | null;
    to: number | null;
    total: number;
    current_page: number;
    last_page: number;
}

/* ---------- Asisten AI (jawaban /asisten/*, lihat app/Services/Asisten) ---------- */

/** Status Asisten AI yang dibagikan server — kunci API tidak pernah ikut. */
export interface AsistenBersama {
    aktif: boolean;
    model: string;
}

export interface SaranButir {
    tugas: 'butir';
    kode: string;
    maks: number;
    nilai: { saran: number | null; na: boolean; v: string; keyakinan: 'rendah' | 'sedang' | 'tinggi'; alasan: string };
    bukti: Array<{ jenis: string; label: string; uraian: string }>;
    pertanyaan: string[];
    draf_temuan: string;
    catatan: string;
    model: string;
}

export interface AksiRtp {
    tindakan: string;
    pic: string;
    hari: number;
    bukti: string;
}

export interface IsianTemuanAi {
    akar_masalah: string;
    tindakan: string;
    penanggung_jawab: string;
    target_selesai: string;
    verifikasi: string;
}

export interface SaranTemuan {
    tugas: 'temuan';
    id: number;
    kode: string;
    akar: { kategori: string; label: string; mengapa: Array<{ tanya: string; jawab: string }>; pernyataan: string };
    koreksi: string;
    korektif: AksiRtp[];
    preventif: AksiRtp[];
    target: { hari: number; tanggal: string };
    bukti_penutupan: string[];
    risiko: { tingkat: string; label: string; alasan: string };
    isian: IsianTemuanAi;
    model: string;
}

export interface IsianProgramAi {
    param: string;
    opsi: string;
    durasi: string;
    sasaran: string;
    target: string;
}

export interface SaranProgram {
    tugas: 'program';
    ringkasan: string;
    program: Array<{
        prioritas: number;
        judul: string;
        parameter: string;
        nama_parameter: string;
        alasan: string;
        kegiatan: string[];
        indikator: string;
        pic: string;
        durasi: string;
        target_waktu: string;
        isian: IsianProgramAi;
    }>;
    model: string;
}

/** Satu tingkat nilai pada kriteria penilaian butir SMKP (Kepdirjen 185.K/2019). */
export interface TingkatRubrik {
    n: number;
    teks: string;
}

/**
 * Kriteria penilaian resmi satu butir. Butir II.5 dan III.3 punya dua versi
 * (pemegang IUP dan pemegang IUJP) di `varian`; selebihnya di `nilai`.
 */
export interface Rubrik {
    hal: number;
    nilai?: TingkatRubrik[];
    na?: string | null;
    catatan?: string;
    varian?: Array<{ untuk: 'iup' | 'iujp'; label: string; nilai: TingkatRubrik[]; na: string | null }>;
}

/** Status verifikasi berkas bukti kelengkapan audit. */
export type StatusBukti = 'menunggu' | 'diterima' | 'ditolak';

/** Satu berkas bukti kelengkapan untuk satu butir kriteria SMKP. */
export interface BerkasBukti {
    id: number;
    kode: string;
    nama: string;
    ukuran: number;
    gambar: boolean;
    keterangan: string | null;
    status: StatusBukti;
    catatan: string | null;
    oleh: string | null;
    waktu: string | null;
    verifikator: string | null;
    diverifikasi: string | null;
    url: string;
    bisaHapus: boolean;
}

/** Batas unggah berkas bukti (jumlah per unggahan, ukuran per berkas, ekstensi). */
export interface BatasBerkas {
    jumlah: number;
    kb: number;
    ekstensi: string[];
}

/** Dokumen & sampel yang diminta Rencana Audit untuk satu sub-elemen. */
export interface DokumenDiminta {
    metode: string;
    sampel: string;
}
