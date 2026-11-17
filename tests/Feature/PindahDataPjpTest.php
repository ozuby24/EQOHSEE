<?php

namespace Tests\Feature;

use App\Models\{Pjp, PjpEvaluasi, PjpLaporan, SmkpChecklistAnswer,
    SmkpChecklistCategory, SmkpChecklistItem};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pemindahan data modul PJP lama ke skema baru.
 *
 * Migrasi yang diuji di sini berjalan SEKALI di produksi, di atas data
 * yang tidak dapat dibuat ulang: perusahaan jasa yang terdaftar, dokumen
 * yang sudah diunggah beserta penilaiannya, dan jawaban daftar periksa
 * yang diisi satu per satu dari 126 pertanyaan. Kalau ia salah, tidak
 * ada kesempatan kedua — dan salahnya tidak menimbulkan galat, hanya
 * angka yang tetap keluar dengan arti yang sudah tertukar.
 *
 * Karena itu ia diuji dengan membangun ulang tabel lamanya, mengisinya,
 * lalu menjalankan migrasinya sungguhan.
 */
class PindahDataPjpTest extends TestCase
{
    use RefreshDatabase;

    /** Membangun ulang tabel lama secukupnya untuk diuji. */
    private function bangunTabelLama(): void
    {
        Schema::create('pjp_perusahaan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('nama_perusahaan');
            $t->string('nib')->nullable();
            $t->string('penanggung_jawab')->nullable();
            $t->text('alamat')->nullable();
            $t->string('status')->default('aktif');
            $t->text('catatan')->nullable();
            $t->timestamps();
        });

