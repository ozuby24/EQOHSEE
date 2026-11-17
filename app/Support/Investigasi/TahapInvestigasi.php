<?php

namespace App\Support\Investigasi;

use App\Models\Investigasi\Investigasi;

/**
 * Tahap investigasi, dan syarat berpindah antar tahap.
 *
 * ── MENGAPA TAHAPNYA PUNYA SYARAT ──
 *
 * Investigasi kecelakaan tambang gampang berhenti di tengah: insidennya
 * dicatat, lalu berkasnya menganggur berbulan-bulan karena tidak ada
 * yang tahu langkah berikutnya apa. Rel tahap yang hanya menggambar enam
 * bulatan tidak menolong siapa pun — yang menolong adalah tahu apa yang
 * KURANG sekarang, dan tombol untuk mengisinya.
 *
 * Karena itu tiap tahap punya daftar syarat yang diperiksa dari data
 * sungguhan. Selama syaratnya belum lengkap, tombol maju tidak muncul,
 * dan yang muncul justru daftar apa yang masih kosong.
 *
 * ── MENGAPA PANJANG JALURNYA TIDAK SAMA ──
 *
 * Enam tahap dengan syarat penuh masuk akal untuk kecelakaan fatal.
 * Untuk pekerja yang terpeleset di tangga dan lecet siku, jalur yang
 * sama menjadi enam layar, satu analisis SCAT, dan satu putaran
 * verifikasi terpisah — untuk perkara yang di lapangan selesai dalam
 * satu pagi. Yang terjadi kemudian bukan investigasi yang lebih baik,
 * melainkan berkas L1 yang tidak pernah ditutup sama sekali.
 *
 * Maka jalurnya mengikuti level triase:
 *
 *   L1  4 tahap  — analisis dan verifikasi terpisah dilewati
 *   L2  5 tahap  — analisis masuk, sebab SCAT memang wajib di L2
 *   L3  6 tahap  — lengkap
 *   L4  6 tahap  — lengkap
 *
 * Yang dilewati adalah TAHAPNYA, bukan isinya: akar masalah tetap wajib
 * di L1, hanya saja diminta pada tahap Rencana Aksi alih-alih lewat
 * layar analisis tersendiri. Tidak satu pun syarat penutupan hilang.
 *
 * ── SYARATNYA SENGAJA LONGGAR ──
 *
 * Yang diminta cuma "ada isinya", bukan "isinya bagus". Menilai mutu
 * analisa adalah pekerjaan KTT dan Inspektur Tambang, bukan pekerjaan
 * pemeriksaan otomatis. Yang dicegah aplikasi hanyalah investigasi yang
 * ditutup dengan bagian yang benar-benar kosong — itu dapat dinilai
 * mesin, dan itulah yang paling sering terjadi.
 */
final class TahapInvestigasi
{
    /** Urutan penuh. Kuncinya tersimpan di inv_investigasi.tahap. */
    public const URUTAN = [
        'perencanaan'  => 'Perencanaan',
        'pengumpulan'  => 'Pengumpulan Data',
        'analisis'     => 'Analisis',
        'rencana_aksi' => 'Rencana Aksi',
        'verifikasi'   => 'Verifikasi',
        'penutupan'    => 'Penutupan',
    ];

    /**
     * Tahap yang dilalui tiap level.
     *
     * Selalu HIMPUNAN BAGIAN dari URUTAN dengan urutan yang sama,
     * sehingga nilai kolom tahap tidak pernah keluar dari daftar yang
     * dikenali — jalur pendek tidak memperkenalkan nama tahap baru.
     */
    public const JALUR = [
        'L1' => ['perencanaan', 'pengumpulan', 'rencana_aksi', 'penutupan'],
        'L2' => ['perencanaan', 'pengumpulan', 'analisis', 'rencana_aksi', 'penutupan'],
        'L3' => ['perencanaan', 'pengumpulan', 'analisis', 'rencana_aksi', 'verifikasi', 'penutupan'],
        'L4' => ['perencanaan', 'pengumpulan', 'analisis', 'rencana_aksi', 'verifikasi', 'penutupan'],
    ];

