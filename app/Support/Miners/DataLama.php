<?php

namespace App\Support\Miners;

use App\Models\Company;
use App\Models\Miners\Departemen;
use App\Models\Miners\HasilMcu;
use App\Models\Miners\Induksi;
use App\Models\Miners\InduksiOrang;
use App\Models\Miners\Jabatan;
use App\Models\Miners\Kendaraan;
use App\Models\Miners\Mcu;
use App\Models\Miners\McuOrang;
use App\Models\Miners\Pekerja;
use App\Models\Miners\Permit;
use App\Models\Miners\Simper;
use App\Models\Miners\SimperUnit;
use App\Models\User;
use App\Support\Waktu;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * "Upload Data Lama" — DataLamaController, DataLamaMinePermitImport, dan
 * DataLamaSimperImport Safe Track.
 *
 * Memindahkan karyawan yang kartunya SUDAH terbit secara fisik ke dalam
 * sistem tanpa mengulang alur pengajuan-persetujuan:
 *
 *   · Mine Permit — satu baris = satu karyawan. Dibuatkan pekerja, MCU
 *     (hasil "Fit", berlaku sampai "Masa Berlaku"), induksi lulus, dan
 *     Mine Permit terbit — rantai yang sama dengan yang dibuat Safe Track,
 *     karena permit berpijak pada MCU dan induksi. NIK yang sudah ada
 *     dilewati, tidak dibuat dobel.
 *   · SIMPER — satu baris = satu unit (atau tanpa unit). NIK harus sudah
 *     punya Mine Permit; SIMPER terbit miliknya dipakai, atau dibuatkan
 *     bila belum ada. Unit dan Class diisi berdua atau dikosongkan
 *     berdua; unit yang sama dengan kelas yang sama dilewati.
 *
 * Setiap baris yang dibuat bertanda `asal = data_lama`. Setiap baris
 * berkasnya diproses sendiri-sendiri dalam transaksinya sendiri: satu
 * baris yang gagal tidak membatalkan baris lain, dan alasannya dicatat
 * per nomor baris. Pratinjau menjalankan pemeriksaan yang SAMA lalu
 * membatalkan seluruh transaksinya — yang tampil di pratinjau adalah
 * persis yang akan terjadi.
 */
final class DataLama
{
    public const ASAL = 'data_lama';

    public const JENIS = ['permit' => 'Mine Permit', 'simper' => 'SIMPER'];

    public const KOLOM = [
        'permit' => ['NIK', 'Nama', 'Jabatan', 'Departemen', 'Perusahaan', 'Golongan Darah', 'No Telp',
            'Tanggal MCU', 'Masa Berlaku', 'Cakupan Area', 'No Registrasi'],
        'simper' => ['NIK', 'No SIMPOL', 'Exp SIM', 'Unit', 'Class'],
    ];

    /** Contoh pada templat — fiktif, ditulis miring abu-abu. */
    private const CONTOH = [
        'permit' => [['3200000000000001', 'Contoh Pekerja', 'Operator Excavator', 'Produksi',
            'PT Contoh Mitra', 'A', '080000000000', '2026-01-15', '2027-01-15', 'FULL', '']],
        'simper' => [
            ['3200000000000001', '000000000000001', '2028-03-20', 'Excavator', 'F'],
            ['3200000000000001', '', '', 'Light Vehicle', 'F'],
            ['3200000000000002', '000000000000002', '2028-05-10', '', ''],
        ],
    ];

    private ?User $oleh = null;

    /** @var Collection<int,Company> */
    private Collection $lingkup;

    /** @var array<string,Simper> SIMPER per NIK dalam satu unggahan */
    private array $simperPerNik = [];

    public static function templat(string $jenis): Spreadsheet
    {
        $buku = new Spreadsheet();
        $l = $buku->getActiveSheet();
        $l->setTitle(self::JENIS[$jenis]);
        $l->fromArray(self::KOLOM[$jenis], null, 'A1');
        $l->fromArray(self::CONTOH[$jenis], null, 'A2');
        $akhir = $l->getHighestColumn();
        $l->getStyle("A1:{$akhir}1")->getFont()->setBold(true);
        $l->getStyle("A2:{$akhir}".(1 + count(self::CONTOH[$jenis])))->getFont()->setItalic(true)->getColor()->setRGB('999999');
        /* NIK dan nomor ditulis sebagai teks — Excel memotong angka 16 digit. */
        $l->getStyle('A:B')->getNumberFormat()->setFormatCode('@');
        foreach (range(1, count(self::KOLOM[$jenis])) as $k) $l->getColumnDimensionByColumn($k)->setAutoSize(true);
        $l->freezePane('A2');

        return $buku;
    }

