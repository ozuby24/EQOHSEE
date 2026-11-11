<?php

namespace App\Support\Miners\Kartu;

use App\Models\Company;
use App\Models\Miners\{JenisUnit, Kendaraan, Permit, Simper, SimperAjuanUnit, SimperUnit};
use App\Support\Berkas;
use App\Support\Miners\Verifikasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Isi kartu Mine Permit / SIMPER — DataKartu::dariPermit Safe Track.
 *
 * SATU KARTU CR80 (54 × 86 mm tegak): DEPAN = Mine Permit (IUP penerbit,
 * pita jenis kartu berwarna akses, nama, foto, nomor induk, jabatan,
 * perusahaan, berlaku, emergency call), BELAKANG = SIMPER (nomor register,
 * SIM Pol dan masa berlakunya, tabel 5 × 2 unit + kelas F/R/P/I, legenda,
 * QR verifikasi, pelanggaran, tanda tangan KTT). Pemegang Mine Permit
 * tanpa SIMPER mendapat belakang yang tabelnya dicoret.
 *
 * Merek: hanya identitas IUP penerbit dari Master Data → Perusahaan
 * (logo grup, nama, KTT, tanda tangan, channel darurat). Tidak ada merek
 * aplikasi lain yang tercetak.
 */
final class DataKartu
{
    public const ARTI_KELAS = ['F' => 'PENUH', 'R' => 'KEADAAN DARURAT', 'P' => 'PERCOBAAN', 'I' => 'PELATIHAN'];

    public const NAMA_KELAS = ['F' => 'FULL', 'R' => 'RESTRICTED', 'P' => 'PROBATION', 'I' => 'INSTRUCTOR'];

    public const BARIS_UNIT = 10;

    /* Lebar petak (pt) dan ukuran huruf, diukur Safe Track pada kartu nyata. */
    private const LEBAR_NAMA_PT = 144.57;
    private const UKURAN_NAMA_PT = 8.4;
    private const UKURAN_NAMA_MIN = 5.0;
    private const SPASI_NAMA_PT = 0.2;
    private const LEBAR_UNIT_PT = 41.39;
    private const UKURAN_UNIT_PT = 4.2;
    private const UKURAN_UNIT_MIN = 3.0;

    private const BULAN = [1 => 'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS',
        'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'];

    /** @return array<string,mixed> */
    public static function dariPermit(Permit $p): array
    {
        $p->loadMissing(['iup', 'company', 'kontraktor', 'pekerja', 'mcuOrang']);

        $iup        = self::iupPenerbit($p);
        $perusahaan = $p->kontraktor ?? $p->pekerja?->company ?? $p->company;
        $simper     = self::simper($p);
        $unit       = $simper ? self::unit($simper) : self::barisKosong();

        $warna = WarnaAkses::sah($p->kode_warna) ? $p->kode_warna : WarnaAkses::untukKartu($unit, (bool) $simper);
        $nama  = mb_strtoupper((string) ($p->pekerja?->nama ?? $p->mcuOrang?->nama));

        return [
            'id'              => $p->id,
            'nomor'           => $p->no_registrasi,
            'warna'           => $iup?->theme_color ?: '#1B4A9C',
            'logo_grup'       => self::gambarTerbuka($iup?->logo_grup ?: $iup?->effectiveLogo()),
            'nama_iup'        => mb_strtoupper((string) $iup?->name),
            'nama_iup_pendek' => mb_strtoupper((string) ($iup?->nama_pendek ?: $iup?->name)),
            'akses'           => WarnaAkses::CAKUPAN_AREA[strtoupper((string) $p->cakupan_area)] ?? 'FULL ACCESS',
            'jenis_kartu'     => mb_strtoupper($p->jenis_kartu ?: 'Mine Permit'),
            'nama'            => $nama,
            'ukuran_nama'     => LebarTeks::ukuranMuat($nama, self::LEBAR_NAMA_PT, self::UKURAN_NAMA_PT, self::UKURAN_NAMA_MIN, true, self::SPASI_NAMA_PT),
            'foto'            => $p->foto ? Berkas::url($p, 'mnf') : null,
            'nomor_induk'     => $p->no_registrasi ?: $p->pekerja?->no_induk,
            'jabatan'         => mb_strtoupper((string) ($p->mcuOrang?->jabatan ?? '')),
            'perusahaan'      => mb_strtoupper((string) ($perusahaan?->nama_pendek ?: $perusahaan?->name)),
            'berlaku'         => self::tanggal($p->habisEfektif()),
            'channel'         => (string) $iup?->channel_darurat,
            'nama_ktt'        => mb_strtoupper((string) $iup?->ktt),
            'jabatan_ktt'     => $iup?->jabatan_ktt ?: 'Kepala Teknik Tambang',
            'ttd_ktt'         => self::gambarTertutup($iup?->ttd_ktt),
            'punya_simper'    => (bool) $simper,
            'no_register'     => $simper ? self::nomorRegister($simper, $iup) : '',
            'no_simpol'       => $simper?->no_simpol ?? '',
            'exp_simpol'      => $simper ? ($simper->habisEfektif()?->format('d-m-Y') ?? 'SEUMUR HIDUP') : '',
            'unit'            => $unit,
            'arti_kelas'      => self::ARTI_KELAS,
            'nama_kelas'      => self::NAMA_KELAS,
            'warna_akses'     => $warna,
            'label_akses'     => WarnaAkses::label($warna),
            'hex_akses'       => WarnaAkses::hex($warna),
            'verifikasi'      => Verifikasi::alamat(Verifikasi::kode('MP', (int) $p->id)),
        ];
    }

