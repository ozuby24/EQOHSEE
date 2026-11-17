<?php

namespace App\Support\Hr;

use App\Models\Hr\{Absensi, Lembur, Roster, Upah};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Support\Carbon;

/**
 * Mengusulkan, menyetujui, dan menolak lembur.
 *
 * LEMBUR DIUSULKAN DARI JAM YANG BENAR-BENAR TERCATAT, bukan diketik
 * pengawas. Absensi sudah menyimpan berapa jam seseorang ada di site
 * hari itu, dan roster sudah menyimpan berapa jam ia dijadwalkan —
 * selisihnya adalah lemburnya, dan selisih itu dapat dihitung sendiri
 * oleh sistem yang memegang keduanya.
 *
 * Diketik tangan, dua hal terjadi sekaligus dan keduanya mahal:
 * lembur yang benar-benar dikerjakan terlewat karena tidak ada yang
 * mengajukannya, dan lembur yang tidak dikerjakan terbayar karena
 * tidak ada yang dapat membantahnya.
 *
 * HARI LIBUR YANG DIKERJAKAN SELURUHNYA LEMBUR. Modul absensi sudah
 * menandainya "di luar roster"; di sinilah tanda itu berbuah — seluruh
 * jamnya masuk tabel faktor hari libur, bukan hanya kelebihan atas
 * jadwal yang memang tidak ada.
 */
final class JalurLembur
{
    /**
     * Selisih jam terkecil yang layak diusulkan.
     *
     * BUKAN ANGKA UNDANG-UNDANG melainkan ambang operasional. Jam
     * masuk dan keluar berasal dari tap kartu, dan tap yang terlambat
     * tiga menit akan melahirkan usulan lembur tiga menit pada tiap
     * orang tiap hari — ratusan baris yang tidak seorang pun bermaksud
     * mengajukannya, dan antrean persetujuan yang berhenti dibaca.
     */
    public const MIN_JAM = 0.5;

    /**
     * Usulkan lembur dari catatan absensi satu rentang.
     *
     * Tidak menimpa baris yang sudah ada: batasan unik (pekerja,
     * tanggal) menjaga satu hari hanya punya satu perintah lembur, dan
     * yang sudah ditindak tidak boleh lahir kembali sebagai usulan
     * baru tiap kali tombolnya ditekan.
     *
     * @param  list<int>  $pekerjaId
     * @return array{dibuat:int,dilewati:int,tanpa_upah:int}
     */
    public static function usulkan(array $pekerjaId, Carbon $dari, Carbon $sampai, ?int $olehUserId = null): array
    {
        $n = ['dibuat' => 0, 'dilewati' => 0, 'tanpa_upah' => 0];

        if ($pekerjaId === []) return $n;

        $absensi = Absensi::withoutGlobalScopes()
            ->whereIn('pekerja_id', $pekerjaId)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->bekerja()
            ->with(['roster', 'pekerja.jabatan'])
            ->get();

        if ($absensi->isEmpty()) return $n;

        $ada = Lembur::withoutGlobalScopes()
            ->whereIn('pekerja_id', $pekerjaId)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->get()
            ->keyBy(fn (Lembur $l) => $l->pekerja_id.'|'.$l->tanggal->toDateString());

        foreach ($absensi as $a) {
            $hari = $a->tanggal->toDateString();

            if ($ada->has($a->pekerja_id.'|'.$hari)) { $n['dilewati']++; continue; }

            $p = $a->pekerja;

            if (! $p) continue;

            /* Golongan jabatan yang dikecualikan PP 35/2021 pasal 27
               ayat (4) tidak diusulkan sama sekali. Diusulkan lalu
               ditolak satu per satu, antrean persetujuan berisi baris
               yang sudah pasti nasibnya. */
            if ($p->jabatan?->kecuali_lembur) { $n['dilewati']++; continue; }

            [$jam, $jenisHari] = self::selisih($a);

            if ($jam < self::MIN_JAM) { $n['dilewati']++; continue; }

            $upah = Upah::pada($p, $a->tanggal);

            /* TANPA UPAH TERCATAT, USULANNYA TETAP DIBUAT — bernilai
               nol dan ditandai. Dilewati diam-diam, lembur yang
               benar-benar dikerjakan hilang tanpa jejak hanya karena
               barisnya belum diisi bagian personalia, dan tidak ada
               satu pun tanda bahwa ia pernah ada. */
            if (! $upah) $n['tanpa_upah']++;

            $h = UpahLembur::hitung(
                $jam,
                $upah?->pokok ?? 0,
                $upah?->tunjangan_tetap ?? 0,
                $upah?->tunjangan_tidak_tetap ?? 0,
                $jenisHari,
                self::hariSeminggu($a->roster),
            );

            Lembur::withoutGlobalScopes()->create([
                'company_id'    => $a->company_id ?? $p->company_id,
                'pekerja_id'    => $p->id,
                'absensi_id'    => $a->id,
                'tanggal'       => $a->tanggal->copy(),
                'jenis_hari'    => $jenisHari,
                'hari_seminggu' => self::hariSeminggu($a->roster),
                'jam'           => $h['jam'],
                'upah_sebulan'  => $h['upah_sebulan'],
                'dasar_persen'  => $h['dasar_persen'],
                'upah_sejam'    => $h['upah_sejam'],
                'rincian'       => $h['rincian'],
                'nilai'         => $h['nilai'],
                'alasan'        => $jenisHari === 'libur'
                    ? 'Bekerja pada hari libur menurut roster.'
                    : 'Selisih jam tercatat terhadap jadwal shift.',
                'status'        => 'menunggu',
                'diajukan_oleh' => $olehUserId,
                'diajukan_pada' => Waktu::kiniSimpan(),
            ]);

            $n['dibuat']++;
        }

        return $n;
    }

