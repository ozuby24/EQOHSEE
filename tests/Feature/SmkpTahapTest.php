<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, User};
use App\Support\SmkpTahap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Dua tahap audit SMKP dan Rencana Audit.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019, dibaca lewat berkas audit
 * PT Gunung Bara Utama 2023 — angka pembanding pada uji mandays diambil dari
 * Berita Acara Tahap I dokumen tersebut.
 */
class SmkpTahapTest extends TestCase
{
    use RefreshDatabase;

    private function audit(array $atribut = []): SmkpAudit
    {
        return SmkpAudit::create(array_merge([
            'tahun'  => 2026,
            'status' => 'draft',
        ], $atribut));
    }

    private function masuk(): User
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        return $u;
    }

    /** Rencana Audit yang memenuhi kesembilan komponen. */
    private function rencanaLengkap(): array
    {
        return [
            'tujuan'          => 'Menilai penerapan SMKP Minerba.',
            'kriteria'        => 'Kepdirjen 185.K/37.04/DJB/2019.',
            'ruang_lingkup'   => 'Seluruh wilayah IUP.',
            'tanggal_mulai'   => '2026-03-02',
            'tanggal_selesai' => '2026-03-06',
            'susunan'         => [['tanggal' => '2026-03-02', 'kegiatan' => 'Rapat pembukaan']],
            'tugas'           => [['nama' => 'Lucky', 'peran' => 'Ketua Tim Auditor']],
            'sumberdaya'      => 'Kendaraan, APD, ruang rapat.',
            'metode'          => 'Wawancara, tinjauan dokumen, observasi lapangan.',
            'pengesahan'      => ['ktt' => ['nama' => 'Budi'], 'ketua' => ['nama' => 'Lucky']],
        ];
    }

    private function kecukupanPenuh(): array
    {
        $k = [];
        foreach (\App\Support\Smkp::elemen() as $e) {
            $k[$e['kode']] = ['status' => SmkpTahap::LENGKAP, 'ket' => ''];
        }
        return $k;
    }

    /* ---------- Rencana Audit ---------- */

    public function test_rencana_audit_menuntut_sembilan_komponen(): void
    {
        // Kepdirjen menyebut sembilan butir; kalau daftarnya menyusut,
        // laporan yang dihasilkan berhenti sah.
        $this->assertCount(9, SmkpTahap::komponenRencana());
    }

    public function test_rencana_kosong_belum_lengkap_dan_menyebut_yang_kurang(): void
    {
        $r = SmkpTahap::rekapRencana([]);

        $this->assertFalse($r['lengkap']);
        $this->assertSame(0, $r['jumlah']);
        $this->assertCount(9, $r['kurang'], 'Seluruh komponen harus disebut sebagai kekurangan.');
    }

    public function test_rencana_terisi_penuh_dinyatakan_lengkap(): void
    {
        $r = SmkpTahap::rekapRencana($this->rencanaLengkap());

        $this->assertTrue($r['lengkap']);
        $this->assertSame(9, $r['jumlah']);
        $this->assertSame([], $r['kurang']);
    }

    public function test_tanggal_setengah_terisi_belum_menghitung_sebagai_komponen(): void
    {
        $d = $this->rencanaLengkap();
        unset($d['tanggal_selesai']);

        $r = SmkpTahap::rekapRencana($d);
        $this->assertFalse($r['terisi']['tanggal']);
    }

    public function test_baris_tabel_kosong_tidak_dianggap_isi(): void
    {
        // Formulir selalu mengirim satu baris kosong; itu tidak boleh
        // membuat susunan kegiatan tampak sudah ditetapkan.
        $d = $this->rencanaLengkap();
        $d['susunan'] = [['tanggal' => '', 'waktu' => '', 'kegiatan' => '', 'auditi' => '', 'auditor' => '']];

        $this->assertFalse(SmkpTahap::rekapRencana($d)['terisi']['susunan']);
    }

    public function test_pengesahan_pjo_tidak_wajib_bagi_pemegang_iup(): void
    {
        // PJO hanya diperlukan bila auditi perusahaan jasa pertambangan.
        $d = $this->rencanaLengkap();
        $this->assertTrue(SmkpTahap::rekapRencana($d)['terisi']['pengesahan']);

        unset($d['pengesahan']['ktt']);
        $this->assertFalse(SmkpTahap::rekapRencana($d)['terisi']['pengesahan'], 'KTT tetap wajib.');
    }

    public function test_menyimpan_rencana_membuang_baris_kosong(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.rencana.simpan', $a), $this->rencanaLengkap() + [
            'susunan' => [
                ['tanggal' => '2026-03-02', 'kegiatan' => 'Rapat pembukaan'],
                ['tanggal' => '', 'kegiatan' => ''],
            ],
        ])->assertRedirect(route('smkp.rencana', $a));

        $this->assertCount(1, $a->fresh()->rencana['susunan']);
    }

    public function test_tanggal_selesai_tidak_boleh_mendahului_tanggal_mulai(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.rencana.simpan', $a), [
            'tanggal_mulai'   => '2026-03-06',
            'tanggal_selesai' => '2026-03-02',
        ])->assertSessionHasErrors('tanggal_selesai');
    }

    /* ---------- laporan Rencana Audit ---------- */

    public function test_laporan_rencana_audit_memuat_kesembilan_judul_komponen(): void
    {
        $this->masuk();
        $a = $this->audit(['rencana' => $this->rencanaLengkap()]);

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')
                ->where('mode', 'rencana')->where('rekap.lengkap', true)->has('komponen', 9)
        );
    }

    public function test_laporan_rencana_audit_menampilkan_isian_dan_pengesah(): void
    {
        $this->masuk();
        $a = $this->audit([
            'rencana' => $this->rencanaLengkap(),
            'risiko'  => ['present' => [['kegiatan' => 'Pengoperasian unit di jalan hauling', 'risiko' => 'Fatality', 'nilai' => 15]]],
        ]);

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')
                ->where('audit.rencana.tujuan', 'Menilai penerapan SMKP Minerba.')
                ->where('audit.risiko.present.0.kegiatan', 'Pengoperasian unit di jalan hauling')
                ->where('audit.rencana.pengesahan.ktt.nama', 'Budi')
                ->where('audit.rencana.pengesahan.ketua.nama', 'Lucky')
        );
    }

    public function test_laporan_rencana_audit_tetap_terbuka_saat_belum_lengkap(): void
    {
        // Auditor perlu mencetak konsep untuk dibahas; menutup laporan sampai
        // lengkap justru menghalangi pekerjaan yang melengkapinya.
        $this->masuk();
        $a = $this->audit();

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')->where('rekap.lengkap', false)
        );
    }

    /* ---------- Tahap I ---------- */

    public function test_kecukupan_yang_belum_ditinjau_tidak_dihitung_lengkap(): void
    {
        $r = SmkpTahap::rekapKecukupan(['I' => ['status' => SmkpTahap::LENGKAP]]);

        $this->assertSame(1, $r['lengkap']);
        $this->assertSame(6, $r['belum']);
        $this->assertFalse($r['siap']);
    }

    public function test_mandays_mengikuti_berita_acara_pt_gbu_2023(): void
    {
        // Berita Acara Tahap I PT GBU 2023: 16 hari / 2 auditor = 8 hari,
        // penyesuaian 0 hari, total 8 hari, Tahap I 0,8 dan Tahap II 7,2.
        $m = SmkpTahap::mandays(['mandays_dasar' => 16, 'jumlah_auditor' => 2, 'penyesuaian' => 0]);

        $this->assertSame(8.0,  $m['per_auditor']);
        $this->assertSame(8.0,  $m['total']);
        $this->assertSame(0.8,  $m['tahap1']);
        $this->assertSame(7.2,  $m['tahap2']);
    }

    public function test_penyesuaian_diusulkan_dari_kondisi_yang_terpenuhi(): void
    {
        $m = SmkpTahap::mandays([
            'mandays_dasar'  => 10,
            'jumlah_auditor' => 2,
            'faktor'         => ['jarak' => true, 'pengolahan' => true],
        ]);

        $this->assertSame(2, $m['usul_penyesuaian']);
        $this->assertSame(7.0, $m['total'], 'Usulan dipakai bila auditor tidak menetapkan angka sendiri.');
    }

    public function test_penetapan_auditor_mengalahkan_usulan(): void
    {
        $m = SmkpTahap::mandays([
            'mandays_dasar'  => 10,
            'jumlah_auditor' => 2,
            'faktor'         => ['jarak' => true, 'pengolahan' => true],
            'penyesuaian'    => 1,
        ]);

        $this->assertSame(1.0, $m['penyesuaian']);
        $this->assertSame(6.0, $m['total']);
    }

    public function test_pembagi_auditor_tidak_pernah_nol(): void
    {
        $m = SmkpTahap::mandays(['mandays_dasar' => 8, 'jumlah_auditor' => 0]);
        $this->assertSame(8.0, $m['per_auditor']);
    }

    public function test_menyimpan_tahap_satu_mencatat_faktor_yang_tidak_dicentang(): void
    {
        // Kotak yang tidak dicentang tidak terkirim; jawabannya tetap harus
        // tercatat "tidak" agar Berita Acara punya isian lengkap.
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.tahap1.simpan', $a), [
            'permulaan' => ['mandays_dasar' => 16, 'jumlah_auditor' => 2, 'faktor' => ['jarak' => '1']],
        ])->assertRedirect(route('smkp.tahap1', $a));

        $f = $a->fresh()->permulaan['faktor'];
        $this->assertTrue($f['jarak']);
        $this->assertFalse($f['pengolahan']);
        $this->assertCount(7, $f);
    }

    public function test_kecukupan_hanya_disimpan_untuk_elemen_yang_ada(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.tahap1.simpan', $a), [
            'kecukupan' => [
                'I'   => ['status' => SmkpTahap::LENGKAP],
                'XIV' => ['status' => SmkpTahap::LENGKAP],   // elemen tidak ada
            ],
        ]);

        $this->assertSame(['I'], array_keys($a->fresh()->kecukupan));
    }

    public function test_berita_acara_menampilkan_tujuh_elemen_dan_hitungan_mandays(): void
    {
        $this->masuk();
        $a = $this->audit([
            'permulaan' => ['mandays_dasar' => 16, 'jumlah_auditor' => 2, 'penyesuaian' => 0],
            'kecukupan' => $this->kecukupanPenuh(),
        ]);

        $this->get(route('smkp.berita-acara', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')
                ->where('mode', 'berita')->has('elemen', 7)->has('mandays')
        );
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_tahapan_terbuka(): void
    {
        $this->masuk();
        $a = $this->audit([
            'permulaan' => ['mandays_dasar' => 16, 'jumlah_auditor' => 2],
            'kecukupan' => $this->kecukupanPenuh(),
            'rencana'   => $this->rencanaLengkap(),
            'risiko'    => ['present' => [['kegiatan' => 'Hauling', 'risiko' => 'Fatality', 'nilai' => 15]]],
        ]);

        foreach ([
            route('smkp.show', $a),
            route('smkp.tahap1', $a),
            route('smkp.berita-acara', $a),
            route('smkp.rencana', $a),
            route('smkp.rencana.cetak', $a),
            route('smkp.rapat', $a),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_halaman_tahapan_terbuka_pada_audit_yang_masih_kosong(): void
    {
        // Audit baru dibuat: seluruh kolom JSON masih null.
        $this->masuk();
        $a = $this->audit();

        foreach ([
            route('smkp.show', $a),
            route('smkp.tahap1', $a),
            route('smkp.berita-acara', $a),
            route('smkp.rencana', $a),
            route('smkp.rencana.cetak', $a),
            route('smkp.rapat', $a),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_ringkasan_audit_menautkan_laporan_rencana_audit(): void
    {
        $this->masuk();
        $a = $this->audit();

        $props = $this->get(route('smkp.show', $a))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(route('smkp.rencana.cetak', $a), $props['tautan']['rencanaCetak']);
    }

    /* ---------- perpindahan tahap ---------- */

    public function test_tahap_dua_tertahan_selama_tahap_satu_belum_tuntas(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.tahap', $a), ['tahap' => SmkpTahap::LAPANGAN])
            ->assertSessionHasErrors('tahap');

        $this->assertSame(SmkpTahap::PERMULAAN, $a->fresh()->tahap);
    }

    public function test_tahap_dua_terbuka_setelah_kecukupan_dan_rencana_lengkap(): void
    {
        $this->masuk();
        $a = $this->audit([
            'kecukupan' => $this->kecukupanPenuh(),
            'rencana'   => $this->rencanaLengkap(),
        ]);

        $this->assertTrue($a->siapTahapDua());

        $this->post(route('smkp.tahap', $a), ['tahap' => SmkpTahap::LAPANGAN])
            ->assertSessionHasNoErrors();

        $this->assertSame(SmkpTahap::LAPANGAN, $a->fresh()->tahap);
    }

    public function test_rencana_lengkap_saja_belum_membuka_tahap_dua(): void
    {
        $a = $this->audit(['rencana' => $this->rencanaLengkap()]);
        $this->assertFalse($a->siapTahapDua(), 'Kecukupan dokumentasi juga menentukan.');
    }

    /* ---------- daftar hadir ---------- */

    public function test_peserta_rapat_tercatat_per_rapat(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.rapat.simpan', $a), ['rapat' => 'pembukaan', 'nama' => 'Ridwan', 'jabatan' => 'OHS Officer']);
        $this->post(route('smkp.rapat.simpan', $a), ['rapat' => 'penutupan', 'nama' => 'Budi']);

        $this->assertCount(1, $a->hadir('pembukaan'));
        $this->assertCount(1, $a->hadir('penutupan'));
    }

    public function test_rapat_pembukaan_memindahkan_audit_ke_tahap_lapangan(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.rapat.simpan', $a), ['rapat' => 'pembukaan', 'nama' => 'Ridwan']);

        $this->assertSame(SmkpTahap::LAPANGAN, $a->fresh()->tahap);
    }

    public function test_rapat_asing_ditolak(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.rapat.simpan', $a), ['rapat' => 'makan-siang', 'nama' => 'X'])
            ->assertSessionHasErrors('rapat');
    }

    public function test_daftar_hadir_siap_cetak_memuat_pesertanya(): void
    {
        $this->masuk();
        $a = $this->audit();
        $a->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Ridwan', 'jabatan' => 'OHS Officer']);

        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')
                ->where('mode', 'hadir')->where('judul', 'Rapat Pembukaan')->where('hadir.0.nama', 'Ridwan')
        );
    }

    public function test_daftar_hadir_rapat_tak_dikenal_menghasilkan_404(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->get(route('smkp.hadir.cetak', [$a, 'makan-siang']))->assertNotFound();
    }

    public function test_peserta_audit_lain_tidak_dapat_dihapus_lewat_audit_ini(): void
    {
        $this->masuk();
        $a = $this->audit();
        $b = $this->audit(['tahun' => 2027]);
        $h = $b->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Ridwan']);

        $this->delete(route('smkp.rapat.hapus', [$a, $h]))->assertNotFound();
        $this->assertDatabaseHas('smkp_attendees', ['id' => $h->id]);
    }

    public function test_menghapus_audit_ikut_menghapus_daftar_hadirnya(): void
    {
        $a = $this->audit();
        $h = $a->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Ridwan']);

        $a->delete();

        $this->assertDatabaseMissing('smkp_attendees', ['id' => $h->id]);
    }

    /* ---------- alur & menu ---------- */

    public function test_alur_membagi_audit_menjadi_empat_babak(): void
    {
        $alur = SmkpTahap::alur();

        $this->assertSame(['permulaan','rencana','lapangan','pelaporan'], array_keys($alur));
        foreach ($alur as $kunci => $babak) {
            $this->assertNotEmpty($babak['langkah'], "Babak {$kunci} tanpa langkah.");
            $this->assertNotEmpty($babak['ket'],     "Babak {$kunci} tanpa penjelasan.");
        }
    }

    public function test_tiap_langkah_menjelaskan_dirinya_dan_punya_rute(): void
    {
        // Menu yang hanya berisi judul tidak memberi tahu auditor apa yang
        // harus dikerjakan; keterangan tiap langkah karena itu wajib ada.
        foreach (SmkpTahap::alur() as $babak) {
            foreach ($babak['langkah'] as $l) {
                $this->assertNotEmpty($l['ket'], "Langkah {$l['kunci']} tanpa keterangan.");
                $this->assertContains($l['jenis'], ['kerja','cetak']);
                $this->assertTrue(\Illuminate\Support\Facades\Route::has($l['rute']), "Rute {$l['rute']} tidak ada.");
            }
        }
    }

    public function test_tiap_langkah_punya_status_yang_dihitung(): void
    {
        $a = $this->audit();
        $status = $a->statusAlur();

        foreach (SmkpTahap::alur() as $babak) {
            foreach ($babak['langkah'] as $l) {
                $this->assertArrayHasKey($l['kunci'], $status, "Langkah {$l['kunci']} tanpa status.");
                $this->assertArrayHasKey('selesai', $status[$l['kunci']]);
            }
        }
    }

    public function test_audit_kosong_belum_menyelesaikan_langkah_apa_pun(): void
    {
        $status = $this->audit()->statusAlur();
        $selesai = array_filter($status, fn ($s) => $s['selesai']);

        $this->assertSame([], array_keys($selesai));
    }

    public function test_status_langkah_ikut_isian_yang_tersimpan(): void
    {
        $a = $this->audit([
            'kecukupan' => $this->kecukupanPenuh(),
            'rencana'   => $this->rencanaLengkap(),
            'permulaan' => ['tanggal_kontak' => '2026-02-15', 'mandays_dasar' => 16, 'jumlah_auditor' => 2],
        ]);

        $s = $a->statusAlur();

        $this->assertTrue($s['kontak']['selesai']);
        $this->assertTrue($s['mandays']['selesai']);
        $this->assertTrue($s['kecukupan']['selesai']);
        $this->assertTrue($s['rencana-cetak']['selesai']);
        $this->assertFalse($s['pembukaan']['selesai'], 'Belum ada peserta rapat.');
    }

    public function test_ringkasan_menampilkan_seluruh_langkah_alur_inertia(): void
    {
        $this->masuk();
        $a = $this->audit();
        $props = $this->get(route('smkp.show', $a))->assertOk()->viewData('page')['props'];

        foreach (SmkpTahap::alur() as $kunci => $babak) {
            $this->assertSame($babak['judul'], $props['alur'][$kunci]['judul']);
            $judul = array_column($props['alur'][$kunci]['langkah'], 'judul');
            foreach ($babak['langkah'] as $l) {
                $this->assertContains($l['judul'], $judul);
            }
        }
    }

    public function legacy_ringkasan_menampilkan_seluruh_langkah_alur(): void
    {
        $this->masuk();
        $a = $this->audit();

        $res = $this->get(route('smkp.show', $a))->assertOk();

        // Tanpa argumen kedua, teks yang diharapkan ikut di-escape seperti
        // Blade melakukannya — judul yang memuat "&" karena itu tetap cocok.
        $res->assertInertia(fn (AssertableInertia $p) => $p->component('Smkp/Halaman')->where('mode', 'show')->has('audit'));
    }

    /* ---------- menu samping ---------- */

    public function test_pintasan_menu_menyalurkan_ke_audit_yang_berjalan(): void
    {
        $this->masuk();
        $selesai  = $this->audit(['tahun' => 2024, 'status' => 'selesai']);
        $berjalan = $this->audit(['tahun' => 2026, 'status' => 'berjalan']);

        $this->get(route('smkp.ke.tahap1'))->assertRedirect(route('smkp.tahap1', $berjalan));
        $this->get(route('smkp.ke.rencana'))->assertRedirect(route('smkp.rencana', $berjalan));
        $this->get(route('smkp.ke.laporan'))->assertRedirect(route('smkp.laporan', $berjalan));
    }

    public function test_pintasan_memakai_periode_terakhir_bila_semua_sudah_selesai(): void
    {
        // Berkas cetak audit yang sudah ditutup tetap harus dapat dibuka.
        $this->masuk();
        $this->audit(['tahun' => 2024, 'status' => 'selesai']);
        $akhir = $this->audit(['tahun' => 2025, 'status' => 'selesai']);

        $this->get(route('smkp.ke.laporan'))->assertRedirect(route('smkp.laporan', $akhir));
    }

    public function test_pintasan_mengarahkan_membuat_periode_saat_belum_ada(): void
    {
        $this->masuk();

        $this->get(route('smkp.ke.tahap1'))->assertRedirect(route('smkp.create'));
    }

    public function test_halaman_acuan_memuat_tujuh_elemen_dan_bobotnya(): void
    {
        $this->masuk();

        $props = $this->get(route('smkp.acuan'))->assertOk()->viewData('page')['props'];

        foreach (\App\Support\Smkp::elemen() as $e) {
            $this->assertContains($e['nama'], array_column($props['elemen'], 'nama'));
        }
        $this->assertSame(\App\Support\Smkp::totalNilai(), $props['meta']['total_nilai']);
    }

    public function test_menu_samping_audit_memuat_seluruh_kelompoknya(): void
    {
        $this->masuk();

        $props = $this->get(route('smkp.index'))->assertOk()->viewData('page')['props'];
        $group = array_column($props['menu']['grup'], 'nama');
        $this->assertContains('Tahap Audit', $group);
        $this->assertContains('Berkas Resmi', $group);
        $this->assertContains('Acuan', $group);
    }

    public function test_acuan_menampilkan_rentang_kategori_dan_tingkat_dengan_benar_inertia(): void
    {
        $this->masuk();
        $props = $this->get(route('smkp.acuan'))->assertOk()->viewData('page')['props'];
        $kategori = array_column($props['kategori'], 'ket');
        $ambang = array_column($props['tingkat'], 'min');

        $this->assertContains(
            collect(\App\Support\Smkp::kategori())->firstWhere('kode', 'minor')['ket'],
            $kategori,
        );
        $this->assertContains(
            collect(\App\Support\Smkp::kategori())->firstWhere('kode', 'mayor')['ket'],
            $kategori,
        );
        $this->assertSame([85, 60, 0], $ambang);
    }

    public function legacy_acuan_menampilkan_rentang_kategori_dan_tingkat_dengan_benar(): void
    {
        // Ambang tersimpan sebagai persen (100/50/0), bukan pecahan; salah
        // membacanya membuat "Minor" tampil sebagai capaian penuh.
        $this->masuk();

        $this->get(route('smkp.acuan'))
            ->assertOk()
            ->assertSee('Capaian 50% sampai kurang dari 100%.')
            ->assertSee('Capaian kurang dari 50%.')
            ->assertSee('≥ 85', false)          // Baik
            ->assertSee('60 – 84', false)       // Perlu Perbaikan
            ->assertSee('0 – 59', false);       // Perlu Perhatian Serius
    }
}
