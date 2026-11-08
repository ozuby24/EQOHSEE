<?php

namespace App\Support\Miners;

use App\Models\Company;
use App\Models\Miners\{InduksiOrang, McuOrang, McuRujukan, Permit, Simper};
use App\Support\Berkas;
use App\Support\Waktu;
use Illuminate\Support\Carbon;

/**
 * Isi lembar cetak Miners — sertifikat MCU, surat rujukan, sertifikat
 * induksi. Padanan view dompdf Safe Track (mcus/sertifikat-mcu,
 * mcus/surat-rujukan, induction_registrations/sertifikat); di EQOHSEE
 * lembarnya halaman cetak peramban (Pages/Print/*), dan isinya disusun
 * di sini sebagai larik datar.
 *
 * LARIK DATAR, BUKAN MODEL. Relasi yang kosong menjadi teks kosong di
 * sini, bukan galat di tengah pencetakan — yang bagi pengguna hanya
 * terlihat sebagai lembar putih tanpa keterangan.
 *
 * KOP, NOMOR, DAN PENANDA TANGAN MENGIKUTI IUP PENERBIT dokumennya
 * (`iup_id`), bukan perusahaan yang sedang dilihat. Satu sertifikat yang
 * sama tidak boleh tercetak berkop IUP yang berbeda bagi dua orang yang
 * membukanya.
 */
final class Cetakan
{
    /* ═══════════════════ sertifikat MCU ═══════════════════ */

    /**
     * Sertifikat MCU — formulir "Medical Check Up Certificate" Rev 02.
     *
     * @return array<string,mixed>
     */
    public static function sertifikatMcu(McuOrang $o): array
    {
        $o->loadMissing(['mcu.company', 'hasil', 'departemen', 'pekerja']);

        $iup = $o->mcu?->iup_id ? Company::query()->find($o->mcu->iup_id) : null;

        /* Nomor Mine Permit dan SIMPER terbaru orang ini — dibiarkan
           kosong bila memang belum ada, lebih jujur daripada tanda hubung
           yang terbaca seperti "tidak berlaku". */
        $permit = Permit::withoutGlobalScopes()->where('mcu_orang_id', $o->id)->orderByDesc('id')->value('no_registrasi')
            ?? ($o->pekerja_id ? Permit::withoutGlobalScopes()->where('pekerja_id', $o->pekerja_id)->orderByDesc('id')->value('no_registrasi') : null);

        $simper = $o->pekerja_id
            ? Simper::withoutGlobalScopes()->where('pekerja_id', $o->pekerja_id)->orderByDesc('id')->value('no_simper')
            : null;

        $stempel = StempelValidasi::untuk($o, $iup);

        /* "Awal / Berkala" tercetak dalam istilah formulirnya. Nilai yang
           tidak dikenali dicetak apa adanya supaya tidak ada yang hilang. */
        $status = ['awal' => 'First', 'berkala' => 'Periodic', 'khusus' => 'Special'][mb_strtolower((string) $o->jenis_mcu)]
            ?? (string) $o->jenis_mcu;

        $hasil = mb_strtolower(trim((string) $o->hasil?->nama));

        return [
            'boleh'      => in_array($o->mcu?->status, StempelValidasi::DISETUJUI, true),
            'kop'        => self::kop($iup, 'HSE-IV.026', 'MEDICAL CHECK UP CERTIFICATE', '01 September 2023', '31 Mei 2026', '02'),
            'nama'       => $o->nama,
            'usia'       => $o->usia ? $o->usia.' Tahun' : '',
            'jabatan'    => $o->jabatan ?? '',
            'departemen' => $o->departemen?->nama ?? '',
            'perusahaan' => $o->mcu?->company?->name ?? '',
            'tglMcu'     => self::tgl($o->tanggal_periksa),
            'tglBerikut' => self::tgl($o->berlaku_sampai),
            'status'     => $status,
            'hasil'      => $o->hasil?->nama,
            'centang'    => match ($hasil) {
                'fit'           => 'fit',
                'fit with note' => 'fitnote',
                'unfit'         => 'unfit',
                default         => null,
            },
            'catatan'    => $o->catatan ?? '',
            'risiko'     => $o->level_risiko ?? '',
            'permit'     => $permit ?? '',
            'simper'     => $simper ?? '',
            'dokter'     => $stempel['dokter'],
            'sebutanDokter' => trim('DR '.Penyetuju::singkat($iup?->id)),
            'stempel'    => $stempel,

            /* QR memuat peran, nama penanda tangan, nomor, dan tautan
               pemeriksaan — yang "ditandatangani" ikut terbawa di dalam
               tandanya sendiri, bukan sekadar kode yang tampak resmi. */
            'qr' => self::qrMcu($o, $stempel['dokter']),
        ];
    }