    public const TUGAS = [
        'perencanaan'  => 'Tetapkan ketua, tim, tujuan, dan target selesai.',
        'pengumpulan'  => 'Kumpulkan bukti, wawancarai saksi, susun kronologi.',
        'analisis'     => 'Tentukan penyebab lewat SCAT, lalu rumuskan akar masalah.',
        'rencana_aksi' => 'Rumuskan temuan dan tindakan perbaikan beserta PIC dan tenggatnya.',
        'verifikasi'   => 'Selesaikan tindakan, verifikasi pelaksanaannya, nilai efektivitasnya.',
        'penutupan'    => 'Tutup investigasi dan terbitkan pembelajaran.',
    ];

    /** Tugas pengganti pada jalur pendek, sebab tahapnya menampung lebih. */
    public const TUGAS_RINGKAS = [
        'rencana_aksi' => 'Rumuskan akar masalah, temuan, dan tindakan perbaikan beserta PIC dan tenggatnya.',
    ];

    /**
     * Metode wajib yang punya kamus taksonomi.
     *
     * Triase::METODE menyebut tujuh nama, tetapi hanya empat di antaranya
     * punya kamus: scat, icam, tripod, hfacs. Sisanya — 5why, kronologi,
     * penghalang — adalah cara bekerja, bukan daftar penyebab, dan
     * hasilnya masuk sebagai kalimat akar masalah alih-alih sebagai kode.
     * Yang dapat diperiksa mesin hanya keempat yang pertama, dan hanya
     * itu yang dijadikan syarat.
     */
    public const METODE_BERKAMUS = ['scat', 'icam', 'tripod', 'hfacs'];

    /**
     * Jalur tahap bagi sebuah level.
     *
     * Level yang tidak dikenali — termasuk insiden yang belum ditriase —
     * memakai jalur penuh. Melewatkan tahap hanya boleh terjadi kalau
     * levelnya memang sudah diketahui.
     */
    public static function jalur(?string $level = null): array
    {
        return self::JALUR[$level] ?? array_keys(self::URUTAN);
    }

    public static function dilalui(string $tahap, ?string $level = null): bool
    {
        return in_array($tahap, self::jalur($level), true);
    }

    /** Posisi tahap pada urutan PENUH — dipakai membandingkan. */
    public static function indeks(string $tahap): int
    {
        $i = array_search($tahap, array_keys(self::URUTAN), true);

        return $i === false ? 0 : $i;
    }

    /**
     * Tahap berikutnya pada jalur level ini.
     *
     * Dicari berdasarkan posisi pada urutan PENUH, bukan posisi pada
     * jalur. Bedanya terasa ketika sebuah insiden diturunkan levelnya
     * sesudah investigasinya berjalan: berkas L3 yang sedang di tahap
     * Verifikasi lalu ditriase ulang menjadi L1 berada pada tahap yang
     * tidak ada di jalurnya sendiri. Dengan cara ini berkas itu tetap
     * punya tahap berikutnya (Penutupan) dan tidak tersangkut selamanya.
     */
    public static function berikutnya(string $tahap, ?string $level = null): ?string
    {
        $kini = self::indeks($tahap);

        foreach (self::jalur($level) as $k) {
            if (self::indeks($k) > $kini) return $k;
        }

        return null;
    }

    public static function sebelumnya(string $tahap, ?string $level = null): ?string
    {
        $kini  = self::indeks($tahap);
        $hasil = null;

        foreach (self::jalur($level) as $k) {
            if (self::indeks($k) < $kini) $hasil = $k;
        }

        return $hasil;
    }

    public static function metodeBerkamus(?string $level): array
    {
        return array_values(array_intersect(Triase::metode($level), self::METODE_BERKAMUS));
    }

    /** Tugas tahap, sudah memperhitungkan jalur pendek. */
    public static function tugas(string $tahap, ?string $level = null): string
    {
        $ringkas = ! self::dilalui('analisis', $level);

        if ($ringkas && isset(self::TUGAS_RINGKAS[$tahap])) {
            return self::TUGAS_RINGKAS[$tahap];
        }

        return self::TUGAS[$tahap] ?? '';
    }