        Schema::create('pjp_laporan', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pjp_id');
            $t->string('jenis');
            $t->string('periode')->nullable();
            $t->string('file_path');
            $t->string('file_name');
            $t->unsignedBigInteger('file_size')->default(0);
            $t->text('catatan')->nullable();
            $t->string('kesesuaian_isi')->nullable();
            $t->timestamps();
        });

        Schema::create('pjp_evaluasi', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pjp_id');
            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('semester');
            $t->unsignedTinyInteger('skor_teknis');
            $t->unsignedTinyInteger('skor_keselamatan_kesehatan');
            $t->unsignedTinyInteger('skor_lingkungan');
            $t->text('catatan')->nullable();
            $t->timestamps();
        });

        Schema::create('pjp_smkp_kategori', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->unsignedInteger('bobot');
            $t->unsignedInteger('urutan');
            $t->timestamps();
        });

        Schema::create('pjp_smkp_item', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('kategori_id');
            $t->string('grup_kode')->nullable();
            $t->string('grup_nama')->nullable();
            $t->unsignedInteger('nomor');
            $t->text('pertanyaan');
            $t->text('petunjuk')->nullable();
            $t->unsignedInteger('bobot');
            $t->unsignedInteger('urutan');
            $t->timestamps();
        });

        Schema::create('pjp_smkp_jawaban', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('pjp_id');
            $t->unsignedBigInteger('item_id');
            $t->string('jawaban')->nullable();
            $t->string('nilai')->nullable();
            $t->text('penjelasan')->nullable();
            $t->timestamps();
        });
    }

    /**
     * Pasangan (kode kategori, urutan) yang sungguh ada di daftar periksa.
     *
     * `urutan` berjalan MENERUS melintasi kategori — LEGALITAS 1–4, A 5–8,
     * B 9–12 — bukan mulai dari satu di tiap kategori. Menebaknya sebagai
     * 1 untuk semua kategori membuat uji ini menguji keadaan yang tidak
     * pernah terjadi di produksi: di sana kedua tabel butir berasal dari
     * daftar pertanyaan yang sama, jadi urutannya pasti bersesuaian.
     *
     * Karena itu nilainya dibaca dari data acuan, bukan ditulis tetap:
     * kalau daftar periksanya kelak disusun ulang, uji ini ikut menyesuaikan
     * alih-alih gagal pada hal yang bukan pokok persoalannya.
     *
     * @return array<string,int> kode kategori → urutan butir pertamanya
     */
    private function pasanganButir(): array
    {
        $peta = [];

        foreach (['LEGALITAS', 'A', 'B'] as $kode) {
            $kategori = SmkpChecklistCategory::where('kode', $kode)->firstOrFail();

            $peta[$kode] = SmkpChecklistItem::where('smkp_checklist_category_id', $kategori->id)
                ->orderBy('urutan')->firstOrFail()->urutan;
        }

        return $peta;
    }

    /**
     * Mengisi tabel lama meniru keadaan produksi.
     *
     * Nomor id butir lamanya sengaja dibuat TIDAK sama dengan yang baru:
     * di sinilah letak kesalahan yang paling mudah terjadi dan paling
     * sulit terlihat.
     */
    private function isiDataLama(): array
    {
        $waktu = '2026-08-14 09:15:00';

        $pjpId = DB::table('pjp_perusahaan')->insertGetId([
            'nama_perusahaan'  => 'PT Mitra Lama Sejahtera',
            'nib'              => '1234567890123',
            'penanggung_jawab' => 'Ir. Bagas',
            'status'           => 'perlu_tindak_lanjut',
            'catatan'          => 'Dipindahkan dari modul lama.',
            'created_at'       => $waktu, 'updated_at' => $waktu,
        ]);

        DB::table('pjp_laporan')->insert([
            'pjp_id' => $pjpId, 'jenis' => 'laporan_bulanan', 'periode' => 'Agustus 2026',
            'file_path' => 'pjp/1/laporan.pdf', 'file_name' => 'laporan.pdf', 'file_size' => 2048,
            'kesesuaian_isi' => 'sesuai',
            'created_at' => '2026-08-02 08:00:00', 'updated_at' => $waktu,
        ]);

        DB::table('pjp_evaluasi')->insert([
            'pjp_id' => $pjpId, 'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 90, 'skor_keselamatan_kesehatan' => 80, 'skor_lingkungan' => 70,
            'created_at' => $waktu, 'updated_at' => $waktu,
        ]);

        /* Id butir lama sengaja digeser jauh dari id butir baru: kalau
           keduanya kebetulan sama, uji ini akan lulus meski pemetaannya
           tidak pernah bekerja. Baris pancingan di bawah yang menggeser
           penomorannya, lalu dibuang lagi. */
        for ($i = 0; $i < 500; $i++) {
            DB::table('pjp_smkp_item')->insert([
                'kategori_id' => 1, 'nomor' => 0, 'pertanyaan' => 'pancingan',
                'bobot' => 0, 'urutan' => 0,
            ]);
        }
        DB::table('pjp_smkp_item')->where('pertanyaan', 'pancingan')->delete();

        $petaLama = [];
        $urutanKe = 1;

        foreach ($this->pasanganButir() as $kode => $urutanButir) {
            $katId = DB::table('pjp_smkp_kategori')->insertGetId([
                'kode' => $kode, 'nama' => 'Kategori '.$kode, 'bobot' => 4, 'urutan' => $urutanKe++,
                'created_at' => $waktu, 'updated_at' => $waktu,
            ]);

            $itemId = DB::table('pjp_smkp_item')->insertGetId([
                'kategori_id' => $katId, 'nomor' => 1, 'pertanyaan' => 'Pertanyaan lama '.$kode,
                'bobot' => 1, 'urutan' => $urutanButir,
                'created_at' => $waktu, 'updated_at' => $waktu,
            ]);

            $petaLama[$kode] = $itemId;

            DB::table('pjp_smkp_jawaban')->insert([
                'pjp_id' => $pjpId, 'item_id' => $itemId,
                'jawaban' => 'ya', 'nilai' => $kode === 'LEGALITAS' ? null : '3',
                'penjelasan' => 'Jawaban lama '.$kode,
                'created_at' => $waktu, 'updated_at' => $waktu,
            ]);
        }

        return ['pjp' => $pjpId, 'butir' => $petaLama];
    }

    private function jalankanMigrasi(): void
    {
        $berkas = database_path('migrations/2026_09_14_000001_pindahkan_data_pjp_lama.php');

        (require $berkas)->up();
    }

    /* ---------- yang dijaga ---------- */

    public function test_data_perusahaan_dokumen_dan_evaluasi_ikut_terbawa(): void
    {
        $this->bangunTabelLama();
        $this->isiDataLama();

        $this->jalankanMigrasi();

        $pjp = Pjp::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('PT Mitra Lama Sejahtera', $pjp->nama_perusahaan);
        $this->assertSame('perlu_tindak_lanjut', $pjp->status);
        $this->assertSame('1234567890123', $pjp->nib);

        $laporan = PjpLaporan::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('laporan_bulanan', $laporan->jenis);
        $this->assertSame('sesuai', $laporan->kesesuaian_isi);
        $this->assertSame('pjp/1/laporan.pdf', $laporan->file_path,
            'Jalur berkas harus disalin apa adanya — berkasnya tidak ikut pindah.');

        $evaluasi = PjpEvaluasi::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(2026, $evaluasi->tahun);
        $this->assertSame(80.0, $evaluasi->skor_rata_rata);
    }

    /**
     * Ketepatan waktu dihitung DARI created_at. Menggantinya dengan waktu
     * migrasi membuat seluruh dokumen lama mendadak dinilai menurut
     * tanggal hari ini — dan dokumen yang dahulu terlambat berubah
     * menjadi tepat waktu, atau sebaliknya.
     */
    public function test_waktu_unggah_dokumen_tidak_digeser_ke_waktu_migrasi(): void
    {
        $this->bangunTabelLama();
        $this->isiDataLama();

        $this->jalankanMigrasi();

        $laporan = PjpLaporan::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('2026-08-02', $laporan->created_at->format('Y-m-d'));
        $this->assertTrue($laporan->tepat_waktu,
            'Diunggah tanggal 2, jadi harus tetap terbaca tepat waktu setelah dipindah.');
    }

    /**
     * Inti migrasi ini: jawaban harus menempel pada PERTANYAAN yang sama,
     * bukan pada nomor id yang sama. Kedua tabel butir diisi terpisah,
     * jadi nomornya kebetulan saja — dan jawaban yang tertukar tetap
     * menghasilkan skor yang terlihat sah.
     */
    public function test_jawaban_dipasangkan_ke_butir_yang_benar_bukan_ke_nomor_id(): void
    {
        $this->bangunTabelLama();
        $lama = $this->isiDataLama();

        $this->jalankanMigrasi();

        foreach ($this->pasanganButir() as $kode => $urutanButir) {
            $kategori = SmkpChecklistCategory::where('kode', $kode)->firstOrFail();
            $butir    = SmkpChecklistItem::where('smkp_checklist_category_id', $kategori->id)
                ->where('urutan', $urutanButir)->firstOrFail();

            $jawaban = SmkpChecklistAnswer::withoutGlobalScopes()
                ->where('smkp_checklist_item_id', $butir->id)->first();

            $this->assertNotNull($jawaban, "Jawaban kategori {$kode} hilang setelah dipindah.");
            $this->assertSame("Jawaban lama {$kode}", $jawaban->penjelasan,
                "Jawaban kategori {$kode} menempel pada butir yang salah.");

            /* Nomor id-nya memang berbeda — itulah sebabnya pemetaan
               lewat (kode kategori, urutan) diperlukan, dan itu pula
               yang membuat uji ini bermakna. */
            $this->assertNotSame($lama['butir'][$kode], $butir->id,
                "Id butir {$kode} kebetulan sama; uji ini jadi tidak membuktikan apa pun.");
        }
    }

    public function test_tabel_lama_dilepas_setelah_isinya_dipindah(): void
    {
        $this->bangunTabelLama();
        $this->isiDataLama();

        $this->jalankanMigrasi();

        foreach ([
            'pjp_perusahaan', 'pjp_laporan', 'pjp_evaluasi',
            'pjp_smkp_kategori', 'pjp_smkp_item', 'pjp_smkp_jawaban',
        ] as $tabel) {
            $this->assertFalse(Schema::hasTable($tabel), "Tabel lama '{$tabel}' belum dilepas.");
        }
    }

    /** Pemasangan baru tidak punya tabel lama, dan itu bukan kegagalan. */
    public function test_tanpa_tabel_lama_migrasi_tidak_melakukan_apa_apa(): void
    {
        $this->jalankanMigrasi();

        $this->assertSame(0, Pjp::withoutGlobalScopes()->count());
    }
}
