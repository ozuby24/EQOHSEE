<?php

namespace Database\Seeders;

use App\Models\Pjp;
use App\Models\PjpEvaluasi;
use App\Models\PjpLaporan;
use App\Models\SmkpChecklistAnswer;
use App\Models\SmkpChecklistCategory;
use App\Models\SmkpChecklistItem;
use Illuminate\Database\Seeder;

/**
 * Data contoh (bukan data sungguhan) untuk melihat gambaran tampilan aplikasi
 * saat sudah terisi — 7 PJP tersebar di ketiga tahap, status, dan rentang skor
 * yang berbeda-beda. Dijalankan manual lewat:
 *
 *     php artisan db:seed --class=DemoDataSeeder
 *
 * Sengaja TIDAK didaftarkan di DatabaseSeeder::run(), supaya tidak otomatis
 * ikut terisi setiap kali instalasi baru menjalankan `php artisan db:seed`
 * atau `migrate:fresh --seed` biasa.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->jawabSmkp($p1 = Pjp::create([
            'nama_perusahaan' => 'PT Borneo Tambang Sejahtera',
            'nib' => '1234567890123',
            'penanggung_jawab' => 'Ahmad Wijaya',
            'alamat' => 'Jl. Tambang Raya No. 12, Balikpapan',
            'status' => 'aktif',
        ]), fn ($i) => $i % 10 === 0 ? 2 : 3);
        $this->jawabLegalitas($p1, 3);

        $this->jawabSmkp($p2 = Pjp::create([
            'nama_perusahaan' => 'CV Mitra Bara Perkasa',
            'nib' => '9988776655443',
            'penanggung_jawab' => 'Siti Rahma',
            'alamat' => 'Jl. Sudirman No. 5, Samarinda',
            'status' => 'perlu_tindak_lanjut',
            'catatan' => 'Baru mendaftar, dokumen persyaratan masih dilengkapi.',
        ]), fn ($i) => $i % 5 === 0 ? 1 : null);
        $this->jawabLegalitas($p2, 0);

        $p3 = Pjp::create([
            'nama_perusahaan' => 'PT Kalimantan Jaya Konstruksi',
            'nib' => '1122334455667',
            'penanggung_jawab' => 'Budi Santoso',
            'alamat' => 'Jl. Pelabuhan No. 8, Bontang',
            'status' => 'aktif',
        ]);
        PjpLaporan::factory()->for($p3)->create(['jenis' => 'spip', 'periode' => '2026', 'created_at' => now()->subMonths(2)->startOfMonth()->addDay()]);
        PjpLaporan::factory()->for($p3)->create(['jenis' => 'tsp', 'periode' => '2026', 'created_at' => now()->subMonths(2)->startOfMonth()->addDays(2)]);
        PjpLaporan::factory()->for($p3)->create(['jenis' => 'laporan_bulanan', 'periode' => 'Juli 2026', 'created_at' => now()->subMonths(2)->startOfMonth()->addDay(), 'kesesuaian_isi' => 'sesuai']);
        PjpLaporan::factory()->for($p3)->create(['jenis' => 'laporan_bulanan', 'periode' => 'Agustus 2026', 'created_at' => now()->subMonth()->startOfMonth()->addDays(2), 'kesesuaian_isi' => 'sesuai']);
        PjpLaporan::factory()->for($p3)->create(['jenis' => 'laporan_bulanan', 'periode' => 'September 2026', 'created_at' => now()->startOfMonth()->addDay(), 'kesesuaian_isi' => 'sesuai']);
        PjpLaporan::factory()->for($p3)->create(['jenis' => 'laporan_triwulan', 'periode' => 'TW3 2026', 'created_at' => now()->subMonths(2)->startOfMonth()->addDay(), 'kesesuaian_isi' => 'sesuai']);

        $p4 = Pjp::create([
            'nama_perusahaan' => 'PT Nusantara Alat Berat',
            'nib' => '5566778899001',
            'penanggung_jawab' => 'Dewi Lestari',
            'alamat' => 'Jl. Industri No. 3, Tenggarong',
            'status' => 'perlu_tindak_lanjut',
            'catatan' => 'Laporan bulanan sering terlambat, perlu teguran tertulis.',
        ]);
        PjpLaporan::factory()->for($p4)->create(['jenis' => 'laporan_bulanan', 'periode' => 'Juli 2026', 'created_at' => now()->subMonths(2)->startOfMonth()->addDays(12), 'kesesuaian_isi' => 'tidak_sesuai']);
        PjpLaporan::factory()->for($p4)->create(['jenis' => 'laporan_bulanan', 'periode' => 'Agustus 2026', 'created_at' => now()->subMonth()->startOfMonth()->addDays(9), 'kesesuaian_isi' => 'sesuai']);
        PjpLaporan::factory()->for($p4)->create(['jenis' => 'laporan_bulanan', 'periode' => 'September 2026', 'created_at' => now()->startOfMonth()->addDays(15)]);

        Pjp::create([
            'nama_perusahaan' => 'PT Cahaya Mentari Energi',
            'nib' => '6677889900112',
            'penanggung_jawab' => 'Eko Prasetyo',
            'alamat' => 'Jl. Energi No. 7, Balikpapan',
            'status' => 'aktif',
        ]);

        $p6 = Pjp::create([
            'nama_perusahaan' => 'PT Sumber Energi Abadi',
            'nib' => '3344556677889',
            'penanggung_jawab' => 'Rudi Hartono',
            'alamat' => 'Jl. Minyak No. 21, Balikpapan',
            'status' => 'aktif',
        ]);
        PjpEvaluasi::factory()->for($p6)->create(['tahun' => 2025, 'semester' => 2, 'skor_teknis' => 75, 'skor_keselamatan_kesehatan' => 70, 'skor_lingkungan' => 72]);
        PjpEvaluasi::factory()->for($p6)->create(['tahun' => 2026, 'semester' => 1, 'skor_teknis' => 88, 'skor_keselamatan_kesehatan' => 92, 'skor_lingkungan' => 90, 'catatan' => 'Peningkatan signifikan pada aspek keselamatan.']);

        $p7 = Pjp::create([
            'nama_perusahaan' => 'CV Karya Tambang Mandiri',
            'nib' => '7788990011223',
            'penanggung_jawab' => 'Joko Prasetyo',
            'alamat' => 'Jl. Batubara No. 9, Sangatta',
            'status' => 'tidak_aktif',
            'catatan' => 'Kontrak tidak diperpanjang karena kinerja keselamatan buruk.',
        ]);
        PjpEvaluasi::factory()->for($p7)->create(['tahun' => 2026, 'semester' => 1, 'skor_teknis' => 35, 'skor_keselamatan_kesehatan' => 28, 'skor_lingkungan' => 33]);

        $this->command->info('Selesai. 7 PJP contoh berhasil ditambahkan.');
    }

    private function jawabSmkp(Pjp $pjp, callable $nilaiUntuk): void
    {
        $items = SmkpChecklistItem::whereHas('category', fn ($q) => $q->where('kode', '!=', 'LEGALITAS'))
            ->orderBy('id')
            ->get();

        foreach ($items as $index => $item) {
            $nilai = $nilaiUntuk($index, $item);
            if ($nilai === null) {
                continue;
            }
            SmkpChecklistAnswer::create([
                'pjp_id' => $pjp->id,
                'smkp_checklist_item_id' => $item->id,
                'jawaban' => $nilai === 'na' ? 'na' : ($nilai >= 2 ? 'ya' : 'tidak'),
                'nilai' => (string) $nilai,
            ]);
        }
    }

    private function jawabLegalitas(Pjp $pjp, int $lengkap): void
    {
        $items = SmkpChecklistCategory::where('kode', 'LEGALITAS')->first()->items;
        foreach ($items as $index => $item) {
            SmkpChecklistAnswer::create([
                'pjp_id' => $pjp->id,
                'smkp_checklist_item_id' => $item->id,
                'jawaban' => $index < $lengkap ? 'ya' : 'tidak',
                'nilai' => null,
            ]);
        }
    }
}