    /**
     * Baca dan proses seluruh baris.
     *
     * @return array{berhasil:list<array<string,mixed>>, gagal:list<array{baris:int,sebab:string}>, total:int, pratinjau:bool}
     */
    public function proses(string $jenis, string $jalur, User $oleh, bool $pratinjau): array
    {
        $this->oleh    = $oleh;
        $this->lingkup = AksesMiners::perusahaan($oleh);
        $hasil = ['berhasil' => [], 'gagal' => [], 'total' => 0, 'pratinjau' => $pratinjau];

        try {
            $baris = $this->baris($jalur, self::KOLOM[$jenis]);
        } catch (Throwable $e) {
            $hasil['gagal'][] = ['baris' => 0, 'sebab' => 'Berkas tidak dapat dibaca sebagai Excel: '.mb_substr($e->getMessage(), 0, 160)];

            return $hasil;
        }

        /* Pratinjau: satu transaksi luar yang dibatalkan di akhir, supaya
           baris berikutnya melihat baris sebelumnya persis seperti saat
           disimpan (NIK dobel dalam satu berkas, SIMPER per NIK). */
        if ($pratinjau) DB::beginTransaction();

        try {
            foreach ($baris as $no => $b) {
                if (! array_filter($b, fn ($v) => trim((string) $v) !== '')) continue;
                if ($this->contoh($jenis, $b)) continue;
                $hasil['total']++;
                try {
                    $ok = DB::transaction(fn () => $jenis === 'permit' ? $this->permit($b) : $this->simper($b));
                    if (is_string($ok)) $hasil['gagal'][] = ['baris' => $no, 'sebab' => $ok];
                    else $hasil['berhasil'][] = ['baris' => $no] + $ok;
                } catch (Throwable $e) {
                    report($e);
                    $hasil['gagal'][] = ['baris' => $no, 'sebab' => 'Galat tak terduga: '.mb_substr($e->getMessage(), 0, 160)];
                }
            }
        } finally {
            if ($pratinjau) DB::rollBack();
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $kolom
     * @return array<int,array<string,mixed>> nomor baris Excel → kunci kolom
     */
    private function baris(string $jalur, array $kolom): array
    {
        $lembar = IOFactory::load($jalur)->getActiveSheet()->toArray(null, true, false, false);
        $judul  = array_map(fn ($v) => self::kunci((string) $v), array_shift($lembar) ?? []);
        $keluar = [];
        foreach ($lembar as $i => $isi) {
            $b = [];
            foreach ($kolom as $k) {
                $pos = array_search(self::kunci($k), $judul, true);
                $b[self::kunci($k)] = $pos === false ? null : ($isi[$pos] ?? null);
            }
            $keluar[$i + 2] = $b; // baris 1 = judul kolom
        }

        return $keluar;
    }

    private static function kunci(string $judul): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($judul)), '_');
    }

    /** Baris contoh templat yang tidak dihapus pengunggah dilewati. */
    private function contoh(string $jenis, array $b): bool
    {
        return in_array((string) ($b['nik'] ?? ''), array_column(self::CONTOH[$jenis], 0), true);
    }

    private static function tanggal(mixed $v): ?CarbonImmutable
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && $v > 20000) return CarbonImmutable::instance(TanggalExcel::excelToDateTimeObject((float) $v))->startOfDay();
        $v = trim((string) $v);
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y'] as $f) {
            $t = CarbonImmutable::createFromFormat('!'.$f, $v);
            if ($t && $t->format($f) === $v) return $t;
        }

        return null;
    }

    private function perusahaan(string $nama): ?Company
    {
        $n = mb_strtolower(trim($nama));

        return $this->lingkup->first(fn (Company $c) => mb_strtolower((string) $c->name) === $n || mb_strtolower((string) $c->code) === $n);
    }

    /** @return array<string,mixed>|string */
    private function permit(array $b): array|string
    {
        $nik  = trim((string) ($b['nik'] ?? ''));
        $nama = trim((string) ($b['nama'] ?? ''));
        if ($nik === '' || $nama === '') return 'NIK dan Nama wajib diisi.';

        $fit = HasilMcu::query()->where('layak', true)->orderBy('urutan')->first();
        if (! $fit) return 'Master hasil MCU "Fit" belum ada — isi dulu di Master Data → Hasil MCU.';

        /* Hanya dalam jangkauan pengimpor. mnr_pekerja.nik tidak unik di
           basis data (unik per perusahaan, lihat MinersController::
           validasiPekerja); memeriksa seluruh perusahaan membocorkan —
           lewat mode pratinjau — bahwa NIK itu bekerja di tambang lain,
           dan menolak impor yang sah. */
        if (Pekerja::withoutGlobalScopes()->where('nik', $nik)->whereIn('company_id', $this->lingkup->pluck('id'))->exists()) {
            return 'NIK ini sudah ada dalam jangkauan akun ini (dilewati, tidak dibuat dobel).';
        }

        $namaPt = trim((string) ($b['perusahaan'] ?? ''));
        if ($namaPt === '') return 'Kolom Perusahaan wajib diisi.';
        $pt = $this->perusahaan($namaPt);
        if (! $pt) return "Perusahaan \"{$namaPt}\" tidak ditemukan dalam jangkauan akun ini. Betulkan ejaannya atau tambahkan dulu lewat Master Data → Perusahaan.";
        $iup = Penyetuju::iupDari($pt->id);

        $tglMcu = self::tanggal($b['tanggal_mcu'] ?? null) ?? Waktu::kini()->toImmutable()->startOfDay();
        $habis  = self::tanggal($b['masa_berlaku'] ?? null) ?? $tglMcu->addYear();
        if (filled($b['tanggal_mcu'] ?? null) && ! self::tanggal($b['tanggal_mcu'])) return 'Tanggal MCU tidak terbaca — tulis sebagai 2026-01-15 atau 15-01-2026.';
        if (filled($b['masa_berlaku'] ?? null) && ! self::tanggal($b['masa_berlaku'])) return 'Masa Berlaku tidak terbaca — tulis sebagai 2027-01-15 atau 15-01-2027.';

        $namaDept = trim((string) ($b['departemen'] ?? ''));
        $dept = $namaDept === '' ? null
            : (Departemen::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaDept)])->first()
                ?? Departemen::withoutGlobalScopes()->create(['nama' => $namaDept, 'aktif' => true, 'urutan' => 99]));
        $namaJab = trim((string) ($b['jabatan'] ?? ''));
        $jab = $namaJab === '' ? null : Jabatan::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaJab)])->first();

        $gol = strtoupper(trim((string) ($b['golongan_darah'] ?? '')));
        $cakupan = strtoupper(trim((string) ($b['cakupan_area'] ?? '')));
        $oleh = $this->oleh->id;

        $pk = Pekerja::withoutGlobalScopes()->create([
            'company_id' => $pt->id, 'nama' => $nama, 'nik' => $nik,
            'gol_darah' => in_array($gol, ['A', 'AB', 'B', 'O'], true) ? $gol : null,
            'telepon' => trim((string) ($b['no_telp'] ?? '')) ?: null,
            'departemen_id' => $dept?->id, 'jabatan_id' => $jab?->id,
            'status' => 'aktif', 'dibuat_oleh' => $oleh, 'asal' => self::ASAL,
        ]);
        $mcu = Mcu::withoutGlobalScopes()->create([
            'company_id' => $pt->id, 'iup_id' => $iup, 'tanggal' => $tglMcu, 'kepada' => 'Impor data lama',
            'perihal' => 'Impor data lama', 'status' => 'selesai', 'user_id' => $oleh, 'asal' => self::ASAL,
        ]);
        $mo = McuOrang::withoutGlobalScopes()->create([
            'mcu_id' => $mcu->id, 'pekerja_id' => $pk->id, 'nama' => $nama, 'nik' => $nik, 'jabatan' => $namaJab ?: null,
            'departemen_id' => $dept?->id, 'hasil_id' => $fit->id, 'tanggal_periksa' => $tglMcu,
            'berlaku_sampai' => $habis, 'aktif' => true, 'catatan' => 'Impor data lama',
        ]);
        $ind = Induksi::withoutGlobalScopes()->create([
            'company_id' => $pt->id, 'iup_id' => $iup, 'no_registrasi' => 'LAMA-'.$mo->id, 'tanggal' => $tglMcu,
            'perihal' => 'Impor data lama', 'status' => 'selesai', 'user_id' => $oleh, 'asal' => self::ASAL,
        ]);
        $io = InduksiOrang::withoutGlobalScopes()->create([
            'induksi_id' => $ind->id, 'pekerja_id' => $pk->id, 'mcu_orang_id' => $mo->id,
            'tanggal_induksi' => $tglMcu, 'status' => 'lulus', 'berlaku_sampai' => $habis, 'catatan' => 'Impor data lama',
        ]);
        $nomor = trim((string) ($b['no_registrasi'] ?? ''));
        Permit::withoutGlobalScopes()->create([
            'company_id' => $pt->id, 'iup_id' => $iup, 'kontraktor_id' => $pt->parent_id ? $pt->id : null,
            'pekerja_id' => $pk->id, 'mcu_orang_id' => $mo->id, 'induksi_orang_id' => $io->id,
            'no_registrasi' => $nomor !== '' ? $nomor : ($pt->code ?: 'MP').'.LAMA.'.$mo->id,
            'tanggal' => $tglMcu, 'jenis_kartu' => 'Mine Permit',
            'cakupan_area' => isset(Permit::CAKUPAN[$cakupan]) ? $cakupan : 'FULL',
            'status' => 'terbit', 'aktif' => true, 'berlaku_sampai' => $habis, 'sumber_berlaku' => 'mcu',
            'catatan' => 'Impor data lama', 'user_id' => $oleh, 'asal' => self::ASAL,
        ]);

        return ['nik' => $nik, 'nama' => $nama, 'keterangan' => $pt->name.' · berlaku s/d '.$habis->format('d-m-Y')];
    }

    /** @return array<string,mixed>|string */
    private function simper(array $b): array|string
    {
        $nik = trim((string) ($b['nik'] ?? ''));
        if ($nik === '') return 'NIK wajib diisi.';

        $namaUnit = trim((string) ($b['unit'] ?? ''));
        $kelas    = strtoupper(trim((string) ($b['class'] ?? '')));
        $adaUnit  = $namaUnit !== '' || $kelas !== '';
        $unit     = null;
        if ($adaUnit) {
            if ($namaUnit === '' || $kelas === '') return 'Unit dan Class harus diisi berdua, atau dikosongkan berdua (unit ditambahkan belakangan di detail SIMPER).';
            if (! in_array($kelas, ['F', 'R', 'P', 'I'], true)) return 'Class harus salah satu dari F, R, P, I.';
            $unit = Kendaraan::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', [mb_strtolower($namaUnit)])->first();
            if (! $unit) return "Unit \"{$namaUnit}\" tidak ditemukan di Master Unit. Tambahkan dulu, betulkan ejaannya, atau kosongkan Unit/Class.";
        }

        $s = $this->simperPerNik[$nik] ?? null;
        if (! $s) {
            $pk = Pekerja::withoutGlobalScopes()->where('nik', $nik)->whereIn('company_id', $this->lingkup->pluck('id'))->first();
            if (! $pk) return 'NIK ini belum ada di sistem (atau di luar jangkauan akun ini) — unggah dulu lewat Upload Data Lama Mine Permit.';
            $permit = Permit::withoutGlobalScopes()->where('pekerja_id', $pk->id)->orderByDesc('id')->first();
            if (! $permit) return 'Karyawan dengan NIK ini belum punya Mine Permit — unggah dulu lewat Upload Data Lama Mine Permit.';

            $s = Simper::withoutGlobalScopes()->where('permit_id', $permit->id)->where('status', 'terbit')->orderByDesc('id')->first();
            if (! $s) {
                $exp = self::tanggal($b['exp_sim'] ?? null);
                if (filled($b['exp_sim'] ?? null) && ! $exp) return 'Exp SIM tidak terbaca — tulis sebagai 2028-03-20 atau 20-03-2028.';
                $s = Simper::withoutGlobalScopes()->create([
                    'company_id' => $permit->company_id, 'iup_id' => $permit->iup_id, 'permit_id' => $permit->id,
                    'pekerja_id' => $pk->id, 'no_simper' => 'LAMA-'.$permit->id, 'tanggal' => Waktu::kini()->startOfDay(),
                    'no_simpol' => trim((string) ($b['no_simpol'] ?? '')) ?: null,
                    'simpol_berlaku_sampai' => $exp, 'simpol_sumber' => $exp ? 'manual' : null,
                    'status' => 'terbit', 'aktif' => true, 'catatan' => 'Impor data lama',
                    'user_id' => $this->oleh->id, 'asal' => self::ASAL,
                ]);
            }
            $this->simperPerNik[$nik] = $s;
        }

        if (! $unit) return ['nik' => $nik, 'nama' => $s->pekerja?->nama, 'keterangan' => 'SIMPER '.$s->no_simper.' · tanpa unit'];

        if (SimperUnit::withoutGlobalScopes()->where('simper_id', $s->id)->where('kendaraan_id', $unit->id)->where('authority', $kelas)->exists()) {
            return "Unit \"{$namaUnit}\" kelas {$kelas} sudah tercatat untuk NIK ini (dilewati).";
        }
        SimperUnit::withoutGlobalScopes()->create(['simper_id' => $s->id, 'kendaraan_id' => $unit->id, 'authority' => $kelas, 'asal' => 'baru']);

        return ['nik' => $nik, 'nama' => $s->pekerja?->nama, 'keterangan' => 'SIMPER '.$s->no_simper.' · '.$unit->nama.' ('.$kelas.')'];
    }
}
