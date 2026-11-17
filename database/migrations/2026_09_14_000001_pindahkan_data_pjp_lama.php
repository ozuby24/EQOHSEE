<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memindahkan isi modul PJP lama ke skema yang baru, lalu melepas
 * tabel lamanya.
 *
 * Modul PJP sempat ada dua kali dengan skema berbeda — `pjp_perusahaan`
 * dan kerabatnya, lalu `pjps` dan kerabatnya. Keduanya menghitung hal
 * yang sama dari daftar periksa prakualifikasi yang sama persis (17
 * kategori, 126 butir, bobot A–P 178), hanya berbeda penamaan tabel.
 *
 * Yang dijaga migrasi ini satu hal: DATA YANG SUDAH ADA DI PRODUKSI
 * TIDAK BOLEH HILANG. Tabel lama itu sudah dipakai — di dalamnya ada
 * perusahaan jasa yang terdaftar, dokumen yang sudah diunggah beserta
 * penilaian kesesuaiannya, evaluasi kinerja per semester, dan jawaban
 * daftar periksa yang diisi satu per satu dari 126 pertanyaan.
 * Menghapus tabelnya begitu saja berarti membuang seluruhnya, dan
 * kehilangan itu tidak menimbulkan galat apa pun — halamannya hanya
 * kembali kosong seolah belum pernah ada yang mengisinya.
 *
 * Jawaban daftar periksa adalah bagian yang paling mudah rusak diam-diam.
 * Ia menunjuk butir lewat `item_id`, dan nomor id itu TIDAK sama antara
 * kedua tabel butir: keduanya diisi terpisah, jadi urutan nomornya
 * kebetulan saja. Memindahkannya apa adanya akan memasangkan jawaban ke
 * pertanyaan yang salah — skornya tetap keluar sebagai angka yang masuk
 * akal, dan tidak ada satu pun tanda bahwa artinya sudah tertukar.
 *
 * Karena itu butirnya dipasangkan lewat (kode kategori, urutan), bukan
 * lewat id maupun teks pertanyaannya. Kode dan urutan adalah penanda
 * yang memang sama pada kedua sisi; teks pertanyaan tidak dipakai karena
 * sembilan di antaranya memuat baris baru, dan perbandingan teks panjang
 * lintas SQLite/MySQL/PostgreSQL berbeda perlakuannya terhadap spasi.
 */
