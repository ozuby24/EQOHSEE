<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Models\Meranti as M;
use App\Support\Meranti\{Geo, Hitung, Impor, Katalog};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * MERANTI — Reklamasi & Nursery.
 *
 * Yang dijaga: empat peran MERANTI, batas perusahaan, rumus Matrik 17 dan
 * Aktual Pencapaian yang sama dengan aplikasi lamanya, unggah peta
 * KML/KMZ/GeoJSON, dan impor lembar Google Sheets yang aman diulang
 * tanpa pernah memuat akun lama.
 */
class MerantiTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $crew;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-26 08:00:00');
        $this->c = Company::create(['name' => 'PT Cemerlang Asa Mandiri']);
        $this->crew = User::factory()->create(['company_id' => $this->c->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pengguna(?string $peran): User
    {
        return User::factory()->create(['company_id' => $this->c->id, 'meranti_role' => $peran]);
    }

    public function test_semua_halaman_modul_terbuka(): void
    {
        $this->actingAs($this->crew);
        foreach (Katalog::semua() as $kunci => $m) {
            $this->get('/meranti/'.$m['slug'])->assertOk()->assertInertia(fn (Assert $p) => $p->component('Meranti/Modul')
                ->where('modul.kunci', $kunci)->where('judul', $m['judul'])->has('baris', 0));
        }
        $this->get('/meranti')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Meranti/Dasbor')->where('dasbor.kartu.totalStok', 0));
        $this->get('/meranti/aktual-pencapaian')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Meranti/Capaian'));
        $this->get('/meranti/peta')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Meranti/Peta'));
        $this->assertSame('/meranti/stok-bibit', route('meranti.stok_bibit', [], false));
    }

    public function test_empat_peran_meranti(): void
    {
        $data = ['nama_bibit' => 'Sengon Laut', 'stok' => 120, 'golongan' => 'Pioneer'];

        // Bawaan (tanpa peran) = Crew: boleh menambah, tidak boleh mengubah/menghapus.
        $this->actingAs($this->crew)->post('/meranti/data/stok_bibit', $data)->assertSessionHasNoErrors()->assertRedirect();
        $b = M\StokBibit::firstOrFail();
        $this->assertSame($this->c->id, $b->company_id);
        $this->assertSame($this->crew->id, $b->user_id);
        $this->put("/meranti/data/stok_bibit/{$b->id}", ['nama_bibit' => 'Sengon', 'stok' => 1])->assertForbidden();
        $this->delete("/meranti/data/stok_bibit/{$b->id}")->assertForbidden();

        // Manager: hanya melihat.
        $this->actingAs($this->pengguna('manager'))->post('/meranti/data/stok_bibit', $data)->assertForbidden();
        $this->get('/meranti/stok-bibit')->assertOk()->assertInertia(fn (Assert $p) => $p->where('izin.tambah', false)->where('izin.ubah', false));

        // EMC: tambah, ubah, hapus — tetapi bukan data master.
        $emc = $this->pengguna('emc');
        $this->actingAs($emc)->put("/meranti/data/stok_bibit/{$b->id}", ['nama_bibit' => 'Sengon Laut', 'stok' => 95, 'golongan' => 'Pioneer'])->assertSessionHasNoErrors();
        $this->assertSame(95, $b->fresh()->stok);
        $this->post('/meranti/data/master_tanaman', ['nama_tanaman' => 'Ulin', 'golongan' => 'Lokal'])->assertForbidden();
        $this->delete("/meranti/data/stok_bibit/{$b->id}")->assertRedirect();
        $this->assertNull(M\StokBibit::find($b->id));

        // Data Master: data master boleh.
        $this->actingAs($this->pengguna('data_master'))->post('/meranti/data/master_tanaman', ['nama_tanaman' => 'Ulin', 'nama_latin' => 'Eusideroxylon zwageri', 'golongan' => 'Lokal'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, M\Tanaman::count());

        // Setiap perubahan tercatat di log.
        $this->assertSame(['Tambah', 'Ubah', 'Hapus', 'Tambah'], M\Log::orderBy('id')->pluck('aksi')->all());
    }

    public function test_validasi_mengikuti_katalog(): void
    {
        $this->actingAs($this->crew)->post('/meranti/data/progres_mingguan', ['tahun' => 2026, 'bulan' => 'Juni', 'minggu' => 'Minggu 9', 'pekerjaan' => 'P7', 'luas_realisasi' => -1])
            ->assertSessionHasErrors(['minggu', 'pekerjaan', 'nama_blok', 'luas_realisasi']);
        $this->post('/meranti/data/bibit_masuk', ['nama_bibit' => 'Kelor'])->assertSessionHasErrors('tanggal');
        $this->assertSame(0, M\Progres::count() + M\BibitMasuk::count());
    }

    public function test_matrik_17_dihitung_server(): void
    {
        $this->actingAs($this->crew)->post('/meranti/data/penilaian_keberhasilan', [
            'blok' => 'Blok 2', 'tahun' => 2025, 'nilai_penatagunaan' => 88, 'nilai_revegetasi' => 82, 'nilai_penyelesaian' => 75,
            'skor_total' => 10, 'kategori' => 'Buruk',   // diabaikan: kolom hitung tidak dapat diketik
        ])->assertSessionHasNoErrors();
        $p = M\Penilaian::firstOrFail();
        $this->assertSame(84.2, $p->skor_total);
        $this->assertSame('Baik', $p->kategori);

        $this->assertSame(['skor_total' => 64.6, 'kategori' => 'Sedang'], Hitung::matrik17(70, 58, 55));
        $this->assertSame('Buruk', Hitung::matrik17(50, 50, 50)['kategori']);
        $this->assertSame('Sedang', Hitung::kategori(80.0), 'Tepat 80 masih Sedang: Baik harus > 80.');
    }

    public function test_aktual_pencapaian_per_blok(): void
    {
        $this->actingAs($this->crew);
        foreach ([['Blok 1', 'P0', 10], ['Blok 1', 'P1', 8], ['Blok 2', 'P0', 12], ['Blok 10', 'P0', 4]] as [$b, $p, $l]) {
            M\TargetBlok::create(['company_id' => $this->c->id, 'nama_blok' => $b, 'pekerjaan' => $p, 'luas_target' => $l, 'tahun' => 2026]);
        }
        M\TargetBlok::create(['company_id' => $this->c->id, 'nama_blok' => 'Blok 2', 'pekerjaan' => 'P1', 'luas_target' => 5, 'tahun' => null]); // tanpa tahun: berlaku tiap tahun
        foreach ([['Blok 1', 'P0', 4.5, 'Juni'], ['Blok 1', 'P0', 3.5, 'Mei'], ['Blok 1', 'P1', 8, 'Juli'], ['Blok 2', 'P0', 15, 'Juni'], ['Blok 2', 'P1', 2, 'Juni']] as [$b, $p, $l, $bln]) {
            M\Progres::create(['company_id' => $this->c->id, 'tahun' => 2026, 'bulan' => $bln, 'minggu' => 'Minggu 1', 'pekerjaan' => $p, 'nama_blok' => $b, 'luas_realisasi' => $l]);
        }
        M\Progres::create(['company_id' => $this->c->id, 'tahun' => 2025, 'bulan' => 'Juni', 'minggu' => 'Minggu 1', 'pekerjaan' => 'P0', 'nama_blok' => 'Blok 1', 'luas_realisasi' => 99]);

        $this->get('/meranti/aktual-pencapaian?tahun=2026')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('capaian.label', ['Blok 1', 'Blok 2', 'Blok 10'])
            ->where('capaian.rinci.0.P0.pct', 80)          // 8 / 10
            ->where('capaian.rinci.0.P1.pct', 100)
            ->where('capaian.rinci.1.P0.pct', 125)         // 15 / 12 — lewat target
            ->where('capaian.rinci.1.P1.pct', 40)          // 2 / 5 (target tanpa tahun)
            ->where('capaian.rinci.2.P0.pct', 0)
            ->where('capaian.rinci.2.P1.pct', null)        // tanpa target
            ->where('capaian.total.real', 33)
            ->where('capaian.total.target', 39)
            ->where('capaian.tahunList', ['2025', '2026']));

        // Dasbor tahun 2026: realisasi per bulan dan PKAR.
        M\RencanaReklamasi::create(['company_id' => $this->c->id, 'tahun' => 2026, 'luas_bukaan_ha' => 60, 'luas_reklamasi_ha' => 48.5]);
        $this->get('/meranti?tahun=2026')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('dasbor.kartu.totalRealisasi', 33)
            ->where('dasbor.kartu.pkar', 80.8)
            ->where('dasbor.perBulan.5', 21.5)             // Juni: 4.5 + 15 + 2
            ->where('dasbor.pekerjaan.P0', 23));

        // Progres Mingguan urut tahun → bulan → minggu, terbaru di atas.
        $this->get('/meranti/progres-mingguan')->assertInertia(fn (Assert $p) => $p->where('baris.0.bulan', 'Juli')->where('baris.5.tahun', 2025));
    }

    public function test_batas_perusahaan(): void
    {
        $lain = Company::create(['name' => 'PT Lain']);
        $asing = M\StokBibit::create(['company_id' => $lain->id, 'nama_bibit' => 'Rahasia', 'stok' => 5]);
        M\StokBibit::create(['company_id' => $this->c->id, 'nama_bibit' => 'Milik sendiri', 'stok' => 5]);

        $emc = $this->pengguna('emc');
        $this->actingAs($emc)->get('/meranti/stok-bibit')->assertInertia(fn (Assert $p) => $p->has('baris', 1)->where('baris.0.nama_bibit', 'Milik sendiri'));
        $this->put("/meranti/data/stok_bibit/{$asing->id}", ['nama_bibit' => 'Diambil', 'stok' => 1])->assertNotFound();
        $this->delete("/meranti/data/stok_bibit/{$asing->id}")->assertNotFound();
        $this->assertSame('Rahasia', $asing->fresh()->nama_bibit);

        // Pengguna biasa tidak dapat menanam baris ke perusahaan lain lewat isian.
        $this->post('/meranti/data/stok_bibit', ['nama_bibit' => 'Titipan', 'stok' => 1, 'company_id' => $lain->id])->assertRedirect();
        $this->assertSame($this->c->id, M\StokBibit::where('nama_bibit', 'Titipan')->value('company_id'));
    }

    public function test_unggah_geojson_kml_dan_kmz(): void
    {
        $this->actingAs($this->crew);
        $gj = json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['name' => 'Blok A', 'petak' => 'P-01', 'tahun' => 2025],
                'geometry' => ['type' => 'MultiPolygon', 'coordinates' => [
                    [[[116.95, -0.62], [116.96, -0.62], [116.96, -0.63], [116.95, -0.63], [116.95, -0.62]]],
                    [[[116.97, -0.62], [116.975, -0.62], [116.975, -0.625], [116.97, -0.62]]],
                ]]],
            ['type' => 'Feature', 'properties' => ['nama' => 'Titik Ulin'], 'geometry' => ['type' => 'Point', 'coordinates' => [116.955, -0.625]]],
        ]]);
        $this->post('/meranti/peta/unggah', ['berkas' => UploadedFile::fake()->createWithContent('blok.geojson', $gj)])->assertSessionHasNoErrors();
        $this->assertSame(3, M\Peta::count(), 'MultiPolygon dipecah menjadi poligon tunggal.');
        $a = M\Peta::where('nama_fitur', 'Blok A')->orderBy('id')->first();
        $this->assertEqualsWithDelta(123.9, $a->luas_ha, 0.5);
        $this->assertSame('P-01', $a->petak);
        $this->assertSame(2025, $a->year);
        $this->assertSame('Titik Tanam', M\Peta::where('nama_fitur', 'Titik Ulin')->value('kategori'));

        $kml = '<?xml version="1.0"?><kml xmlns="http://www.opengis.net/kml/2.2"><Document><Placemark><name>Revegetasi 1</name>'
            .'<Polygon><outerBoundaryIs><LinearRing><coordinates>116.95,-0.62,0 116.951,-0.62,0 116.951,-0.621,0 116.95,-0.62,0</coordinates></LinearRing></outerBoundaryIs></Polygon></Placemark></Document></kml>';
        $zip = tempnam(sys_get_temp_dir(), 'kmz');
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::OVERWRITE);
        $z->addFromString('doc.kml', $kml);
        $z->close();
        $this->post('/meranti/peta/unggah', ['berkas' => new UploadedFile($zip, 'revegetasi smt 1.kmz', null, null, true), 'kategori' => 'Petak'])->assertSessionHasNoErrors();
        $r = M\Peta::where('nama_fitur', 'Revegetasi 1')->firstOrFail();
        $this->assertSame('Petak', $r->kategori);
        $this->assertSame('revegetasi smt 1.kmz', $r->sumber_file);

        $this->post('/meranti/peta/unggah', ['berkas' => UploadedFile::fake()->createWithContent('peta.txt', 'x')])->assertSessionHasErrors('berkas');
        $this->post('/meranti/peta/unggah', ['berkas' => UploadedFile::fake()->createWithContent('rusak.kml', '<kml><bukan')])->assertSessionHasErrors('berkas');

        $this->get('/meranti/peta')->assertInertia(fn (Assert $p) => $p->has('fitur', 4)->where('ringkas.fitur', 4));
        $gjKeluar = $this->get('/meranti/peta/geojson')->assertOk()->json();
        $this->assertSame('FeatureCollection', $gjKeluar['type']);
        $this->assertCount(4, $gjKeluar['features']);

        // Manager tidak dapat mengunggah.
        $this->actingAs($this->pengguna('manager'))->post('/meranti/peta/unggah', ['berkas' => UploadedFile::fake()->createWithContent('b.geojson', $gj)])->assertForbidden();
    }

    /**
     * Buku kerja sintetis yang meniru lembar basis data MERANTI versi Apps
     * Script: awalan "MERANTI - ", nama lembar terpotong 31 karakter, lembar
     * Users berisi hash sandi, log ber-id kembar, poligon tanpa nama, nilai
     * pilihan berhuruf besar, dan kolom lama `stok_actual`.
     */
    private function bukuMeranti(): string
    {
        $poligon = json_encode(['type' => 'Polygon', 'coordinates' => [[[116.95, -0.62], [116.96, -0.62], [116.96, -0.63], [116.95, -0.63], [116.95, -0.62]]]]);

        return $this->xlsx([
            'Sheet1' => [],
            'MERANTI - Stok Bibit' => [['id', 'nama_bibit', 'stok', 'golongan', 'waktu_input'], [1, 'Sengon', 1, 'Pioneer', '2026-07-09 10:07'], [2, 'Kapur', 1, 'Lokal', '2026-07-09 10:08'], [3, 'Mata Kucing', null, 'MPTS', '2026-07-09 10:10']],
            'MERANTI - Actual Penanaman' => [['id', 'tanggal', 'nama_bibit', 'golongan', 'stok_actual', 'nama_project', 'nama_kegiatan', 'nama_pelaksana', 'nama_blok', 'penginput', 'waktu_input'],
                [5, '2026-06-13', 'Meranti Merah', 'LOKAL', 50, 'Perawatan', 'Perawatan Rehabilitasi Tahap I', 'Tim Persemaian', 'Blok 3', 'Petugas A', '2026-07-06 12:53']],
            'MERANTI - Rencana Reklamasi' => [['id', 'tahun', 'luas_bukaan_ha', 'luas_reklamasi_ha', 'jumlah_bibit_rencana', 'biaya_rencana', 'keterangan', 'waktu_input'], [3, 2026, 60, '48,5', 30000, 3850000000, 'Rencana tahun berjalan', null]],
            'MERANTI - Penilaian Keberhasila' => [['id', 'blok', 'tahun', 'nilai_penatagunaan', 'nilai_revegetasi', 'nilai_penyelesaian', 'skor_total', 'kategori'], [1, 'Blok 2', 2025, 88, 82, 75, '84,2', 'Baik'], [2, 'Blok 1', 2024, 70, 58, 55, '64,6', 'Sedang']],
            'MERANTI - Peta Spasial' => [['id', 'nama_fitur', 'kategori', 'petak', 'week', 'month', 'year', 'luas_ha', 'geojson', 'sumber_file'],
                [1, ' ', 'Blok', null, null, null, null, '6,160809', $poligon, 'revegetasi smt 1.kmz'], [2, 'Blok Utara', 'Blok', null, null, null, null, null, $poligon, 'revegetasi smt 1.kmz']],
            'MERANTI - Log Aktivitas' => [['id', 'waktu', 'user', 'aksi', 'modul', 'detail'], [1, '2026-07-06 12:54', 'petugas', 'Login', 'Sistem', null],
                [14, '2026-07-07 15:11', 'petugas', 'Hapus', 'Actual Penanaman', '3 | '], [14, '2026-07-07 15:11', 'petugas', 'Hapus', 'Actual Penanaman', '3 | ']],
            'MERANTI - Users' => [['id', 'username', 'password', 'nama', 'role'], [1, 'petugas', str_repeat('ab', 32), 'Petugas Lama', 'Administrator']],
        ]);
    }

    public function test_impor_lembar_meranti_aman_diulang(): void
    {
        $dm = $this->pengguna('data_master');
        $kirim = fn () => $this->actingAs($dm)->post('/meranti/impor', ['sumber' => 'berkas', 'berkas' => new UploadedFile($this->bukuMeranti(), 'MERANTI.xlsx', null, null, true)]);
        $this->actingAs($this->crew)->post('/meranti/impor', ['sumber' => 'berkas', 'berkas' => new UploadedFile($this->bukuMeranti(), 'MERANTI.xlsx', null, null, true)])->assertForbidden();

        $this->actingAs($dm)->get('/meranti/impor')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Meranti/Impor')->where('tujuan.id', $this->c->id));
        $kirim()->assertSessionHasNoErrors()->assertRedirect()
            ->assertSessionHas('hasilImpor', fn ($h) => $h['baru'] === 12 && $h['dilewati'] === 1);   // 3 stok + 1 penanaman + 1 rencana + 2 penilaian + 2 peta + 3 log; Users dilewati

        $this->assertSame(3, M\StokBibit::count());
        $this->assertNull(M\StokBibit::where('nama_bibit', 'Mata Kucing')->value('stok'), 'Stok kosong tetap kosong, bukan nol.');
        $this->assertSame(2, M\Penilaian::count(), 'Lembar "Penilaian Keberhasila" (nama terpotong 31 karakter) tetap dikenali.');
        $this->assertSame(84.2, M\Penilaian::where('blok', 'Blok 2')->value('skor_total'));
        $this->assertSame(48.5, M\RencanaReklamasi::firstOrFail()->luas_reklamasi_ha);
        $pen = M\Penanaman::firstOrFail();
        $this->assertSame('Lokal', $pen->golongan, 'LOKAL dinormalkan ke pilihan katalog.');
        $this->assertSame(50, $pen->jumlah, 'stok_actual → jumlah.');
        $this->assertSame('Tim Persemaian', $pen->pelaksana, 'nama_pelaksana → pelaksana.');
        $this->assertSame('2026-07-06 12:53', $pen->created_at->format('Y-m-d H:i'), 'waktu_input lembar dipertahankan.');
        $this->assertSame(['revegetasi smt 1 · #1', 'Blok Utara'], M\Peta::orderBy('id')->pluck('nama_fitur')->all(), 'Fitur tanpa nama diberi nama dari berkas sumbernya.');
        $this->assertSame(6.160809, M\Peta::orderBy('id')->first()->luas_ha);
        $this->assertGreaterThan(100, M\Peta::where('nama_fitur', 'Blok Utara')->value('luas_ha'), 'Luas kosong dihitung dari poligon.');
        $this->assertSame(3, M\Log::whereNotNull('asal_ref')->count(), 'Log ber-id kembar tetap dimuat per baris.');
        $this->assertSame(0, User::where('name', 'Petugas Lama')->count(), 'Lembar Users tidak pernah dimuat.');

        // Impor ulang tidak menggandakan apa pun.
        $sebelum = collect(Katalog::semua())->map(fn ($m) => $m['kelas']::count())->all();
        $kirim()->assertSessionHasNoErrors()->assertSessionHas('hasilImpor', fn ($h) => $h['baru'] === 0);
        $this->assertSame($sebelum, collect(Katalog::semua())->map(fn ($m) => $m['kelas']::count())->all());
        $this->assertSame(3, M\Log::whereNotNull('asal_ref')->count());

        // Data yang diketik langsung di EQOHSEE tidak disentuh impor ulang.
        $this->post('/meranti/data/stok_bibit', ['nama_bibit' => 'Meranti Merah', 'stok' => 40, 'golongan' => 'Lokal']);
        $kirim();
        $this->assertSame(4, M\StokBibit::count());
    }

    private function xlsx(array $lembar): string
    {
        $buku = new Spreadsheet();
        $buku->removeSheetByIndex(0);
        foreach ($lembar as $nama => $baris) {
            $ws = $buku->createSheet()->setTitle($nama);
            // Teks ditulis sebagai teks: fromArray() mengubah "1.250" menjadi angka 1,25,
            // padahal sel teks pada ekspor Google Sheets tetap teks.
            foreach ($baris as $r => $isi) {
                foreach (array_values($isi) as $k => $v) {
                    $sel = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($k + 1).($r + 1);
                    if (is_string($v)) $ws->setCellValueExplicit($sel, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    elseif ($v !== null) $ws->setCellValue($sel, $v);
                }
            }
        }
        $jalur = tempnam(sys_get_temp_dir(), 'mrt').'.xlsx';
        (new Xlsx($buku))->save($jalur);

        return $jalur;
    }

    public function test_impor_berkas_xlsx(): void
    {
        $jalur = $this->xlsx([
            'MERANTI - Stok Bibit' => [['id', 'nama_bibit', 'stok', 'golongan', 'waktu_input'], [1, 'Sengon', '1.250', 'pioneer', '2026-07-09 10:07'], [2, 'Kahoi', 7, 'Lokal', null]],
            'MERANTI - Penilaian Keberhasila' => [['id', 'blok', 'tahun', 'nilai_penatagunaan', 'nilai_revegetasi', 'nilai_penyelesaian', 'skor_total', 'kategori'], [1, 'Blok 3', 2025, 90, 85, 80, '87', 'Baik']],
            'Progres Mingguan' => [['id', 'tahun', 'bulan', 'minggu', 'tanggal', 'pelaksana', 'pekerjaan', 'nama_blok', 'luas_realisasi'],
                [1, 2026, 'Juni', 'Minggu 3', 46193, 'Kelompok Tani Hijau', 'p0', 'Blok 3', '4,5'], [2, 2026, 'Juni', 'Minggu 3', null, 'Kelompok Tani Hijau', 'P0', '', '1']],
            'MERANTI - Users' => [['id', 'username', 'password', 'nama', 'role'], [1, 'ali', 'hash', 'Ali', 'Administrator']],
        ]);

        $this->actingAs($this->pengguna('data_master'))->post('/meranti/impor', ['sumber' => 'berkas', 'berkas' => new UploadedFile($jalur, 'MERANTI.xlsx', null, null, true)])
            ->assertSessionHasNoErrors()->assertSessionHas('hasilImpor', fn ($h) => $h['baru'] === 4 && $h['dilewati'] === 2);

        $this->assertSame(1250, M\StokBibit::where('nama_bibit', 'Sengon')->value('stok'), '"1.250" dibaca seribu dua ratus lima puluh.');
        $this->assertSame('Pioneer', M\StokBibit::where('nama_bibit', 'Sengon')->value('golongan'));
        $this->assertSame(87.0, M\Penilaian::firstOrFail()->skor_total);
        $p = M\Progres::firstOrFail();
        $this->assertSame('P0', $p->pekerjaan);
        $this->assertSame(4.5, $p->luas_realisasi);
        $this->assertSame('2026-06-20', $p->tanggal->toDateString(), 'Tanggal serial Excel 46193 dikonversi.');
        $this->assertSame(1, M\Progres::count(), 'Baris tanpa nama blok dilewati.');
        $this->assertSame(0, User::where('name', 'Ali')->count(), 'Lembar Users tidak pernah dimuat.');

        $this->post('/meranti/impor', ['sumber' => 'berkas', 'berkas' => UploadedFile::fake()->createWithContent('data.txt', 'a,b')])->assertSessionHasErrors('berkas');
    }

    public function test_impor_csv_ekspor_meranti(): void
    {
        // Tombol Export CSV MERANTI: kepala berupa label, kolom pertama "No", nama berkas = judul modul.
        $csv = "\xEF\xBB\xBFNo,Tahun,Bulan,Minggu,Tanggal,Pelaksana,Pekerjaan,Nama Blok,Luas Realisasi (Ha),Keterangan\n"
            ."1,2026,Juni,Minggu 3,2026-06-20,Kelompok Tani Hijau,P0,Blok 3,4.5,Pengayaan 2026\n"
            ."2,2026,Juni,Minggu 3,2026-06-18,Tim Persemaian,P2,Blok 2,3.5,\"Perawatan, Revegetasi Tahap I\"\n";
        $dm = $this->pengguna('data_master');
        $kirim = fn () => $this->actingAs($dm)->post('/meranti/impor', ['sumber' => 'berkas', 'berkas' => UploadedFile::fake()->createWithContent('Progres_Mingguan.csv', $csv)]);

        $kirim()->assertSessionHasNoErrors();
        $this->assertSame(2, M\Progres::count());
        $b = M\Progres::where('nama_blok', 'Blok 2')->firstOrFail();
        $this->assertSame(3.5, $b->luas_realisasi);
        $this->assertSame('Perawatan, Revegetasi Tahap I', $b->keterangan);
        $this->assertSame('progres_mingguan#r2', $b->asal_ref);

        $kirim()->assertSessionHasNoErrors();
        $this->assertSame(2, M\Progres::count(), 'CSV yang sama diimpor ulang tidak menggandakan.');

        $target = "No;Nama Blok;Pekerjaan;Luas Target (Ha);Tahun;Keterangan\n1;Blok 3;P0;15;2026;\n";
        $this->post('/meranti/impor', ['sumber' => 'berkas', 'berkas' => UploadedFile::fake()->createWithContent('Target_Blok_(Rencana).csv', $target)])->assertSessionHasNoErrors();
        $this->assertSame(15.0, M\TargetBlok::firstOrFail()->luas_target, 'Pemisah titik koma dikenali.');
    }

    public function test_impor_tautan_google_sheets(): void
    {
        $jalur = $this->xlsx(['Stok Material' => [['id', 'nama_material', 'material_stok', 'nama_satuan'], [1, 'Pupuk TSP', 100, 'kg']]]);
        Http::fake([
            'docs.google.com/spreadsheets/d/LembarContohMerantiTerbagi0123456789/*' => Http::response(file_get_contents($jalur), 200),
            'docs.google.com/spreadsheets/d/lembarTertutupLembarTertutup123/*' => Http::response('<html>Masuk Google</html>', 200),
        ]);
        $this->actingAs($this->pengguna('data_master'));

        $this->post('/meranti/impor', ['sumber' => 'tautan', 'tautan' => 'https://docs.google.com/spreadsheets/d/LembarContohMerantiTerbagi0123456789/edit?gid=12345#gid=12345'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Kg', M\StokMaterial::firstOrFail()->nama_satuan);
        Http::assertSent(fn ($r) => str_starts_with($r->url(), 'https://docs.google.com/spreadsheets/d/LembarContohMerantiTerbagi0123456789/export'));

        $this->post('/meranti/impor', ['sumber' => 'tautan', 'tautan' => 'https://docs.google.com/spreadsheets/d/lembarTertutupLembarTertutup123/edit'])->assertSessionHasErrors('tautan');
        $this->post('/meranti/impor', ['sumber' => 'tautan', 'tautan' => 'https://contoh.test/berkas.xlsx'])->assertSessionHasErrors('tautan');
    }

    public function test_perintah_artisan_impor(): void
    {
        $berkas = $this->bukuMeranti();
        $this->artisan('meranti:impor', ['berkas' => $berkas, '--perusahaan' => 'Cemerlang', '--kering' => true])->assertSuccessful();
        $this->assertSame(0, M\StokBibit::withoutGlobalScopes()->count());

        $this->artisan('meranti:impor', ['berkas' => $berkas, '--perusahaan' => 'Cemerlang'])->assertSuccessful();
        $this->assertSame(3, M\StokBibit::withoutGlobalScopes()->where('company_id', $this->c->id)->count());

        $this->artisan('meranti:impor', ['berkas' => $berkas, '--perusahaan' => 'Tidak Ada'])->assertFailed();
        $this->artisan('meranti:impor', ['--perusahaan' => (string) $this->c->id])->assertFailed();
    }

    public function test_ekspor_csv_log_dan_peran(): void
    {
        $this->actingAs($this->crew)->post('/meranti/data/jaminan_reklamasi', ['periode' => '2024-2028', 'jenis' => 'Jaminan Reklamasi', 'nilai' => 14500000000, 'status' => 'Ditempatkan']);
        $csv = $this->get('/meranti/data/jaminan_reklamasi/csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Periode,Jenis,"Nilai (Rp)"', $csv);
        $this->assertStringContainsString('2024-2028', $csv);

        $this->get('/meranti/log')->assertForbidden();
        $this->get('/meranti/pengguna')->assertForbidden();

        $dm = $this->pengguna('data_master');
        $this->actingAs($dm)->get('/meranti/log')->assertOk()->assertInertia(fn (Assert $p) => $p->where('log.total', 1)->where('log.data.0.aksi', 'Tambah'));
        $this->get('/meranti/pengguna')->assertOk()->assertInertia(fn (Assert $p) => $p->has('akun', 2)->has('peran', 4));
        $this->put("/meranti/pengguna/{$this->crew->id}", ['peran' => 'emc'])->assertSessionHasNoErrors();
        $this->assertSame('emc', $this->crew->fresh()->meranti_role);
        $this->put("/meranti/pengguna/{$this->crew->id}", ['peran' => 'raja'])->assertSessionHasErrors('peran');

        $orangLain = User::factory()->create(['company_id' => Company::create(['name' => 'PT Lain'])->id]);
        $this->put("/meranti/pengguna/{$orangLain->id}", ['peran' => 'data_master'])->assertNotFound();
        $this->assertNull($orangLain->fresh()->meranti_role);

        $this->put('/meranti/setelan', ['folder_drive' => 'https://contoh.test/folder'])->assertSessionHasErrors('folder_drive');
        $this->put('/meranti/setelan', ['folder_drive' => 'https://drive.google.com/drive/folders/FolderContohArsipCapaianMeranti01'])->assertSessionHasNoErrors();
        $this->get('/meranti/arsip-capaian')->assertInertia(fn (Assert $p) => $p->where('setelan.folder_drive', 'https://drive.google.com/drive/folders/FolderContohArsipCapaianMeranti01'));
    }

    public function test_angka_berformat_indonesia(): void
    {
        $this->assertSame(48.5, Hitung::angka('48,5'));
        $this->assertSame(1250000.0, Hitung::angka('1.250.000'));
        $this->assertSame(1250000.5, Hitung::angka('1.250.000,50'));
        $this->assertSame(1250000.5, Hitung::angka('1,250,000.50'));
        $this->assertSame(3850000000.0, Hitung::angka('Rp 3.850.000.000'));
        $this->assertNull(Hitung::angkaAtauNull(''));
        $this->assertEqualsWithDelta(123.9, Geo::luasHa([[116.95, -0.62], [116.96, -0.62], [116.96, -0.63], [116.95, -0.63]]), 0.5);
    }
}
