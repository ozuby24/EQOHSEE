<?php

namespace Tests\Feature\Concerns;

use App\Models\Company;
use App\Models\Miners\{HasilMcu, Induksi, InduksiOrang, JenisUnit, Kendaraan, Mcu, McuOrang, Pekerja, Permit, PermitBerkas, Simper, SimperUnit, Skema};
use App\Models\User;
use App\Support\Miners\MasterMiners;
use App\Support\Waktu;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Susunan bersama uji Miners bagian 2: satu IUP berkode, satu mitra
 * (IUJP) anaknya, penyetuju bernama per IUP, dan pembuat dokumen siap
 * pakai yang melewati jalur aplikasi.
 */
trait SiapanMiners
{
    protected Company $iup;
    protected Company $mitra;
    protected User $admin;
    protected User $ohse;
    protected User $ktt;
    protected User $dokter;
    protected User $pengaju;

    protected function siapkan(): void
    {
        Storage::fake('local');
        MasterMiners::pasang();

        $this->iup   = Company::create(['name' => 'PT Tambang Uji', 'code' => 'TBU', 'izin_type' => 'IUP Operasi Produksi']);
        $this->mitra = Company::create(['name' => 'PT Mitra Uji', 'code' => 'MTU', 'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);

        $this->admin   = User::factory()->create(['is_admin' => true]);
        $this->ohse    = User::factory()->create(['company_id' => $this->iup->id, 'ohse_role' => 'ohse']);
        $this->ktt     = User::factory()->create(['company_id' => $this->iup->id, 'lms_role' => 'ktt']);
        $this->dokter  = User::factory()->create(['company_id' => $this->iup->id, 'ohse_role' => 'paramedis']);
        $this->pengaju = User::factory()->create(['company_id' => $this->mitra->id]);

        Skema::create([
            'company_id'     => $this->iup->id,
            'dokter_user_id' => $this->dokter->id,
            'ohse_user_id'   => $this->ohse->id,
            'ktt_user_id'    => $this->ktt->id,
        ]);
    }

    protected function hasil(string $nama): int
    {
        return (int) HasilMcu::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])->value('id');
    }

    protected function departemen(): int
    {
        return (int) \App\Models\Miners\Departemen::withoutGlobalScopes()->value('id');
    }

    protected function dokumenPdf(string $nama = 'berkas.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 30, 'application/pdf');
    }

    /** Isian "Add Manpower" yang sah. @return array<string,mixed> */
    protected function isianManpower(array $ganti = []): array
    {
        return $ganti + [
            'nama'            => 'Karyawan '.Str::random(5),
            'nik'             => (string) random_int(6400000000000000, 6499999999999999),
            'usia'            => 30,
            'tanggal_periksa' => '2026-03-10',
            'jabatan'         => 'Operator',
            'departemen_id'   => $this->departemen(),
            'jenis_mcu'       => 'Awal',
            'level_risiko'    => 'Sedang',
            'berkas_hasil'    => $this->dokumenPdf('hasil.pdf'),
        ];
    }

    /** Surat MCU buatan mitra, lewat rute aplikasi. */
    protected function suratMcu(?User $oleh = null): Mcu
    {
        $oleh ??= $this->pengaju;

        $this->actingAs($oleh)->post('/miners/mcu', [
            'tanggal' => '2026-03-10', 'kepada' => 'Dokter Uji', 'perihal' => 'Permohonan Sertifikat MCU',
            'company_id' => $oleh->company_id,
        ])->assertSessionHasNoErrors();

        return Mcu::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /**
     * MCU yang sudah disetujui dokter, berisi satu orang dengan hasil
     * tertentu — langsung lewat model, untuk uji yang bukan tentang MCU.
     */
    protected function mcuSelesai(Company $c, string $hasil = 'Fit', ?string $berlaku = null, ?Pekerja $pk = null): McuOrang
    {
        $pk ??= Pekerja::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'nama' => 'Pekerja '.Str::random(4), 'nik' => (string) random_int(1000000000000000, 9999999999999999), 'status' => 'aktif',
        ]);