    /**
     * Syarat yang belum terpenuhi untuk MENINGGALKAN tahap sekarang.
     *
     * @return list<string> kosong berarti boleh maju
     */
    public static function yangKurang(Investigasi $inv): array
    {
        $level  = $inv->level();
        $jalur  = self::jalur($level);
        $kurang = [];

        /* Tahap yang tidak ada di jalur level ini tidak menahan apa pun:
           yang perlu dikerjakan sudah dititipkan ke tahap lain. */
        if (! in_array($inv->tahap, $jalur, true)) return [];

        $adaTahapAnalisis = in_array('analisis', $jalur, true);

        switch ($inv->tahap) {
            case 'perencanaan':
                if (! $inv->ketua_id)                  $kurang[] = 'Ketua investigasi belum ditetapkan.';
                if (! $inv->target_selesai)            $kurang[] = 'Target selesai belum diisi.';
                if (! trim((string) $inv->tujuan))     $kurang[] = 'Tujuan investigasi belum ditulis.';

                /* Tim wajib mulai L2. Insiden ringan diselidiki pengawas
                   yang bersangkutan sendiri, dan memaksanya menambah
                   anggota tim hanya menghasilkan nama asal-asalan. */
                if ($adaTahapAnalisis && $inv->tim()->count() < 1) {
                    $kurang[] = 'Tim investigasi belum punya anggota.';
                }
                break;

            case 'pengumpulan':
                if ($inv->bukti()->count() < 1) {
                    $kurang[] = 'Belum ada satu pun bukti dikumpulkan.';
                }

                /* Kronologi berbutir wajib mulai L2. Di L1, uraian
                   kejadian pada formulir laporan sudah menjadi kronologi
                   yang dibaca orang, dan menyalinnya ulang menjadi dua
                   baris tidak menambah apa pun ke berkasnya. */
                if ($adaTahapAnalisis && $inv->kronologi()->count() < 2) {
                    $kurang[] = 'Kronologi perlu sedikitnya dua peristiwa supaya urutannya terbaca.';
                }
                break;

            case 'analisis':
                if ($inv->akar()->count() < 1) {
                    $kurang[] = 'Akar masalah belum dirumuskan.';
                }

                /* Yang diminta: analisis penyebab yang bertaut ke kamus —
                   lewat pilihan SCAT, atau lewat akar masalah yang sudah
                   dikaitkan ke butir taksonomi mana pun.

                   Sengaja TIDAK diperketat menjadi "harus memakai metode
                   wajib levelnya". Itu memang lebih tepat, tetapi berarti
                   berkas L3 dan L4 tertahan menunggu SCAT yang bukan
                   metodenya — dan satu-satunya jalan keluar adalah
                   mengisi analisis yang tidak diminta siapa pun.
                   Kecocokan metode dengan level tetap diperiksa, tetapi
                   sebagai catatan di layar, bukan sebagai palang. */
                $scat = $inv->analisis()->where('metode', 'scat')->first();
                $adaScat = $scat && $scat->pilihan()->count() > 0;
                $adaAkarBerkamus = $inv->akar()->whereNotNull('taksonomi_id')->count() > 0;

                if (! $adaScat && ! $adaAkarBerkamus) {
                    $berkamus = self::metodeBerkamus($level);

                    $kurang[] = 'Penyebab belum dianalisis: belum ada pilihan SCAT maupun akar masalah '
                        .'yang dikaitkan ke kamus penyebab'
                        .($berkamus ? ' ('.strtoupper(implode('/', $berkamus)).')' : '').'.';
                }
                break;

            case 'rencana_aksi':
                /* Pada jalur pendek tidak ada tahap Analisis, jadi akar
                   masalah diminta di sini. Syaratnya tidak hilang, hanya
                   pindah tempat — berkas L1 tetap tidak dapat ditutup
                   tanpa akar masalah. */
                if (! $adaTahapAnalisis && $inv->akar()->count() < 1) {
                    $kurang[] = 'Akar masalah belum dirumuskan (hasil 5 Why).';
                }

                if ($inv->temuan()->count() < 1) {
                    $kurang[] = 'Belum ada temuan yang dirumuskan.';
                }

                $tanpaTindakan = $inv->temuan()->doesntHave('tindakan')->count();

                if ($tanpaTindakan > 0) {
                    $kurang[] = $tanpaTindakan.' temuan belum punya tindakan perbaikan.';
                }
                break;

            case 'verifikasi':
                $belum = $inv->tindakanSemua()
                    ->whereNotIn('inv_tindakan.status', ['diverifikasi', 'ditutup'])->count();

                if ($belum > 0) $kurang[] = $belum.' tindakan perbaikan belum diverifikasi.';
                break;

            case 'penutupan':
                // Tahap terakhir: tidak ada tahap sesudahnya.
                break;
        }

        return $kurang;
    }