    /**
     * Jam lembur sebuah hari, beserta jenis harinya.
     *
     * @return array{0:float,1:string}
     */
    private static function selisih(Absensi $a): array
    {
        $jam = (float) $a->jam;

        /* Hari libur yang dikerjakan: SELURUH jamnya lembur. Tidak ada
           jadwal yang dapat dikurangkan — itulah arti "di luar
           roster". */
        if ($a->keadaan === 'luar_roster') return [round($jam, 2), 'libur'];

        $jadwal = (float) ($a->roster?->jam ?? 0);

        return [round(max(0.0, $jam - $jadwal), 2), 'kerja'];
    }

    /**
     * Pola minggu yang dipakai memilih tabel faktor hari libur.
     *
     * POLA 14:7 TIDAK PUNYA PADANAN LIMA ATAU ENAM HARI SEMINGGU, dan
     * itu perlu dikatakan terang-terangan: pasal 31 ayat (2) menyusun
     * faktornya untuk pola mingguan, sedangkan roster FIFO tidak
     * mengenal hari istirahat mingguan — istirahatnya adalah periode
     * off-site.
     *
     * Yang dipakai di sini tabel enam hari, sebab itulah bawaan
     * undang-undang dan tabel yang lebih hemat bagi pemberi kerja hanya
     * pada jam ke-8 saja. Pola kantor 5:2 memakai tabel lima hari.
     * Pilihan ini boleh disunting per baris pada layarnya.
     */
    private static function hariSeminggu(?Roster $r): int
    {
        $pola = $r?->pola;

        if (! $pola) return 6;

        /* Pola bersatuan hari yang libur dua hari tiap lima hari kerja
           adalah pola lima hari seminggu — 5:2 dan turunannya. */
        return ($pola->satuan === 'hari' && $pola->kerja === 5 && $pola->libur === 2) ? 5 : 6;
    }

    /* ═══════════════════ tindakan ═══════════════════ */