    private static function qrMcu(McuOrang $o, ?string $dokter): string
    {
        $kode = Verifikasi::kode('MC', $o->id);

        return Verifikasi::alamat($kode) ?? implode("\n", [
            'EQOHSEE - SERTIFIKAT MCU',
            'No   : MCU-'.$o->id,
            'Nama : '.$o->nama,
            'Dokter : '.($dokter ?: '-'),
            'Kode : '.$kode,
            'Cek  : '.route('miners.mcu.sertifikat', $o->id),
        ]);
    }

    /* ═══════════════════ surat rujukan ═══════════════════ */

    /**
     * Pengantar rujukan dokter perusahaan, beserta lembar rujukan balik.
     *
     * @return array<string,mixed>
     */
    public static function suratRujukan(McuRujukan $j): array
    {
        $j->loadMissing(['orang.mcu.company', 'user']);

        $o   = $j->orang;
        $iup = $o?->mcu?->iup_id ? Company::query()->find($o->mcu->iup_id) : null;

        return [
            'kop'         => self::kop($iup, 'OHS-4123', '“PENGANTAR RUJUKAN DOKTER PERUSAHAAN (INHOUSE CLINIC)”', '01 Juli 2022', '28 April 2023', '02'),
            'tanggal'     => self::tgl($j->tanggal_surat),
            'tanggalPanjang' => $j->tanggal_surat?->locale('id')->translatedFormat('d F Y') ?? '',
            'perusahaan'  => $o?->mcu?->company?->name ?? '',
            'dokter'      => $j->dokter ?? '',
            'poliklinik'  => $j->poliklinik ?? '',
            'rumahSakit'  => $j->rumah_sakit ?? '',
            'nama'        => $o?->nama ?? '',
            'usia'        => $o?->usia ? $o->usia.' Tahun' : '',
            'jabatan'     => $o?->jabatan ?? '',
            'diagnosis'   => $j->diagnosis_awal ?? '',
            'keterangan'  => $j->keterangan ?? '',
            'kota'        => trim((string) ($iup?->location ?? '')),
            'penandatangan' => StempelValidasi::namaDokter($o, $iup?->id) ?? $j->user?->name ?? '',
            'qr'          => implode("\n", [
                'EQOHSEE - SURAT RUJUKAN',
                'No   : RJK-'.$j->id,
                'Nama : '.($o?->nama ?? '-'),
                'Tanggal : '.self::tgl($j->tanggal_surat),
            ]),
        ];
    }

    /* ═══════════════════ sertifikat induksi ═══════════════════ */

    /**
     * Peserta berhak atas sertifikat bila pendaftarannya SUDAH DISETUJUI
     * dan ia TIDAK dinyatakan Tidak Lulus — SertifikatInduksi::boleh().
     *
     * @return array{0:bool,1:string}
     */
    public static function bolehSertifikatInduksi(?InduksiOrang $o): array
    {
        if (! $o) return [false, 'Peserta induksi tidak ditemukan.'];
        if ($o->induksi?->status !== 'selesai') {
            return [false, 'Pendaftaran induksi ini belum disetujui, jadi sertifikatnya belum bisa terbit.'];
        }
        if ($o->tidakLulus()) {
            return [false, 'Peserta ini dinyatakan TIDAK LULUS induksi, jadi tidak ada sertifikat yang bisa diterbitkan.'];
        }

        return [true, ''];
    }