    /** IUP penerbit: iup_id permit, induk perusahaannya, lalu perusahaannya sendiri. */
    public static function iupPenerbit(Permit $p): ?Company
    {
        if ($p->iup) return $p->iup;

        $c = $p->company;

        return $c?->parent_id ? Company::query()->find($c->parent_id) : $c;
    }

    /** SIMPER yang tercetak: terbit dan aktif, terbaru. */
    public static function simper(Permit $p): ?Simper
    {
        return Simper::withoutGlobalScopes()->where('permit_id', $p->id)->where('status', 'terbit')
            ->where('aktif', true)->orderByDesc('id')->first();
    }

    /**
     * Baris unit 5 × 2: unit SIMPER lalu unit dari Penambahan yang disetujui.
     * Master unit dibaca TANPA batas perusahaan — kartu karyawan mitra
     * memakai master milik IUP.
     *
     * @return list<array<string,mixed>>
     */
    public static function unit(Simper $s): array
    {
        $baris = SimperUnit::withoutGlobalScopes()->where('simper_id', $s->id)->orderBy('id')->get()
            ->concat($s->unitTambahan());

        $kendaraan = Kendaraan::withoutGlobalScopes()->whereIn('id', $baris->pluck('kendaraan_id')->filter())->get()->keyBy('id');

        $isi = [];
        foreach ($baris as $u) {
            $jenis = self::namaJenis($u);
            $k     = $kendaraan[$u->kendaraan_id] ?? null;
            $isi   = array_merge($isi, self::barisUnit((string) $k?->nama, $jenis, (string) $k?->kode, (string) $u->authority));
        }

        $hasil = self::barisKosong();
        foreach (array_slice($isi, 0, self::BARIS_UNIT) as $i => $b) $hasil[$i] = $b;

        return $hasil;
    }

    private static function namaJenis(SimperUnit|SimperAjuanUnit $u): string
    {
        $tabel = $u instanceof SimperUnit ? 'mnr_simper_unit_jenis' : 'mnr_simper_ajuan_unit_jenis';
        $kunci = $u instanceof SimperUnit ? 'simper_unit_id' : 'ajuan_unit_id';

        $id = \Illuminate\Support\Facades\DB::table($tabel)->where($kunci, $u->id)->pluck('jenis_unit_id');
        if ($id->isEmpty() && $u->jenis_unit_id) $id = collect([$u->jenis_unit_id]);

        return JenisUnit::withoutGlobalScopes()->whereIn('id', $id)->orderBy('nama')->pluck('nama')->join(', ');
    }

