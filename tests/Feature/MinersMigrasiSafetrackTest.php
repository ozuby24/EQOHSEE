<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Support\Miners\MasterMiners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengisian data lama oleh migrasi 2026_11_07_100001_miners_safetrack_dasar.
 *
 * Skemanya sudah termigrasi oleh RefreshDatabase; yang diuji di sini
 * LANGKAH PENGISIANNYA, dijalankan ulang atas baris berbentuk lama
 * (langkah 'pjo', dokumen tanpa iup_id, tanpa skema, MCU dengan langkah
 * OHSE). Tidak ada data yang boleh hilang selain langkah MENUNGGU yang
 * tidak lagi ada pada rantainya.
 */
class MinersMigrasiSafetrackTest extends TestCase
{
    use RefreshDatabase;

    private function jalankanPengisian(): void
    {
        $migrasi = require database_path('migrations/2026_11_07_100001_miners_safetrack_dasar.php');

        foreach (['isiIup', 'isiPermit', 'isiMcu', 'isiUnit', 'isiSkema', 'isiAlur'] as $langkah) {
            $m = new \ReflectionMethod($migrasi, $langkah);
            $m->invoke($migrasi);
        }
    }

    private function alur(string $dokumen, int $id, array $langkah): void
    {
        foreach ($langkah as $i => [$peran, $keadaan, $oleh]) {
            DB::table('mnr_alur')->insert([
                'dokumen' => $dokumen, 'dokumen_id' => $id, 'urutan' => $i + 1,
                'peran' => $peran, 'keadaan' => $keadaan, 'user_id' => $oleh,
                'bertindak_pada' => $keadaan === 'menunggu' ? null : now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function test_data_lama_dipetakan_ke_rantai_safe_track(): void
    {
        MasterMiners::pasang();

        $iup   = Company::create(['name' => 'PT IUP Lama', 'code' => 'IL']);
        $mitra = Company::create(['name' => 'PT Mitra Lama', 'izin_type' => 'IUJP', 'parent_id' => $iup->id]);

        $pengaju = User::factory()->create(['company_id' => $mitra->id]);
        $ohse    = User::factory()->create(['company_id' => $iup->id, 'ohse_role' => 'ohse']);
        $ktt     = User::factory()->create(['company_id' => $iup->id, 'lms_role' => 'ktt']);
        $dokter  = User::factory()->create(['company_id' => $iup->id, 'ohse_role' => 'paramedis']);

        $pekerja = DB::table('mnr_pekerja')->insertGetId(['company_id' => $mitra->id, 'nama' => 'Lama', 'status' => 'aktif',
            'created_at' => now(), 'updated_at' => now()]);

        /* MCU yang sudah diperiksa dokter dan menunggu OHSE — langkah
           OHSE tidak ada lagi pada rantai Safe Track. */
        $mcu = DB::table('mnr_mcu')->insertGetId(['company_id' => $mitra->id, 'status' => 'ohse', 'user_id' => $pengaju->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->alur('mcu', $mcu, [['pjo', 'setuju', $ohse->id], ['dokter', 'setuju', $dokter->id], ['ohse', 'menunggu', null]]);
        DB::table('mnr_mcu_orang')->insert(['mcu_id' => $mcu, 'pekerja_id' => $pekerja, 'nama' => 'Lama',
            'berlaku_sampai' => '2027-01-01', 'created_at' => now(), 'updated_at' => now()]);

        /* Permit yang sedang di meja OHSE. */
        $berjalan = DB::table('mnr_permit')->insertGetId(['company_id' => $mitra->id, 'pekerja_id' => $pekerja,
            'status' => 'ohse', 'user_id' => $pengaju->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->alur('permit', $berjalan, [['pjo', 'setuju', $ohse->id], ['ohse', 'menunggu', null], ['ktt', 'menunggu', null]]);

        /* Permit yang diajukan ulang dengan cara lama — langkah PJO masih
           menunggu: belum pernah benar-benar dikirim ke HSE. */
        $belum = DB::table('mnr_permit')->insertGetId(['company_id' => $mitra->id, 'pekerja_id' => $pekerja,
            'status' => 'diajukan', 'user_id' => $pengaju->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->alur('permit', $belum, [['pjo', 'menunggu', null], ['ohse', 'menunggu', null], ['ktt', 'menunggu', null]]);

        /* Permit yang dicabut. */
        $cabut = DB::table('mnr_permit')->insertGetId(['company_id' => $iup->id, 'pekerja_id' => $pekerja,
            'status' => 'dicabut', 'tanggal_cabut' => '2026-10-01', 'alasan_cabut' => 'Mutasi',
            'created_at' => now(), 'updated_at' => now()]);

        $simper = DB::table('mnr_simper')->insertGetId(['company_id' => $mitra->id, 'permit_id' => $berjalan,
            'pekerja_id' => $pekerja, 'status' => 'terbit', 'created_at' => now(), 'updated_at' => now()]);
        $jenisUnit = DB::table('mnr_jenis_unit')->value('id');
        $unit = DB::table('mnr_simper_unit')->insertGetId(['simper_id' => $simper, 'kewenangan' => 'operator',
            'jenis_unit_id' => $jenisUnit, 'asal' => 'baru', 'created_at' => now(), 'updated_at' => now()]);

        $jumlahSebelum = DB::table('mnr_alur')->count();

        $this->jalankanPengisian();

        /* IUP penerbit dan kontraktor. */
        $this->assertSame($iup->id, (int) DB::table('mnr_mcu')->where('id', $mcu)->value('iup_id'));
        $this->assertSame($iup->id, (int) DB::table('mnr_permit')->where('id', $berjalan)->value('iup_id'));
        $this->assertSame($mitra->id, (int) DB::table('mnr_permit')->where('id', $berjalan)->value('kontraktor_id'));
        $this->assertSame($iup->id, (int) DB::table('mnr_simper')->where('id', $simper)->value('iup_id'));

        /* Skema awal dari pemegang peran IUP. */
        $skema = DB::table('mnr_skema')->where('company_id', $iup->id)->first();
        $this->assertNotNull($skema, 'Skema awal tidak dibuat dari pemegang peran.');
        $this->assertSame([$dokter->id, $ohse->id, $ktt->id],
            [(int) $skema->dokter_user_id, (int) $skema->ohse_user_id, (int) $skema->ktt_user_id]);

        /* Tidak ada lagi langkah 'pjo'. */
        $this->assertSame(0, DB::table('mnr_alur')->where('peran', 'pjo')->count());

        /* MCU: langkah OHSE yang menunggu dibuang, dokumennya tuntas di dokter. */
        $this->assertSame(['pengaju', 'dokter'],
            DB::table('mnr_alur')->where('dokumen', 'mcu')->where('dokumen_id', $mcu)->orderBy('urutan')->pluck('peran')->all());
        $this->assertSame('selesai', DB::table('mnr_mcu')->where('id', $mcu)->value('status'));

        /* Permit berjalan: langkahnya tetap, yang menunggu ditugasi skema. */
        $l = DB::table('mnr_alur')->where('dokumen', 'permit')->where('dokumen_id', $berjalan)->orderBy('urutan')->get();
        $this->assertSame(['pengaju', 'ohse', 'ktt'], $l->pluck('peran')->all());
        $this->assertSame($pengaju->id, (int) $l[0]->ditugaskan_user_id, 'Langkah pengaju tidak ditugaskan ke pembuatnya.');
        $this->assertSame($ohse->id, (int) $l[1]->ditugaskan_user_id);
        $this->assertSame($ktt->id, (int) $l[2]->ditugaskan_user_id);
        $this->assertSame('ohse', DB::table('mnr_permit')->where('id', $berjalan)->value('status'));

        /* Yang belum pernah dikirim menjadi draf. */
        $this->assertSame('draf', DB::table('mnr_permit')->where('id', $belum)->value('status'));

        /* Dicabut → tidak aktif, alasannya ikut. */
        $c = DB::table('mnr_permit')->where('id', $cabut)->first();
        $this->assertSame('dicabut', $c->status);
        $this->assertSame(0, (int) $c->aktif);
        $this->assertSame('Mutasi', $c->alasan_nonaktif);

        /* Unit: huruf kewenangan dan type unit pada tabel pasangannya;
           kolom kewenangan lama tetap utuh. */
        $u = DB::table('mnr_simper_unit')->where('id', $unit)->first();
        $this->assertSame('F', $u->authority);
        $this->assertSame('operator', $u->kewenangan);
        $this->assertSame(1, DB::table('mnr_simper_unit_jenis')->where('simper_unit_id', $unit)->count());

        $this->assertSame(12, (int) DB::table('mnr_mcu_orang')->where('mcu_id', $mcu)->value('bulan_berlaku'));

        /* Satu-satunya langkah yang hilang: OHSE pada MCU yang menunggu. */
        $this->assertSame($jumlahSebelum - 1, DB::table('mnr_alur')->count());

        /* Dijalankan dua kali tidak mengubah apa pun lagi. */
        $this->jalankanPengisian();
        $this->assertSame($jumlahSebelum - 1, DB::table('mnr_alur')->count());
        $this->assertSame(1, DB::table('mnr_skema')->where('company_id', $iup->id)->count());
    }
}