    /** @return array<string,mixed> */
    public static function sertifikatInduksi(InduksiOrang $o): array
    {
        $o->loadMissing(['induksi.iup', 'pekerja.company', 'pekerja.departemen', 'pekerja.jabatan', 'mcuOrang']);

        $iup = $o->induksi?->iup;
        $p   = $o->pekerja;

        $tanggal = $o->tanggal_induksi ?? $o->induksi?->tanggal;
        $kode    = Verifikasi::kode('ID', $o->id);

        return [
            'nomor'       => $o->nomorSertifikat(),
            'nama'        => mb_strtoupper(trim((string) ($p?->nama ?? $o->mcuOrang?->nama))),
            'noid'        => trim((string) ($p?->no_induk ?? $o->mcuOrang?->no_id)),
            'jabatan'     => mb_strtoupper(trim((string) ($o->mcuOrang?->jabatan ?? $p?->jabatan?->nama))),
            'departemen'  => mb_strtoupper(trim((string) $p?->departemen?->nama)),

            /* PERUSAHAAN ORANGNYA, bukan IUP penyelenggara. Keduanya berbeda
               bagi karyawan mitra, dan yang dicari pembaca sertifikat adalah
               "orang ini bekerja untuk siapa". */
            'perusahaan'  => mb_strtoupper(trim((string) $p?->company?->name)),

            'tanggal'     => $tanggal?->locale('id')->translatedFormat('d F Y') ?? '',
            'lokasi'      => trim((string) $o->lokasi),
            'noDaftar'    => (string) $o->induksi?->no_registrasi,

            'namaIup'     => mb_strtoupper((string) $iup?->name),
            'logo'        => self::logo($iup),
            'warna'       => $iup?->theme_color ?: '#1B4A9C',
            'namaKtt'     => mb_strtoupper((string) $iup?->ktt),
            'jabatanKtt'  => $iup?->jabatan_ktt ?: 'Kepala Teknik Tambang',
            'ttdKtt'      => $iup?->ttd_ktt ? Berkas::terbuka($iup->ttd_ktt) : null,

            /* QR verifikasi bertanda tangan APP_KEY. Selama halaman
               verifikasi publik belum ada, QR memuat keterangannya sendiri. */
            'qr'          => Verifikasi::alamat($kode) ?? implode("\n", [
                'EQOHSEE - SERTIFIKAT INDUKSI',
                'No   : '.$o->nomorSertifikat(),
                'Nama : '.($p?->nama ?? '-'),
                'Kode : '.$kode,
            ]),
            'qrTautan'    => Verifikasi::alamat($kode) !== null,
        ];
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    /**
     * Kop formulir: logo dan nomor dokumen mengikuti IUP penerbit.
     *
     * Nomor dokumennya "<KODE IUP>-<nomor formulir>", bukan nomor
     * perusahaan asal formulir yang disalin — nomor dokumen menyatakan
     * siapa yang menerbitkannya.
     *
     * @return array<string,string|null>
     */
    private static function kop(?Company $iup, string $nomor, string $judul, string $terbit, string $setuju, string $revisi): array
    {
        $kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($iup?->code ?: Penyetuju::singkat($iup?->id)))) ?: 'EQ';

        return [
            'logo'       => self::logo($iup),
            'perusahaan' => $iup?->name ?? '',
            'nomor'      => $kode.'-'.$nomor,
            'judul'      => $judul,
            'terbit'     => $terbit,
            'setuju'     => $setuju,
            'revisi'     => $revisi,
        ];
    }

    private static function logo(?Company $iup): ?string
    {
        $jalur = $iup?->logo_grup ?: $iup?->effectiveLogo();

        return $jalur ? Berkas::terbuka($jalur) : null;
    }

    private static function tgl(?Carbon $t): string
    {
        return $t ? $t->format('d-m-Y') : '';
    }

    /** Waktu cetak dalam waktu tambang. */
    public static function dicetak(): string
    {
        return Waktu::kini()->format('d-m-Y H:i');
    }
}