        $m = Mcu::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tanggal' => Waktu::kini()->subMonth()->startOfDay(), 'kepada' => 'Klinik',
            'status' => 'selesai', 'user_id' => $this->pengaju->id,
        ]);

        return McuOrang::create([
            'mcu_id' => $m->id, 'pekerja_id' => $pk->id, 'nama' => $pk->nama, 'nik' => $pk->nik, 'jabatan' => 'Operator',
            'hasil_id' => $this->hasil($hasil), 'tanggal_periksa' => Waktu::kini()->subMonth()->startOfDay(),
            'berlaku_sampai' => $berlaku ?? Waktu::kini()->addMonths(11)->toDateString(), 'aktif' => true,
        ]);
    }

    /** Peserta induksi yang pendaftarannya disetujui. */
    protected function induksiSelesai(McuOrang $o, string $status = 'lulus', string $statusInduksi = 'selesai'): InduksiOrang
    {
        $c = $o->pekerja?->company_id ?? $this->mitra->id;

        $ind = Induksi::withoutGlobalScopes()->create([
            'company_id' => $c, 'tanggal' => Waktu::kini()->startOfDay(), 'status' => $statusInduksi,
            'user_id' => $this->pengaju->id, 'iup_id' => $this->iup->id,
        ]);

        return InduksiOrang::create([
            'induksi_id' => $ind->id, 'pekerja_id' => $o->pekerja_id, 'mcu_orang_id' => $o->id,
            'tanggal_induksi' => Waktu::kini()->startOfDay(), 'status' => $status,
        ]);
    }

    /** Isian "add Permit" yang sah bagi peserta induksi ini. @return array<string,mixed> */
    protected function isianPermit(InduksiOrang $io, array $ganti = []): array
    {
        return $ganti + [
            'induksi_orang_id' => $io->id,
            'iup_id'           => $this->iup->id,
            'kontraktor_id'    => $this->mitra->id,
            'jenis_kartu'      => 'Mine Permit',
            'cakupan_area'     => 'FULL',
            'departemen_id'    => $this->departemen(),
            'tanggal_lahir'    => '1990-05-01',
            'gol_darah'        => 'O',
            'foto'             => UploadedFile::fake()->image('foto.jpg'),
            'ktp'              => $this->dokumenPdf('ktp.pdf'),
            'permohonan'       => $this->dokumenPdf('mohon.pdf'),
        ];
    }

    /** Mine Permit mitra yang sudah terbit, mengikuti MCU-nya. */
    protected function permitTerbit(?McuOrang $o = null): Permit
    {
        $o ??= $this->mcuSelesai($this->mitra);
        $io = $this->induksiSelesai($o);

        $p = Permit::withoutGlobalScopes()->create([
            'company_id' => $this->mitra->id, 'iup_id' => $this->iup->id, 'kontraktor_id' => $this->mitra->id,
            'pekerja_id' => $o->pekerja_id, 'mcu_orang_id' => $o->id, 'induksi_orang_id' => $io->id,
            'tanggal' => Waktu::kini()->subMonth()->startOfDay(), 'status' => 'terbit', 'aktif' => true,
            'jenis_kartu' => 'Mine Permit', 'cakupan_area' => 'FULL', 'no_registrasi' => 'TBU.MTU.UJI'.$o->id,
            'berlaku_sampai' => $o->berlaku_sampai, 'sumber_berlaku' => 'mcu', 'user_id' => $this->pengaju->id,
        ]);

        return $p;
    }

    protected function lampiranSpdk(Permit $p): void
    {
        PermitBerkas::create(['permit_id' => $p->id, 'jenis' => 'spdk', 'berkas' => 'miners/permit/spdk.pdf', 'catatan' => 'SPDK']);
    }

    /** PDF kecil berisi teks — untuk uji deteksi masa berlaku SIM. */
    protected function pdfTeks(string $teks, string $nama = 'sim.pdf'): UploadedFile
    {
        $isi = "%PDF-1.4\n1 0 obj << /Length 60 >>\nstream\nBT /F1 12 Tf 10 10 Td ({$teks}) Tj ET\nendstream\nendobj\n%%EOF";

        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    /** Isian "Add Manpower Simper" yang sah. @return array<string,mixed> */
    protected function isianSimper(Permit $p, array $ganti = []): array
    {
        return $ganti + [
            'permit_id'             => $p->id,
            'no_simpol'             => '1234-5678-000001',
            'jenis_simpol'          => 'SIM B1 Umum',
            'simpol_berlaku_sampai' => Waktu::kini()->addYears(2)->toDateString(),
            'berkas_simpol'         => $this->dokumenPdf('sim.pdf'),
            'berkas_ddt'            => $this->dokumenPdf('ddt.pdf'),
            'berkas_permohonan'     => $this->dokumenPdf('mohon.pdf'),
            'berkas_pengalaman'     => $this->dokumenPdf('alaman.pdf'),
        ];
    }

    /** SIMPER terbit berunit satu, tanggal SIM dua tahun lagi. */
    protected function simperTerbit(?Permit $p = null, string $kendaraan = 'Dump Truck'): Simper
    {
        $p ??= $this->permitTerbit();

        $s = Simper::withoutGlobalScopes()->create([
            'company_id' => $p->company_id, 'iup_id' => $p->iup_id, 'permit_id' => $p->id, 'pekerja_id' => $p->pekerja_id,
            'tanggal' => Waktu::kini()->startOfDay(), 'status' => 'terbit', 'aktif' => true,
            'no_simpol' => 'SIM-LAMA-1', 'jenis_simpol' => 'SIM B1 Umum',
            'simpol_berlaku_sampai' => Waktu::kini()->addYears(2)->startOfDay(), 'simpol_sumber' => 'manual',
            'berkas_simpol' => 'miners/simper/a.pdf', 'berkas_ddt' => 'miners/simper/b.pdf',
            'berkas_permohonan' => 'miners/simper/c.pdf', 'berkas_pengalaman' => 'miners/simper/d.pdf',
            'user_id' => $this->pengaju->id,
        ]);

        $k = Kendaraan::withoutGlobalScopes()->firstOrCreate(['nama' => $kendaraan], ['kode' => 'DT', 'aktif' => true]);
        $j = JenisUnit::withoutGlobalScopes()->firstOrCreate(['nama' => 'Volvo 40T'], ['aktif' => true]);

        $u = SimperUnit::withoutGlobalScopes()->create(['simper_id' => $s->id, 'kendaraan_id' => $k->id, 'jenis_unit_id' => $j->id,
            'authority' => 'F', 'nilai_p2h' => 90, 'nilai_praktek' => 85, 'asal' => 'baru']);
        $u->jenisUnitBanyak()->sync([$j->id]);

        return $s;
    }
}