    /**
     * Setujui, HITUNG ULANG dengan upah yang berlaku pada tanggalnya.
     *
     * Dihitung ulang, bukan dipakai apa adanya dari saat diusulkan: di
     * antara usulan dan persetujuan, upah orang itu dapat diperbaiki
     * bagian personalia — dan angka yang lama membayar lembur dengan
     * upah yang sudah diketahui salah.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function setujui(Lembur $l, User $oleh, ?string $catatan = null): ?string
    {
        if (! $l->menunggu()) return 'Perintah lembur ini sudah '.Lembur::STATUS[$l->status].'.';

        if (self::pengaju($l, $oleh)) {
            return 'Yang mengajukan tidak dapat menyetujui pengajuannya sendiri.';
        }

        $p = $l->pekerja;

        if ($p?->jabatan?->kecuali_lembur) {
            return 'Jabatan '.$p->jabatan->nama.' dikecualikan dari upah lembur'
                .' — PP 35/2021 pasal 27 ayat (4).';
        }

        $upah = $p ? Upah::pada($p, $l->tanggal) : null;

        $h = UpahLembur::hitung(
            (float) $l->jam,
            $upah?->pokok ?? 0,
            $upah?->tunjangan_tetap ?? 0,
            $upah?->tunjangan_tidak_tetap ?? 0,
            $l->jenis_hari,
            (int) $l->hari_seminggu,
        );

        $l->forceFill([
            'upah_sebulan'   => $h['upah_sebulan'],
            'dasar_persen'   => $h['dasar_persen'],
            'upah_sejam'     => $h['upah_sejam'],
            'rincian'        => $h['rincian'],
            'nilai'          => $h['nilai'],
            'status'         => 'disetujui',
            'ditindak_oleh'  => $oleh->id,
            'ditindak_pada'  => Waktu::kiniSimpan(),
            'catatan_tindak' => $catatan,
        ])->save();

        return null;
    }

    public static function tolak(Lembur $l, User $oleh, ?string $catatan = null): ?string
    {
        if (! $l->menunggu()) return 'Perintah lembur ini sudah '.Lembur::STATUS[$l->status].'.';

        if (self::pengaju($l, $oleh)) {
            return 'Yang mengajukan tidak dapat menolak pengajuannya sendiri.';
        }

        $l->forceFill([
            'status'         => 'ditolak',
            'nilai'          => 0,
            'ditindak_oleh'  => $oleh->id,
            'ditindak_pada'  => Waktu::kiniSimpan(),
            'catatan_tindak' => $catatan,
        ])->save();

        return null;
    }

    public static function batalkan(Lembur $l, User $oleh, ?string $catatan = null): ?string
    {
        if (! in_array($l->status, ['menunggu', 'disetujui'], true)) {
            return 'Perintah lembur ini sudah '.Lembur::STATUS[$l->status].'.';
        }

        $l->forceFill([
            'status'         => 'dibatalkan',
            'nilai'          => 0,
            'ditindak_oleh'  => $oleh->id,
            'ditindak_pada'  => Waktu::kiniSimpan(),
            'catatan_tindak' => $catatan,
        ])->save();

        return null;
    }

    /**
     * Peringatan batas jam bagi sebuah baris.
     *
     * MELAMPAUI BATAS TIDAK MEMBATALKAN UPAHNYA — jamnya sudah
     * dikerjakan, dan menolak membayarnya berarti menghukum pekerja
     * atas perintah yang bukan keputusannya. Yang ditandai adalah
     * pelanggarannya, supaya yang menyetujui melihatnya sebelum
     * menekan tombol.
     *
     * @return list<string>
     */
    public static function peringatan(Lembur $l): array
    {
        $out = [];

        if ($p = UpahLembur::melebihi((float) $l->jam, $l->jenis_hari)) $out[] = $p;

        /* Batas mingguan hanya dapat dilihat dari rangkaian, bukan dari
           satu baris. Pekannya diambil Senin–Minggu. */
        $awal  = $l->tanggal->copy()->startOfWeek();
        $akhir = $l->tanggal->copy()->endOfWeek();

        $sepekan = Lembur::withoutGlobalScopes()
            ->where('pekerja_id', $l->pekerja_id)
            ->whereIn('status', ['menunggu', 'disetujui'])
            ->antara($awal->toDateString(), $akhir->toDateString())
            ->get()
            ->map(fn (Lembur $x) => ['jam' => (float) $x->jam, 'jenis_hari' => $x->jenis_hari])
            ->all();

        if ($p = UpahLembur::melebihiMinggu($sepekan)) $out[] = $p;

        if ($l->upah_sebulan <= 0) {
            $out[] = 'Upah pekerja ini belum tercatat — nilainya nol sampai upahnya diisi.';
        }

        return $out;
    }

    private static function pengaju(Lembur $l, User $oleh): bool
    {
        if ($l->diajukan_oleh && (int) $l->diajukan_oleh === (int) $oleh->id) return true;

        return $l->pekerja?->user_id !== null
            && (int) $l->pekerja->user_id === (int) $oleh->id;
    }
}