    /** @return list<array<string,mixed>> */
    private static function barisUnit(string $kategori, string $jenis, string $kode, string $kelas): array
    {
        $teks = self::teksEquipment($kategori, $jenis, $kode);
        if ($teks === '') return [];

        $buat = fn (string $t) => [
            'nama' => $t, 'kelas' => strtoupper(trim($kelas)), 'kode' => strtoupper(trim($kode)), 'kategori' => trim($kategori),
            'ukuran' => LebarTeks::ukuranMuat($t, self::LEBAR_UNIT_PT, self::UKURAN_UNIT_PT, self::UKURAN_UNIT_MIN),
        ];

        $type = self::typeBerarti($kategori, $jenis, $kode);
        $muat = LebarTeks::lebar($teks, self::UKURAN_UNIT_PT) <= self::LEBAR_UNIT_PT;

        /* Banyak type tidak muat satu sel: satu baris per type, berkode. */
        if (count($type) > 1 && trim($kode) !== '' && ! $muat) {
            return array_map(fn ($t) => $buat(trim($kode).' '.$t), $type);
        }

        return [$buat($teks)];
    }

    /** Teks kolom Equipments — kode unit + type ("DT Volvo 40T"), atau kategori + type bila tanpa kode. */
    public static function teksEquipment(string $kategori, string $jenis, string $kode): string
    {
        $type = self::typeBerarti($kategori, $jenis, $kode);

        if (trim($kode) === '') {
            $j = implode(', ', $type);
            $gabung = trim($kategori) !== '' && $j !== '' ? trim($kategori).' '.$j : ($j !== '' ? $j : trim($kategori));

            return mb_strtoupper($gabung);
        }

        return $type ? trim($kode).' '.implode(', ', $type) : trim($kode);
    }

    /** @return list<string> type yang menambah arti (bukan pengulangan kategori/kode). */
    private static function typeBerarti(string $kategori, string $jenis, string $kode): array
    {
        $rapi = fn ($t) => strtolower((string) preg_replace('/\s+/', ' ', trim((string) $t)));
        $inti = fn ($t) => (string) preg_replace('/\s+\d+$/', '', $rapi($t));

        $hasil = [];
        foreach (explode(',', $jenis) as $t) {
            $t = trim((string) preg_replace('/\s+/', ' ', $t));
            if ($t === '' || $inti($t) === $inti($kategori)) continue;
            if (trim($kode) !== '' && str_contains($rapi($kode), $rapi($t))) continue;
            $hasil[$rapi($t)] = $t;
        }

        return array_values($hasil);
    }

    /** `0001/KODE/mm/yyyy` dari angka nomor SIMPER dan tanggalnya. */
    private static function nomorRegister(Simper $s, ?Company $iup): string
    {
        $nomor = trim((string) $s->no_simper);
        if ($nomor === '' || ! preg_match('/(\d+)/', $nomor, $c)) return $nomor;

        $t = $s->tanggal ?? $s->created_at;

        return sprintf('%04d/%s/%s/%s', (int) $c[1], (string) $iup?->code, $t?->format('m') ?? date('m'), $t?->format('Y') ?? date('Y'));
    }

    /** @return list<array<string,mixed>> */
    private static function barisKosong(): array
    {
        return array_fill(0, self::BARIS_UNIT, ['nama' => '', 'kelas' => '', 'kode' => '', 'kategori' => '', 'ukuran' => self::UKURAN_UNIT_PT]);
    }

    private static function tanggal(?Carbon $t): string
    {
        return $t ? $t->day.' '.self::BULAN[$t->month].' '.$t->year : '';
    }

    private static function gambarTerbuka(?string $jalur): ?string
    {
        return $jalur ? Berkas::terbuka($jalur) : null;
    }

    /**
     * Tanda tangan KTT disimpan di disk TERTUTUP (dapat dipakai memalsu
     * surat), lalu disisipkan ke halaman cetak sebagai data URI — tidak
     * pernah punya alamat yang dapat dibuka langsung.
     */
    private static function gambarTertutup(?string $jalur): ?string
    {
        if (! $jalur || ! Storage::disk(Berkas::TERTUTUP)->exists($jalur)) return null;

        $isi  = Storage::disk(Berkas::TERTUTUP)->get($jalur);
        $mime = Storage::disk(Berkas::TERTUTUP)->mimeType($jalur) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) $isi);
    }
}