    public static function bolehMaju(Investigasi $inv): bool
    {
        return $inv->berjalan()
            && self::berikutnya($inv->tahap, $inv->level()) !== null
            && ! self::yangKurang($inv);
    }

    /**
     * Syarat menutup investigasi.
     *
     * Lebih ketat daripada berpindah tahap: menutup berarti menyatakan
     * kepada KTT dan Inspektur Tambang bahwa perkaranya selesai.
     *
     * Isinya SAMA untuk semua level — bukti, akar masalah, temuan, dan
     * tindakan yang tidak menggantung. Yang berbeda hanya seberapa jauh
     * tindakan perbaikan harus sudah berjalan: L3 dan L4 punya tahap
     * Verifikasi tersendiri sehingga seluruh tindakannya wajib sudah
     * diverifikasi; L1 dan L2 cukup sampai dinyatakan selesai oleh
     * pelaksananya, sebab verifikasi terpisah untuk perkara ringan hanya
     * menghasilkan berkas yang tidak pernah ditutup.
     *
     * @return list<string> kosong berarti boleh ditutup
     */
    public static function yangKurangUntukTutup(Investigasi $inv): array
    {
        $level  = $inv->level();
        $kurang = [];

        if ($inv->tahap !== 'penutupan')  $kurang[] = 'Investigasi belum sampai tahap Penutupan.';
        if ($inv->bukti()->count() < 1)   $kurang[] = 'Tidak ada bukti yang dikumpulkan.';
        if ($inv->akar()->count() < 1)    $kurang[] = 'Akar masalah belum dirumuskan.';
        if ($inv->temuan()->count() < 1)  $kurang[] = 'Tidak ada temuan.';

        if (self::dilalui('verifikasi', $level)) {
            $belum = $inv->tindakanSemua()
                ->whereNotIn('inv_tindakan.status', ['diverifikasi', 'ditutup'])->count();

            if ($belum > 0) $kurang[] = $belum.' tindakan perbaikan belum diverifikasi.';
        } else {
            $belum = $inv->tindakanSemua()
                ->whereNotIn('inv_tindakan.status', ['selesai', 'diverifikasi', 'ditutup'])->count();

            if ($belum > 0) $kurang[] = $belum.' tindakan perbaikan belum dinyatakan selesai.';
        }

        return $kurang;
    }

    /**
     * Rel tahap siap gambar: tiap tahap beserta keadaannya.
     *
     * @return list<array<string,mixed>>
     */
    public static function rel(Investigasi $inv): array
    {
        $level = $inv->level();
        $kini  = self::indeks($inv->tahap);

        return array_map(function (string $k) use ($inv, $kini, $level) {
            $i = self::indeks($k);

            return [
                'kunci'   => $k,
                'label'   => self::URUTAN[$k],
                'tugas'   => self::tugas($k, $level),
                'lewat'   => $i < $kini,
                'kini'    => $k === $inv->tahap,
                'nanti'   => $i > $kini,
            ];
        }, self::jalur($level));
    }
}
