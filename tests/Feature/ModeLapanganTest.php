<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Company, HazardReport, P2hPeriksa, P2hUnit, User, WorkOrder};
use App\Support\{P2h, RisikoLapangan, Waktu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Mode lapangan: lapor bahaya bermatriks 5×5, P2H, dan kiriman luring.
 *
 * Yang paling mahal bila rusak di sini justru yang tidak terlihat di
 * layar:
 *
 *   · Kiriman ulang dari perangkat yang sinyalnya putus-sambung. Satu
 *     laporan yang tercatat dua kali menggandakan KPI dan membingungkan
 *     pengawas; satu yang hilang adalah bahaya yang tidak ditindak.
 *   · Butir kritis P2H yang gagal. Unit yang seharusnya ditahan tetapi
 *     tercatat laik akan dioperasikan.
 *   · Tenggat dari matriks. Pelapor tidak lagi memilih tingkat risiko
 *     sendiri — bila pemetaannya salah, seluruh tenggat ikut salah.
 */
class ModeLapanganTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    // Persis bentuk yang dikirim aplikasi 1.1+: UA WebView bawaan + penanda.
    private const UA_APLIKASI = 'Mozilla/5.0 (Linux; Android 14; SM-A155F Build/UP1A.231005.007; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/140.0.7339.51 Mobile Safari/537.36 EQOHSEE-Android/1.1.0';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Lapangan {$i}", 'code' => "LP{$i}"]);
    }

    private function pekerja(Company $c, array $x = []): User
    {
        return User::factory()->create(array_merge(['is_admin' => false, 'company_id' => $c->id], $x));
    }

    private function unit(Company $c, array $x = []): P2hUnit
    {
        return P2hUnit::withoutGlobalScopes()->create(array_merge([
            'company_id' => $c->id, 'kode' => 'DT-'.(++self::$n), 'nama' => 'Komatsu HD785-7',
            'jenis' => 'dump_truck', 'status' => P2h::LAIK, 'aktif' => true, 'hm' => 1000,
        ], $x));
    }

    /** Jawaban lengkap untuk satu jenis unit, seluruhnya OK kecuali yang diubah. */
    private function jawab(string $jenis, array $ubah = []): array
    {
        return array_merge(array_fill_keys(array_column(P2h::butir($jenis), 'kode'), P2h::OK), $ubah);
    }

    private function lapor(array $x = []): array
    {
        return array_merge([
            'kategori'    => 'Unsafe Condition',
            'deskripsi'   => 'Tanggul pengaman tergerus di tikungan.',
            'lokasi'      => 'Jalan angkut R-04',
            'kemungkinan' => 3,
            'keparahan'   => 2,
        ], $x);
    }

    /* ═══════════════ matriks 5×5 ═══════════════ */

    public function test_matriks_dipetakan_ke_tiga_tingkat_dengan_tenggat(): void
    {
        $hari = Carbon::parse('2026-09-27 14:00');

        foreach ([
            // kemungkinan, keparahan → pita, risiko tersimpan, tenggat (hari)
            [1, 1, 'rendah', 'Rendah', 7],
            [1, 3, 'rendah', 'Rendah', 7],
            [2, 2, 'sedang', 'Sedang', 3],
            [1, 7, 'sedang', 'Sedang', 3],   // dibatasi ke 5 → 1×5 = 5
            [2, 4, 'tinggi', 'Tinggi', 1],
            [3, 4, 'tinggi', 'Tinggi', 1],
            [3, 5, 'ekstrem', 'Tinggi', 0],
            [5, 5, 'ekstrem', 'Tinggi', 0],
        ] as [$k, $p, $pita, $risiko, $tenggat]) {
            $this->assertSame($pita, RisikoLapangan::pita($k, $p), "{$k}×{$p}");
            $this->assertSame($risiko, RisikoLapangan::risiko($k, $p), "{$k}×{$p}");
            $this->assertSame($hari->copy()->startOfDay()->addDays($tenggat)->toDateString(),
                RisikoLapangan::batasAkhir($k, $p, $hari)->toDateString(), "{$k}×{$p}");
        }

        /* Ekstrem tidak menjadi tingkat keempat: seluruh rekap membaca tiga. */
        $tersimpan = array_unique(array_column(RisikoLapangan::PITA, 'risiko'));
        sort($tersimpan);
        $this->assertSame(['Rendah', 'Sedang', 'Tinggi'], $tersimpan);
    }

    public function test_lapor_menurunkan_risiko_dan_tenggat_dari_matriks(): void
    {
        $c = $this->perusahaan();
        $u = $this->pekerja($c, ['name' => 'Rina Anggraini', 'position' => 'Pengawas']);
        $this->actingAs($u);

        $r = $this->postJson(route('lapangan.lapor.simpan'), $this->lapor([
            'klien_id' => 'uji-1', 'kemungkinan' => 4, 'keparahan' => 4,
            'lat' => -2.9871234, 'lng' => 115.4412812, 'akurasi_m' => 4,
            // Kiriman yang mencoba menentukan risikonya sendiri tidak didengar.
            'risiko' => 'Rendah',
            'foto' => [UploadedFile::fake()->image('temuan.jpg', 800, 600)],
        ]))->assertCreated()->assertJson(['ok' => true, 'baru' => true]);

        $h = HazardReport::withoutGlobalScopes()->findOrFail($r->json('id'));
        $this->assertSame('Tinggi', $h->risiko);
        $this->assertSame(Waktu::kini()->toDateString(), $h->batas_akhir->toDateString(), 'Skor 16 = ekstrem: tenggat hari ini.');
        $this->assertSame([4, 4], [$h->kemungkinan, $h->keparahan]);
        $this->assertEqualsWithDelta(-2.9871234, $h->lat, 1e-7);
        $this->assertSame($c->id, $h->company_id);
        $this->assertSame('Rina Anggraini', $h->pelapor_nama);
        $this->assertSame('Open', $h->status);
        $this->assertCount(1, (array) $h->foto);
        $this->assertStringContainsString($h->kode, $r->json('kode'));
        $this->assertStringEndsWith('/lapangan/laporan/'.$h->id, $r->json('url'));
    }

    public function test_lapor_tanpa_json_dialihkan_ke_detail_laporan(): void
    {
        $c = $this->perusahaan();
        $this->actingAs($this->pekerja($c));

        $this->post(route('lapangan.lapor.simpan'), $this->lapor())
            ->assertRedirect()->assertSessionHas('ok');

        $h = HazardReport::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Sedang', $h->risiko, '3×2 = 6 → sedang');
    }

    public function test_lapor_menolak_isian_tidak_lengkap(): void
    {
        $this->actingAs($this->pekerja($this->perusahaan()));

        $this->postJson(route('lapangan.lapor.simpan'), $this->lapor(['deskripsi' => '', 'kemungkinan' => 6, 'kategori' => 'Lain']))
            ->assertStatus(422)->assertJsonValidationErrors(['deskripsi', 'kemungkinan', 'kategori']);

        $this->postJson(route('lapangan.lapor.simpan'), $this->lapor(['klien_id' => 'ada spasi/garing']))
            ->assertStatus(422)->assertJsonValidationErrors(['klien_id']);

        $this->assertSame(0, HazardReport::withoutGlobalScopes()->count());
    }

    /* ═══════════════ kiriman luring ═══════════════ */

    public function test_kiriman_ulang_dengan_klien_id_sama_tidak_menggandakan(): void
    {
        $this->actingAs($this->pekerja($this->perusahaan()));
        $isi = $this->lapor(['klien_id' => '0b5c1e0e-7f7a-4a8e-9a55-2c7c1a9d3f10']);

        $a = $this->postJson(route('lapangan.lapor.simpan'), $isi)->assertCreated();
        $b = $this->postJson(route('lapangan.lapor.simpan'), $isi)->assertOk()->assertJson(['baru' => false]);

        $this->assertSame($a->json('id'), $b->json('id'));
        $this->assertSame(1, HazardReport::withoutGlobalScopes()->count());
    }

    public function test_klien_id_sama_milik_dua_orang_tetap_dua_laporan(): void
    {
        $c = $this->perusahaan();

        foreach ([$this->pekerja($c), $this->pekerja($c)] as $u) {
            $this->actingAs($u);
            $this->postJson(route('lapangan.lapor.simpan'), $this->lapor(['klien_id' => 'sama']))->assertCreated();
        }

        $this->assertSame(2, HazardReport::withoutGlobalScopes()->count());
    }

    public function test_waktu_susun_luring_dipakai_tetapi_dibatasi(): void
    {
        $this->actingAs($this->pekerja($this->perusahaan()));
        $kini = Waktu::kini();

        foreach ([
            'a' => [$kini->copy()->subDays(2)->setTime(7, 18)->toIso8601String(), $kini->copy()->subDays(2)->toDateString(), '07:18'],
            'b' => [$kini->copy()->addDays(3)->toIso8601String(), $kini->toDateString(), null],
            'c' => [$kini->copy()->subDays(40)->toIso8601String(), $kini->copy()->subDays(7)->toDateString(), null],
        ] as $id => [$disusun, $tanggal, $jam]) {
            $r = $this->postJson(route('lapangan.lapor.simpan'), $this->lapor(['klien_id' => "w-{$id}", 'disusun_pada' => $disusun]))->assertCreated();
            $h = HazardReport::withoutGlobalScopes()->findOrFail($r->json('id'));

            $this->assertSame($tanggal, $h->tanggal->toDateString(), "kiriman {$id}");
            if ($jam) $this->assertStringStartsWith($jam, (string) $h->waktu, "kiriman {$id}");
        }
    }

    public function test_pekerja_tidak_dapat_melapor_ke_perusahaan_lain(): void
    {
        $c = $this->perusahaan();
        $lain = $this->perusahaan();
        $this->actingAs($this->pekerja($c));

        $this->postJson(route('lapangan.lapor.simpan'), $this->lapor(['company_id' => $lain->id]))->assertForbidden();
        $this->assertSame(0, HazardReport::withoutGlobalScopes()->count());
    }

    /* ═══════════════ P2H ═══════════════ */

    public function test_p2h_laik_bila_hanya_butir_biasa_yang_gagal(): void
    {
        $c = $this->perusahaan();
        $u = $this->unit($c);
        $this->actingAs($this->pekerja($c));

        $this->postJson(route('lapangan.p2h.simpan', $u), [
            'klien_id' => 'p-1', 'operator' => 'Agus Pratama', 'shift' => 'pagi', 'hm' => 1012.5,
            'jawab' => $this->jawab('dump_truck', ['kaca' => P2h::TIDAK, 'klakson' => P2h::NA]),
        ])->assertCreated()->assertJson(['hasil' => P2h::LAIK]);

        $u->refresh();
        $this->assertSame(P2h::LAIK, $u->status);
        $this->assertEquals(1012.5, (float) $u->hm);
        $this->assertSame(0, WorkOrder::withoutGlobalScopes()->count());

        $p = P2hPeriksa::withoutGlobalScopes()->firstOrFail();
        $this->assertSame([1, 1], [$p->jumlah_tidak, $p->jumlah_na]);
        $this->assertSame(count(P2h::butir('dump_truck')), $p->jumlah_ok + $p->jumlah_tidak + $p->jumlah_na);
    }

    public function test_p2h_belum_lengkap_ditolak(): void
    {
        $c = $this->perusahaan();
        $u = $this->unit($c);
        $this->actingAs($this->pekerja($c));

        $jawab = $this->jawab('dump_truck');
        unset($jawab['rem_servis']);

        $this->postJson(route('lapangan.p2h.simpan', $u), ['operator' => 'Agus', 'shift' => 'pagi', 'jawab' => $jawab])
            ->assertStatus(422)->assertJsonValidationErrors(['jawab']);

        /* Kode asing tidak menggantikan butir yang hilang. */
        $this->postJson(route('lapangan.p2h.simpan', $u), ['operator' => 'Agus', 'shift' => 'pagi', 'jawab' => $jawab + ['karangan' => P2h::OK]])
            ->assertStatus(422)->assertJsonValidationErrors(['jawab']);

        $this->assertSame(0, P2hPeriksa::withoutGlobalScopes()->count());
    }

    public function test_p2h_kritis_gagal_wajib_foto(): void
    {
        $c = $this->perusahaan();
        $u = $this->unit($c);
        $this->actingAs($this->pekerja($c));

        $this->postJson(route('lapangan.p2h.simpan', $u), [
            'operator' => 'Agus', 'shift' => 'pagi', 'jawab' => $this->jawab('dump_truck', ['retarder' => P2h::TIDAK]),
        ])->assertStatus(422)->assertJsonValidationErrors(['foto']);

        $this->assertSame(P2h::LAIK, $u->refresh()->status);
    }

    public function test_p2h_kritis_gagal_menahan_unit_dan_membuka_satu_perintah_kerja(): void
    {
        $c = $this->perusahaan();
        $u = $this->unit($c, ['kode' => 'DT-1142']);
        $this->actingAs($this->pekerja($c));

        $kirim = fn (string $id) => $this->postJson(route('lapangan.p2h.simpan', $u), [
            'klien_id' => $id, 'operator' => 'Agus Pratama', 'shift' => 'pagi', 'catatan' => 'Berdecit di turunan.',
            'jawab' => $this->jawab('dump_truck', ['retarder' => P2h::TIDAK]),
            'foto' => [UploadedFile::fake()->image('retarder.jpg')],
        ]);

        $kirim('k-1')->assertCreated()->assertJson(['hasil' => P2h::DITAHAN]);

        $u->refresh();
        $this->assertSame(P2h::DITAHAN, $u->status);
        $this->assertNotNull($u->ditahan_sejak);
        $this->assertStringContainsString('Retarder', $u->ditahan_karena);

        $wo = WorkOrder::withoutGlobalScopes()->sole();
        $this->assertSame(['korektif', 'kritis', 'dibuka'], [$wo->jenis, $wo->prioritas, $wo->status]);
        $this->assertSame($c->id, $wo->company_id);
        $this->assertStringContainsString('DT-1142', $wo->gejala);
        $this->assertStringContainsString('Berdecit', $wo->gejala);

        /* P2H gagal berikutnya untuk unit yang masih ditahan tidak membuka
           perintah kerja kedua — dan kiriman ulang tidak mencatat apa pun. */
        $kirim('k-2')->assertCreated();
        $kirim('k-2')->assertOk()->assertJson(['baru' => false]);

        $this->assertSame(1, WorkOrder::withoutGlobalScopes()->count());
        $this->assertSame(2, P2hPeriksa::withoutGlobalScopes()->count());
        $this->assertSame([$wo->id], P2hPeriksa::withoutGlobalScopes()->pluck('work_order_id')->unique()->values()->all());
    }

    public function test_teks_dan_sifat_kritis_butir_diambil_dari_daftar_bukan_kiriman(): void
    {
        $n = P2h::nilai('dump_truck', $this->jawab('dump_truck', ['rem_servis' => P2h::TIDAK]));

        $this->assertSame(P2h::DITAHAN, $n['hasil']);
        $this->assertSame(['Rem servis berfungsi normal'], $n['kritisGagal']);

        $kode = array_column(P2h::butir('dump_truck'), 'kode');
        $this->assertSame(count($kode), count(array_unique($kode)), 'Kode butir kembar menimpa jawaban butir lain.');

        foreach (array_keys(P2h::JENIS) as $jenis) {
            $this->assertNotEmpty(array_filter(P2h::butir($jenis), fn ($b) => $b['kritis']), "Jenis {$jenis} tanpa butir kritis.");
        }
    }

    public function test_lepas_tahan_hanya_oleh_pengawas_dan_wajib_alasan(): void
    {
        $c = $this->perusahaan();
        $u = $this->unit($c, ['status' => P2h::DITAHAN, 'ditahan_sejak' => now(), 'ditahan_karena' => 'Rem']);

        $this->actingAs($this->pekerja($c));
        $this->post(route('lapangan.p2h.lepas', $u), ['catatan' => 'Sudah diperbaiki'])->assertForbidden();

        $ktt = $this->pekerja($c, ['lms_role' => 'ktt']);
        $this->actingAs($ktt);
        $this->post(route('lapangan.p2h.lepas', $u), ['catatan' => 'ok'])->assertSessionHasErrors('catatan');
        $this->assertSame(P2h::DITAHAN, $u->refresh()->status);

        $this->post(route('lapangan.p2h.lepas', $u), ['catatan' => 'Retarder diganti, uji jalan normal'])->assertRedirect();
        $u->refresh();
        $this->assertSame(P2h::LAIK, $u->status);
        $this->assertSame($ktt->id, $u->dilepas_oleh);
        $this->assertSame('Retarder diganti, uji jalan normal', $u->catatan_lepas);
        $this->assertTrue(ActivityLog::where('action', 'Lepas tahan unit P2H')->exists());
    }

    public function test_unit_perusahaan_lain_tidak_dapat_dibuka_maupun_diisi(): void
    {
        $lain = $this->unit($this->perusahaan());
        $c = $this->perusahaan();
        $this->actingAs($this->pekerja($c));

        $this->get(route('lapangan.p2h.isi', $lain))->assertNotFound();
        $this->postJson(route('lapangan.p2h.simpan', $lain), [
            'operator' => 'Agus', 'shift' => 'pagi', 'jawab' => $this->jawab('dump_truck'),
        ])->assertNotFound();

        $this->get(route('lapangan.p2h'))->assertInertia(fn (AssertableInertia $p) => $p->has('unit', 0));
    }

    /* ═══════════════ layar ═══════════════ */

    public function test_seluruh_layar_lapangan_dirender(): void
    {
        $c = $this->perusahaan();
        $w = $this->pekerja($c, ['name' => 'Ir. Budi Santoso']);
        $u = $this->unit($c);
        $this->actingAs($w);
        $h = HazardReport::withoutGlobalScopes()->findOrFail(
            $this->postJson(route('lapangan.lapor.simpan'), $this->lapor())->json('id'));

        foreach ([
            [route('lapangan.beranda'), 'Lapangan/Beranda'],
            [route('lapangan.modul'), 'Lapangan/Modul'],
            [route('lapangan.tugas'), 'Lapangan/Tugas'],
            [route('lapangan.profil'), 'Lapangan/Profil'],
            [route('lapangan.lapor'), 'Lapangan/Lapor'],
            [route('lapangan.laporan', $h), 'Lapangan/Laporan'],
            [route('lapangan.izin'), 'Lapangan/Izin'],
            [route('lapangan.sertifikat'), 'Lapangan/Sertifikat'],
            [route('lapangan.p2h'), 'Lapangan/P2hDaftar'],
            [route('lapangan.p2h.isi', $u), 'Lapangan/P2h'],
        ] as [$url, $komponen]) {
            $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->component($komponen));
        }

        /* Gelar di depan nama bukan nama panggilan. */
        $this->get(route('lapangan.beranda'))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('saya.depan', 'Budi')->where('saya.inisial', 'BS')
            /* Prop halaman tidak boleh menimpa prop bersama `pengguna`:
               pengirim kiriman luring membaca id-nya dari sana. */
            ->where('pengguna.id', $w->id));
    }

    public function test_matriks_dan_jenis_temuan_dikirim_ke_layar_lapor(): void
    {
        $this->actingAs($this->pekerja($this->perusahaan()));

        $this->get(route('lapangan.lapor'))->assertInertia(fn (AssertableInertia $p) => $p
            ->has('matriks.pita', 4)
            ->where('matriks.kemungkinan.5', 'Hampir pasti')
            ->where('matriks.keparahan.5', 'Fatal')
            ->has('jenis', 4)
            ->has('perusahaan', 1)
            ->where('maksFoto', 6));
    }

    public function test_tugas_pekerja_hanya_laporannya_sendiri_pengawas_seluruhnya(): void
    {
        $c = $this->perusahaan();
        $a = $this->pekerja($c);
        $b = $this->pekerja($c);

        foreach ([$a, $b] as $u) {
            $this->actingAs($u);
            $this->postJson(route('lapangan.lapor.simpan'), $this->lapor(['klien_id' => 'x'.$u->id]))->assertCreated();
        }

        $this->actingAs($a);
        $this->get(route('lapangan.tugas'))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('tindakan', fn ($t) => collect($t)->where('jenis', 'hazard')->count() === 1)
            ->has('laporanSaya', 1));

        $this->actingAs($this->pekerja($c, ['lms_role' => 'ktt']));
        $this->get(route('lapangan.tugas'))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('tindakan', fn ($t) => collect($t)->where('jenis', 'hazard')->count() === 2));
    }

    public function test_unit_ditahan_dan_belum_diperiksa_masuk_tindakan(): void
    {
        $c = $this->perusahaan();
        $this->unit($c, ['kode' => 'GD-03', 'status' => P2h::DITAHAN, 'ditahan_karena' => 'Rem']);
        $this->unit($c, ['kode' => 'LV-018']);
        $this->actingAs($this->pekerja($c));

        $this->get(route('lapangan.beranda'))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('tindakan', fn ($t) => collect($t)->contains(fn ($x) => str_contains($x['kode'], 'GD-03 · DITAHAN'))
                && collect($t)->contains(fn ($x) => $x['jenis'] === 'p2h' && str_contains($x['ket'], 'LV-018')))
            ->where('angka.tanpaLti', null));
    }

    /* ═══════════════ pintu masuk ═══════════════ */

    public function test_aplikasi_android_masuk_ke_mode_lapangan(): void
    {
        $c = $this->perusahaan();
        $this->pekerja($c, ['email' => 'lapangan@uji.test', 'password' => bcrypt('rahasia123')]);

        $this->withHeader('User-Agent', self::UA_APLIKASI)
            ->post('/login', ['email' => 'lapangan@uji.test', 'password' => 'rahasia123'])
            ->assertRedirect('/lapangan');

        $this->withHeader('User-Agent', self::UA_APLIKASI)->get('/')->assertRedirect('/lapangan');
    }

    public function test_peramban_biasa_tetap_ke_dasbor(): void
    {
        $c = $this->perusahaan();
        $this->pekerja($c, ['email' => 'kantor@uji.test', 'password' => bcrypt('rahasia123')]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130')
            ->post('/login', ['email' => 'kantor@uji.test', 'password' => 'rahasia123'])
            ->assertRedirect('/dashboard');
    }

    public function test_tamu_tidak_dapat_membuka_mode_lapangan(): void
    {
        $this->get(route('lapangan.beranda'))->assertRedirect('/login');
        $this->postJson(route('lapangan.lapor.simpan'), $this->lapor())->assertUnauthorized();
    }

    public function test_lokasi_dibuka_hanya_untuk_situs_sendiri_kamera_tetap_tertutup(): void
    {
        $this->actingAs($this->pekerja($this->perusahaan()));

        $kebijakan = (string) $this->get(route('lapangan.lapor'))->headers->get('Permissions-Policy');
        $this->assertStringContainsString('geolocation=(self)', $kebijakan);
        $this->assertStringContainsString('camera=()', $kebijakan);
        $this->assertStringContainsString('microphone=()', $kebijakan);
    }

    public function test_pekerja_latar_hanya_menyimpan_cakupan_lapangan(): void
    {
        $sw = file_get_contents(public_path('sw-lapangan.js'));

        $this->assertStringContainsString("u.pathname === '/lapangan' || u.pathname.startsWith('/lapangan/')", $sw);
        $this->assertStringContainsString("if (r.method !== 'GET') return;", $sw, 'Kiriman tidak boleh dijawab dari simpanan.');
        $this->assertStringContainsString("'bersihkan'", $sw, 'Simpanan harus dapat dibuang saat keluar.');
        $this->assertStringContainsString('!jawab.redirected', $sw, 'Halaman masuk hasil pengalihan tidak boleh disimpan.');
    }

    /* ═══════════════ web: P2H Unit ═══════════════ */

    public function test_web_p2h_mengelola_unit(): void
    {
        $c = $this->perusahaan();
        $this->actingAs($this->pekerja($c, ['lms_role' => 'ktt']));

        $this->get(route('maintenance.p2h'))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Maintenance/P2h')->has('unit', 0)->where('dapatMelepas', true));

        $this->post(route('maintenance.p2h.simpan'), ['kode' => 'EX-2201', 'nama' => 'Komatsu PC2000-8', 'jenis' => 'excavator'])
            ->assertSessionHasNoErrors();
        $this->post(route('maintenance.p2h.simpan'), ['kode' => 'EX-2201', 'nama' => 'Kembar', 'jenis' => 'excavator'])
            ->assertSessionHasErrors('kode');
        $this->post(route('maintenance.p2h.simpan'), ['kode' => 'X-1', 'nama' => 'Jenis asing', 'jenis' => 'pesawat'])
            ->assertSessionHasErrors('jenis');

        $u = P2hUnit::withoutGlobalScopes()->sole();
        $this->assertSame([$c->id, P2h::LAIK, true], [$u->company_id, $u->status, (bool) $u->aktif]);

        $this->put(route('maintenance.p2h.ubah', $u), ['kode' => 'EX-2201', 'nama' => 'Komatsu PC2000-8', 'jenis' => 'excavator', 'aktif' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse((bool) $u->refresh()->aktif);

        /* Kode yang sama di perusahaan lain bukan kembar. */
        $this->actingAs($this->pekerja($this->perusahaan()));
        $this->post(route('maintenance.p2h.simpan'), ['kode' => 'EX-2201', 'nama' => 'Unit lain', 'jenis' => 'excavator'])
            ->assertSessionHasNoErrors();
    }
}
