<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Pjp\{Evaluasi, Laporan, Pjp, SmkpItem, SmkpJawaban, SmkpKategori};
use App\Models\User;
use App\Support\Pjp\DaftarPeriksaSmkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aritmetika skor PJP.
 *
 * Ketiga skor di sini adalah angka yang dipakai memutuskan apakah
 * sebuah perusahaan jasa boleh menangani pekerjaan berisiko tinggi.
 * Yang diuji karena itu bukan "apakah fungsinya jalan" melainkan
 * pembedaan yang paling mudah hilang saat kodenya disederhanakan:
 * N/A yang bukan nol, nol yang bukan null, dan terendah yang bukan
 * rata-rata.
 */
class PjpSkorTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        DaftarPeriksaSmkp::pasang();

        $this->c = Company::create(['name' => 'PT Uji PJP']);
    }

    private function mitra(array $ganti = []): Pjp
    {
        return Pjp::withoutGlobalScopes()->create($ganti + [
            'company_id' => $this->c->id,
            'nama_perusahaan' => 'PT Mitra Jasa',
        ]);
    }

    /** Jawab seluruh butir berbobot dengan nilai penuh. */
    private function isiPenuh(Pjp $pjp): void
    {
        SmkpItem::whereHas('kategori', fn ($q) => $q->where('kode', '!=', SmkpKategori::LEGALITAS))
            ->get()
            ->each(fn (SmkpItem $b) => SmkpJawaban::create([
                'pjp_id' => $pjp->id, 'item_id' => $b->id, 'jawaban' => 'ya', 'nilai' => '3',
            ]));
    }

    /* ═══════════ daftar periksa SMKP ═══════════ */

    #[Test]
    public function skor_nol_persen_saat_belum_ada_jawaban(): void
    {
        $skor = $this->mitra()->smkpScore();

        $this->assertSame(0.0, $skor['persentase']);

        /* Penyebutnya harus tetap penuh. Bila butir yang belum diisi
           ikut dikeluarkan dari penyebut seperti butir N/A, daftar
           periksa yang sama sekali kosong akan menghasilkan 0/0 — lalu
           dibaca sebagai nilai sempurna oleh tampilan mana pun yang
           menampilkan persentasenya. */
        $this->assertSame(178, $skor['total_bobot']);
    }

    #[Test]
    public function skor_seratus_persen_saat_semua_butir_bernilai_penuh(): void
    {
        $pjp = $this->mitra();
        $this->isiPenuh($pjp);

        $this->assertSame(100.0, $pjp->smkpScore()['persentase']);
    }

    #[Test]
    public function butir_bernilai_na_keluar_dari_pembilang_maupun_penyebut(): void
    {
        $pjp = $this->mitra();

        $kategori = SmkpKategori::where('kode', '!=', SmkpKategori::LEGALITAS)
            ->orderBy('urutan')->first();

        foreach ($kategori->items as $i => $butir) {
            SmkpJawaban::create([
                'pjp_id' => $pjp->id, 'item_id' => $butir->id,
                'jawaban' => $i === 0 ? 'ya' : 'na',
                'nilai'   => $i === 0 ? '3'  : 'na',
            ]);
        }

        $rincian = collect($pjp->smkpCategoryBreakdown())->firstWhere('kode', $kategori->kode);

        $this->assertSame($kategori->items->first()->bobot, $rincian['bobot_dinilai']);
        $this->assertSame(100.0, $rincian['persentase']);
    }

    #[Test]
    public function butir_yang_belum_dijawab_tetap_menyumbang_bobot_dengan_skor_nol(): void
    {
        $pjp = $this->mitra();

        $kategori = SmkpKategori::where('kode', '!=', SmkpKategori::LEGALITAS)
            ->orderBy('urutan')->first();

        $rincian = collect($pjp->smkpCategoryBreakdown())->firstWhere('kode', $kategori->kode);

        $this->assertSame((int) $kategori->items->sum('bobot'), $rincian['bobot_dinilai']);
        $this->assertSame(0.0, $rincian['persentase']);
    }

    #[Test]
    public function legalitas_dihitung_terpisah_dan_tidak_masuk_rincian_berbobot(): void
    {
        $pjp = $this->mitra();

        $butir = SmkpKategori::where('kode', SmkpKategori::LEGALITAS)->first()->items;
        $this->assertGreaterThan(0, $butir->count());

        SmkpJawaban::create([
            'pjp_id' => $pjp->id, 'item_id' => $butir->first()->id,
            'jawaban' => 'ya', 'nilai' => '3',
        ]);

        $status = $pjp->smkpLegalitasStatus();

        $this->assertSame($butir->count(), $status['total']);
        $this->assertSame(1, $status['lengkap']);

        /* Nilai '3' di atas sengaja ikut diisi: bila LEGALITAS bocor ke
           dalam rincian berbobot, satu butir bernilai penuh akan
           menaikkan skor kepatuhan tanpa ada yang menjawab satu pun
           pertanyaan A–P. */
        $this->assertNotContains(
            SmkpKategori::LEGALITAS,
            array_column($pjp->smkpCategoryBreakdown(), 'kode'),
        );
        $this->assertSame(0.0, $pjp->smkpScore()['persentase']);
    }

    #[Test]
    public function kelayakan_naik_bersama_skornya_bukan_turun(): void
    {
        /* Label "Kritis" pada skor 100% berarti mitra ini LAYAK
           menangani pekerjaan berisiko kritis. Membalik tangganya
           adalah kesalahan yang tidak menimbulkan galat dan langsung
           dipakai orang memutuskan penugasan. */
        $pjp = $this->mitra();
        $this->assertSame('Sangat Rendah', $pjp->smkpScore()['kelayakan']);

        $this->isiPenuh($pjp);
        $this->assertSame('Kritis', $pjp->fresh()->smkpScore()['kelayakan']);
    }

    /* ═══════════ kepatuhan pelaporan ═══════════ */

    #[Test]
    public function pelaporan_null_saat_belum_pernah_mengunggah(): void
    {
        $this->assertNull($this->mitra()->pelaporanScore());
    }

    #[Test]
    public function pelaporan_hanya_memakai_ketepatan_waktu_saat_belum_ada_yang_diperiksa(): void
    {
        $pjp = $this->mitra();

        $this->laporan($pjp, hari: 1);
        $this->laporan($pjp, hari: 10);

        $this->assertSame(50.0, $pjp->pelaporanScore());
    }

    #[Test]
    public function pelaporan_merata_ratakan_ketepatan_dan_kesesuaian(): void
    {
        $pjp = $this->mitra();

        $this->laporan($pjp, hari: 1, sesuai: 'sesuai');
        $this->laporan($pjp, hari: 1, sesuai: 'tidak_sesuai');

        // tepat waktu 100, kesesuaian 50 → 75.
        $this->assertSame(75.0, $pjp->pelaporanScore());
    }

    #[Test]
    public function dokumen_yang_belum_diperiksa_tidak_dihitung_tidak_sesuai(): void
    {
        $pjp = $this->mitra();

        $this->laporan($pjp, hari: 1, sesuai: 'sesuai');
        $this->laporan($pjp, hari: 1);

        /* Belum diperiksa berarti belum diketahui. Dihitung sebagai
           tidak sesuai, angka kepatuhan turun setiap kali mitranya
           MENGIRIM dokumen — persis kebalikan dari yang dimaksudkan. */
        $this->assertSame(100.0, $pjp->pelaporanScore());
    }

    /* ═══════════ ketepatan waktu dan zona tambang ═══════════ */

    #[Test]
    public function ketepatan_waktu_dihitung_menurut_zona_tambang_bukan_utc(): void
    {
        config(['waktu.zona' => 'Asia/Makassar']);   // WITA, +8

        $pjp = $this->mitra();

        /* 4 September pukul 07.00 WITA — terlambat. Tersimpan sebagai
           3 September pukul 23.00 UTC, dan dinilai dengan hari UTC ia
           akan lolos sebagai tepat waktu. */
        $telat = $this->laporan($pjp, saat: Carbon::parse('2026-09-03 23:00:00', 'UTC'));

        /* 3 September pukul 07.00 WITA — tepat waktu, meski jamnya di
           UTC masih 2 September. */
        $tepat = $this->laporan($pjp, saat: Carbon::parse('2026-09-02 23:00:00', 'UTC'));

        $this->assertFalse($telat->tepat_waktu, 'Terlambat menurut waktu tambang tetapi dinilai tepat waktu.');
        $this->assertTrue($tepat->tepat_waktu, 'Tepat waktu menurut waktu tambang tetapi dinilai terlambat.');
    }

    #[Test]
    public function menunggak_dihitung_atas_bulan_menurut_zona_tambang(): void
    {
        config(['waktu.zona' => 'Asia/Makassar']);

        /* 10 September pukul 08.00 WITA: tanggal batas sudah lewat. */
        Carbon::setTestNow(Carbon::parse('2026-09-10 00:00:00', 'UTC'));

        $pjp = $this->mitra();

        /* Dikirim 1 September pukul 07.00 WITA — masih tanggal 31
           Agustus menurut UTC. Penyaring yang membandingkan bulan UTC
           tidak akan menemukannya dan melaporkan mitra ini menunggak
           padahal sudah mengirim tepat waktu. */
        $this->laporan($pjp, saat: Carbon::parse('2026-08-31 23:00:00', 'UTC'));

        $this->assertSame([], Pjp::belumLaporanBulananBulanIni()->pluck('id')->all());

        Carbon::setTestNow();
    }

    #[Test]
    public function menunggak_kosong_selama_tanggal_batas_belum_terlewati(): void
    {
        config(['waktu.zona' => 'Asia/Makassar']);
        Carbon::setTestNow(Carbon::parse('2026-09-02 00:00:00', 'UTC'));

        $this->mitra();

        $this->assertTrue(Pjp::belumLaporanBulananBulanIni()->isEmpty());

        Carbon::setTestNow();
    }

    #[Test]
    public function menunggak_menyebut_yang_mengirim_lewat_tanggal_batas(): void
    {
        config(['waktu.zona' => 'Asia/Makassar']);
        Carbon::setTestNow(Carbon::parse('2026-09-20 00:00:00', 'UTC'));

        $telat = $this->mitra(['nama_perusahaan' => 'PT Telat']);
        $this->laporan($telat, saat: Carbon::parse('2026-09-09 00:00:00', 'UTC'));

        $mati = $this->mitra(['nama_perusahaan' => 'PT Berhenti', 'status' => 'tidak_aktif']);

        $daftar = Pjp::belumLaporanBulananBulanIni()->pluck('id')->all();

        $this->assertContains($telat->id, $daftar);
        $this->assertNotContains($mati->id, $daftar, 'Mitra yang tidak lagi dipantau ikut ditagih.');

        Carbon::setTestNow();
    }

    /* ═══════════ achievement ═══════════ */

    #[Test]
    public function achievement_nol_bukan_null_saat_daftar_periksa_kosong(): void
    {
        $this->assertSame(0.0, $this->mitra()->achievement());
    }

    #[Test]
    public function achievement_memakai_yang_terendah_bukan_rata_rata(): void
    {
        $pjp = $this->mitra();

        $this->isiPenuh($pjp);
        $this->laporan($pjp, hari: 1);

        Evaluasi::create([
            'pjp_id' => $pjp->id, 'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 30, 'skor_keselamatan_kesehatan' => 30, 'skor_lingkungan' => 30,
        ]);

        /* Rata-ratanya 76,7 — terbaca cukup. Yang terendah 30. */
        $this->assertSame(30.0, $pjp->fresh()->achievement());
    }

    #[Test]
    public function achievement_mengabaikan_yang_masih_null(): void
    {
        $pjp = $this->mitra();
        $this->isiPenuh($pjp);

        $this->assertSame(100.0, $pjp->fresh()->achievement());
    }

    /* ═══════════ ongkos kueri ═══════════ */

    #[Test]
    public function menghitung_skor_banyak_mitra_tidak_menambah_kueri_per_mitra(): void
    {
        /* Kit asalnya membaca tiga relasi ditambah dua tabel acuan pada
           TIAP mitra — terukur 36 kueri untuk 7 mitra, dan tumbuh lurus
           sesudahnya. Yang dijaga di sini bukan angkanya melainkan
           bentuknya: menambah mitra tidak boleh menambah kueri. */
        foreach (range(1, 3) as $n) {
            $pjp = $this->mitra(['nama_perusahaan' => "PT Mitra {$n}"]);
            $this->laporan($pjp, hari: 1);
        }

        $tiga = $this->kueriUntukSkorSemua();

        foreach (range(4, 9) as $n) {
            $pjp = $this->mitra(['nama_perusahaan' => "PT Mitra {$n}"]);
            $this->laporan($pjp, hari: 1);
        }

        $sembilan = $this->kueriUntukSkorSemua();

        $this->assertSame($tiga, $sembilan,
            "Menghitung skor 9 mitra memakai {$sembilan} kueri, sedangkan 3 mitra memakai {$tiga}. ".
            'Ongkosnya tumbuh per mitra — eager load-nya terlewat.');
    }

    private function kueriUntukSkorSemua(): int
    {
        SmkpKategori::lupakanIngatan();

        DB::flushQueryLog();
        DB::enableQueryLog();

        Pjp::withoutGlobalScopes()->denganSkor()->get()
            ->each(fn (Pjp $p) => $p->achievement());

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    /* ═══════════ pembantu ═══════════ */

    private function laporan(Pjp $pjp, ?int $hari = null, ?string $sesuai = null, ?Carbon $saat = null): Laporan
    {
        $saat ??= Carbon::parse('2026-09-01 00:00:00', 'UTC')->addDays(($hari ?? 1) - 1);

        $l = Laporan::create([
            'pjp_id' => $pjp->id, 'jenis' => 'laporan_bulanan',
            'file_path' => 'pjp/x.pdf', 'file_name' => 'x.pdf', 'file_size' => 10,
            'kesesuaian_isi' => $sesuai,
        ]);

        /* created_at ditulis lewat kueri, bukan lewat isian: kolom
           stempel waktu diisi Eloquent sesudah penyimpanan dan menimpa
           apa pun yang diberikan. */
        Laporan::withoutGlobalScopes()->where('id', $l->id)->update(['created_at' => $saat]);

        return $l->fresh();
    }
}