return new class extends Migration
{
    /** Tabel lama, berurut aman untuk dilepas (anak lebih dulu). */
    private const TABEL_LAMA = [
        'pjp_smkp_jawaban',
        'pjp_smkp_item',
        'pjp_smkp_kategori',
        'pjp_evaluasi',
        'pjp_laporan',
        'pjp_perusahaan',
    ];

    public function up(): void
    {
        // Pemasangan baru tidak pernah punya tabel lamanya.
        if (!Schema::hasTable('pjp_perusahaan')) return;

        DB::transaction(function () {
            $petaPjp  = $this->pindahkanPerusahaan();
            $petaButir = $this->petaButir();

            $this->pindahkanLaporan($petaPjp);
            $this->pindahkanEvaluasi($petaPjp);
            $this->pindahkanJawaban($petaPjp, $petaButir);
        });

        Schema::disableForeignKeyConstraints();
        foreach (self::TABEL_LAMA as $tabel) Schema::dropIfExists($tabel);
        Schema::enableForeignKeyConstraints();
    }

    /** @return array<int,int> id lama → id baru */
    private function pindahkanPerusahaan(): array
    {
        $peta = [];

        foreach (DB::table('pjp_perusahaan')->orderBy('id')->cursor() as $baris) {
            $peta[$baris->id] = DB::table('pjps')->insertGetId([
                'company_id'       => $baris->company_id,
                'nama_perusahaan'  => $baris->nama_perusahaan,
                'nib'              => $baris->nib,
                'penanggung_jawab' => $baris->penanggung_jawab,
                'alamat'           => $baris->alamat,
                'status'           => $baris->status,
                'catatan'          => $baris->catatan,
                'created_at'       => $baris->created_at,
                'updated_at'       => $baris->updated_at,
            ]);
        }

        return $peta;
    }

    /**
     * Peta id butir lama → id butir baru, dipasangkan lewat
     * (kode kategori, urutan).
     *
     * @return array<int,int>
     */
    private function petaButir(): array
    {
        if (!Schema::hasTable('pjp_smkp_item')) return [];

        $baru = [];
        foreach (
            DB::table('smkp_checklist_items as i')
                ->join('smkp_checklist_categories as k', 'k.id', '=', 'i.smkp_checklist_category_id')
                ->get(['i.id', 'k.kode', 'i.urutan']) as $b
        ) {
            $baru[$b->kode.'#'.$b->urutan] = $b->id;
        }

        $peta = [];
        foreach (
            DB::table('pjp_smkp_item as i')
                ->join('pjp_smkp_kategori as k', 'k.id', '=', 'i.kategori_id')
                ->get(['i.id', 'k.kode', 'i.urutan']) as $l
        ) {
            $kunci = $l->kode.'#'.$l->urutan;

            if (isset($baru[$kunci])) $peta[$l->id] = $baru[$kunci];
        }

        return $peta;
    }

    private function pindahkanLaporan(array $petaPjp): void
    {
        if (!Schema::hasTable('pjp_laporan')) return;

        foreach (DB::table('pjp_laporan')->orderBy('id')->cursor() as $b) {
            if (!isset($petaPjp[$b->pjp_id])) continue;

            DB::table('pjp_laporans')->insert([
                'pjp_id'         => $petaPjp[$b->pjp_id],
                'jenis'          => $b->jenis,
                'periode'        => $b->periode,

                /* Jalur berkasnya disalin apa adanya. Berkasnya sendiri
                   tidak ikut dipindah: ia tetap di disk pada tempat yang
                   sama, dan jalur yang diubah justru membuat dokumen yang
                   masih ada menjadi tidak dapat dibuka. */
                'file_path'      => $b->file_path,
                'file_name'      => $b->file_name,
                'file_size'      => $b->file_size,
                'catatan'        => $b->catatan,
                'kesesuaian_isi' => $b->kesesuaian_isi,

                /* created_at dibawa apa adanya, dan itu bukan kerapian:
                   ketepatan waktu dihitung DARI kolom ini, bukan dari
                   kolom tersimpan. Menggantinya dengan waktu migrasi
                   membuat seluruh dokumen lama mendadak dinilai menurut
                   tanggal hari ini. */
                'created_at'     => $b->created_at,
                'updated_at'     => $b->updated_at,
            ]);
        }
    }

    private function pindahkanEvaluasi(array $petaPjp): void
    {
        if (!Schema::hasTable('pjp_evaluasi')) return;

        foreach (DB::table('pjp_evaluasi')->orderBy('id')->cursor() as $b) {
            if (!isset($petaPjp[$b->pjp_id])) continue;

            DB::table('pjp_evaluasis')->insert([
                'pjp_id'                     => $petaPjp[$b->pjp_id],
                'tahun'                      => $b->tahun,
                'semester'                   => $b->semester,
                'skor_teknis'                => $b->skor_teknis,
                'skor_keselamatan_kesehatan' => $b->skor_keselamatan_kesehatan,
                'skor_lingkungan'            => $b->skor_lingkungan,
                'catatan'                    => $b->catatan,
                'created_at'                 => $b->created_at,
                'updated_at'                 => $b->updated_at,
            ]);
        }
    }

    private function pindahkanJawaban(array $petaPjp, array $petaButir): void
    {
        if (!Schema::hasTable('pjp_smkp_jawaban')) return;

        foreach (DB::table('pjp_smkp_jawaban')->orderBy('id')->cursor() as $b) {
            /* Jawaban yang butirnya tidak dapat dipasangkan sengaja
               DILEWATI, bukan dipasang ke butir sembarang. Jawaban yang
               menempel pada pertanyaan yang salah lebih buruk daripada
               jawaban yang hilang: yang hilang terlihat sebagai kolom
               kosong yang menunggu diisi, sedangkan yang tertukar
               terbaca sebagai penilaian yang sudah dilakukan. */
            if (!isset($petaPjp[$b->pjp_id]) || !isset($petaButir[$b->item_id])) continue;

            DB::table('smkp_checklist_answers')->insert([
                'pjp_id'                 => $petaPjp[$b->pjp_id],
                'smkp_checklist_item_id' => $petaButir[$b->item_id],
                'jawaban'                => $b->jawaban,
                'nilai'                  => $b->nilai,
                'penjelasan'             => $b->penjelasan,
                'created_at'             => $b->created_at,
                'updated_at'             => $b->updated_at,
            ]);
        }
    }

    /**
     * Tidak dapat dikembalikan.
     *
     * Tabel lamanya sudah dilepas beserta bentuknya, dan menuliskan
     * `down()` yang membuat ulang tabel kosong hanya akan terlihat
     * seperti pemulihan padahal isinya tidak kembali. Yang memulihkan
     * keadaan sebelum migrasi ini adalah cadangan basis data, dan
     * pernyataan tegas di sini lebih jujur daripada rollback yang
     * berhasil tanpa memulihkan apa pun.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Pemindahan data PJP tidak dapat dibatalkan lewat rollback. '.
            'Pulihkan dari cadangan basis data sebelum migrasi ini dijalankan.'
        );
    }
};
