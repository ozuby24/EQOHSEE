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

    public function test_mandays_dasar_dibaca_dari_tabel_bukan_diketik(): void
    {
        // 460 pekerja jatuh pada baris 426–625; risiko Tinggi = kolom 16 hari.
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 460, 'kelas_risiko' => 'Tinggi', 'jumlah_auditor' => 2]);

        $this->assertSame('426–625', $m['rentang']);
        $this->assertSame(16, $m['dasar']);

        /* Kelas risiko memilih kolom, bukan sekadar keterangan. Bila ia
           tidak berpengaruh, tambang berisiko rendah ditagih hari sebanyak
           tambang berisiko tinggi. */
        $this->assertSame(13, SmkpTahap::mandays(
            ['jumlah_pekerja' => 460, 'kelas_risiko' => 'Menengah'])['dasar']);
        $this->assertSame(11, SmkpTahap::mandays(
            ['jumlah_pekerja' => 460, 'kelas_risiko' => 'Rendah'])['dasar']);
    }

    public function test_pekerja_melampaui_tabel_memakai_baris_terakhir(): void
    {
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 5_000_000, 'kelas_risiko' => 'Tinggi']);

        $this->assertSame(25, $m['dasar'], 'Tabel harus punya batas atas, bukan jatuh ke nol.');
    }

    public function test_pekerja_belum_diisi_memakai_baris_terkecil_bukan_terbesar(): void
    {
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 0, 'kelas_risiko' => 'Tinggi']);

        /* Nol berarti belum diisi, dan nol tidak masuk baris mana pun.
           Jatuh ke baris TERAKHIR, audit yang datanya belum lengkap menagih
           25 hari — angka yang tampak seperti jawaban sungguhan, tidak
           menimbulkan galat, dan karena itu tidak pernah dipertanyakan. */
        $this->assertSame(3, $m['dasar']);
        $this->assertSame('1–5', $m['rentang']);
    }

    public function test_faktor_penambah_dan_pengurang_menggeser_total(): void
    {
        $dasar = ['jumlah_pekerja' => 460, 'kelas_risiko' => 'Tinggi', 'jumlah_auditor' => 1];

        $m = SmkpTahap::mandays($dasar + [
            'faktor'    => ['jarak' => true, 'pengolahan' => true],
            'pengurang' => ['tanpa_mayor' => true],
        ]);

        $this->assertSame(2, $m['penambah']);
        $this->assertSame(1, $m['pengurang']);
        $this->assertSame(17, $m['total'], '16 + 2 − 1');
    }

    public function test_faktor_pengurang_tidak_menghabiskan_audit_sampai_nol(): void
    {
        $m = SmkpTahap::mandays([
            'jumlah_pekerja' => 3, 'kelas_risiko' => 'Rendah',      // dasar 2
            'pengurang' => array_fill_keys(array_keys(SmkpTahap::faktorPengurang()), true),
        ]);

        $this->assertSame(1, $m['total'], 'Audit sekurang-kurangnya satu hari.');
    }

    public function test_auditor_membagi_durasi_bukan_mandays(): void
    {
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 460, 'kelas_risiko' => 'Tinggi', 'jumlah_auditor' => 4]);

        /* Mandays satuan USAHA, durasi satuan WAKTU. Empat auditor
           mengerjakan 16 orang-hari dalam 4 hari — menambah auditor
           memperpendek waktu di lapangan, bukan mengurangi beban auditnya.
           Membagi mandays-nya membuat audit tampak makin murah tiap kali
           satu auditor ditambahkan. */
        $this->assertSame(16,  $m['total']);
        $this->assertSame(4.0, $m['durasi']);
    }

    public function test_alokasi_tahap_diturunkan_dari_durasi(): void
    {
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 460, 'kelas_risiko' => 'Tinggi', 'jumlah_auditor' => 1]);

        // Durasi 16 hari: Tahap I 10% = 1,6; sisanya Tahap II.
        $this->assertSame(16.0, $m['durasi']);
        $this->assertSame(1.6,  $m['tahap1']);
        $this->assertSame(14.4, $m['tahap2']);
    }

    public function test_tahap_satu_sekurang_kurangnya_satu_hari(): void
    {
        // Durasi 2 hari: 10% = 0,2 hari — tidak masuk akal sebagai kunjungan.
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 3, 'kelas_risiko' => 'Rendah', 'jumlah_auditor' => 1]);

        $this->assertSame(1.0, $m['tahap1']);
        $this->assertSame(1.0, $m['tahap2']);
    }

    public function test_pembagi_auditor_tidak_pernah_nol(): void
    {
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 460, 'kelas_risiko' => 'Tinggi', 'jumlah_auditor' => 0]);

        $this->assertSame(1, $m['auditor']);
        $this->assertSame(16.0, $m['durasi']);
    }

    public function test_kelas_risiko_asing_jatuh_ke_yang_paling_ketat(): void
    {
        /* Kelas yang tidak dikenal — mis. data lama bertuliskan "Sedang" —
           tidak boleh menjatuhkan hitungan ke kolom termurah diam-diam. */
        $m = SmkpTahap::mandays(['jumlah_pekerja' => 460, 'kelas_risiko' => 'Sedang']);

        $this->assertSame('Tinggi', $m['kelas']);
        $this->assertSame(16, $m['dasar']);
    }

    /* ---------- keselarasan Rencana Audit dengan hari kerja ---------- */

    /** Hitungan hari kerja untuk 460 pekerja risiko Tinggi, satu auditor. */
    private function mandaysUji(int $auditor = 1): array
    {
        return SmkpTahap::mandays([
            'jumlah_pekerja' => 460, 'kelas_risiko' => 'Tinggi', 'jumlah_auditor' => $auditor,
        ]);
    }

    private function selaras(array $rencana, int $auditor = 1): array
    {
        return collect(SmkpTahap::selarasRencana($rencana, $this->mandaysUji($auditor)))
            ->keyBy('kunci')->all();
    }

    public function test_jumlah_auditor_tim_harus_sama_dengan_pembagi_durasi(): void
    {
        $tugas = ['tugas' => [['nama' => 'Ir. Bambang'], ['nama' => 'Sdri. Rina']]];

        /* Durasi dibagi jumlah auditor. Bila tim yang benar-benar ditugaskan
           berbeda dari pembaginya, durasi di lapangan yang tercetak pada
           Rencana Audit bukan angka yang akan terjadi. */
        $this->assertFalse($this->selaras($tugas, 1)['auditor']['selaras']);
        $this->assertTrue($this->selaras($tugas, 2)['auditor']['selaras']);
    }

    public function test_pembagian_tugas_kosong_bukan_ketidakselarasan(): void
    {
        /* Belum diisi bukan salah — hanya pekerjaan yang belum dimulai.
           Menandainya merah membuat rencana yang baru dibuka tampak cacat. */
        $c = $this->selaras([], 2)['auditor'];

        $this->assertTrue($c['selaras']);
        $this->assertStringContainsString('belum diisi', $c['ket']);
    }

    public function test_rentang_tanggal_lebih_pendek_dari_alokasi_tahap_dua_ditandai(): void
    {
        // Tahap II menuntut 14,4 hari untuk satu auditor.
        $pendek = ['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-04'];
        $cukup  = ['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20'];

        $this->assertFalse($this->selaras($pendek)['tanggal']['selaras']);
        $this->assertStringContainsString('3 hari', $this->selaras($pendek)['tanggal']['ket']);
        $this->assertTrue($this->selaras($cukup)['tanggal']['selaras']);
    }

    public function test_rentang_sehari_dihitung_satu_hari_bukan_nol(): void
    {
        $c = $this->selaras(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-02']);

        $this->assertStringContainsString('1 hari', $c['tanggal']['ket']);
    }

    public function test_kegiatan_di_luar_rentang_tanggal_audit_ditandai(): void
    {
        $r = [
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20',
            'susunan' => [
                ['tanggal' => '2026-03-03', 'kegiatan' => 'Rapat pembukaan'],
                ['tanggal' => '2026-04-01', 'kegiatan' => 'Rapat penutupan'],
                ['tanggal' => '',           'kegiatan' => 'Belum dijadwalkan'],
            ],
        ];

        $c = $this->selaras($r)['susunan'];

        /* Baris tanpa tanggal bukan pelanggaran; yang di luar rentang iya.
           Jadwal penutupan yang jatuh sebulan setelah audit berakhir adalah
           salah ketik yang tidak pernah menimbulkan galat. */
        $this->assertFalse($c['selaras']);
        $this->assertStringContainsString('1 kegiatan', $c['ket']);
    }

    public function test_tanggal_belum_ditetapkan_tidak_dihitung_menyimpang(): void
    {
        foreach ($this->selaras([]) as $c) {
            $this->assertTrue($c['selaras'], "{$c['judul']} tidak boleh ditandai sebelum diisi.");
        }
    }

    public function test_halaman_rencana_membawa_hasil_pemeriksaan_keselarasan(): void
    {
        $this->masuk();
        $a = $this->audit(['permulaan' => ['jumlah_pekerja' => 460, 'jumlah_auditor' => 2]]);

        foreach ([route('smkp.rencana', $a), route('smkp.rencana.cetak', $a)] as $alamat) {
            $props = $this->get($alamat)->assertOk()->viewData('page')['props'];

            $this->assertSame(
                ['auditor', 'tanggal', 'susunan'],
                array_column($props['selaras'], 'kunci'),
                "Pemeriksaan keselarasan tidak sampai ke {$alamat}."
            );
        }
    }

    public function test_menyimpan_tahap_satu_mencatat_faktor_yang_tidak_dicentang(): void
    {
        // Kotak yang tidak dicentang tidak terkirim; jawabannya tetap harus
        // tercatat "tidak" agar Berita Acara punya isian lengkap.
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.tahap1.simpan', $a), [
            'permulaan' => [
                'jumlah_pekerja' => 460, 'jumlah_auditor' => 2,
                'faktor'    => ['jarak' => '1'],
                'pengurang' => ['tanpa_mayor' => '1'],
            ],
        ])->assertRedirect(route('smkp.tahap1', $a));

        $p = $a->fresh()->permulaan;

        $this->assertTrue($p['faktor']['jarak']);
        $this->assertFalse($p['faktor']['pengolahan']);
        $this->assertCount(count(SmkpTahap::faktorPenyesuaian()), $p['faktor']);

        // Daftar pengurang diperlakukan sama; tanpa itu "tidak" tersimpan
        // sebagai kosong dan Berita Acara punya isian yang tak terjawab.
        $this->assertTrue($p['pengurang']['tanpa_mayor']);
        $this->assertFalse($p['pengurang']['kepatuhan']);
        $this->assertCount(count(SmkpTahap::faktorPengurang()), $p['pengurang']);
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
            'permulaan' => ['tanggal_kontak' => '2026-02-15', 'jumlah_pekerja' => 460, 'jumlah_auditor' => 2],
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
