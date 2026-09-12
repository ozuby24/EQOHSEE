<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{Absensi, AbsensiJejak, MesinAbsensi, Roster};
use App\Support\Hr\Fatigue;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Akar modul HRIS — satu layar untuk satu pertanyaan.
 *
 * PERTANYAANNYA: siapa yang seharusnya di site hari ini, apakah
 * mereka benar-benar ada, dan apa yang menghalangi. Ketiganya tinggal
 * di tiga tabel yang berbeda — roster, absensi, dan berkas kelayakan
 * — dan sebelum layar ini yang menanyakannya harus membuka tiga layar
 * lalu mencocokkan angkanya sendiri di kepala.
 *
 * YANG DITAMPILKAN ADALAH SELISIHNYA, bukan jumlahnya. "Dua ratus
 * orang hadir" tidak menuntut tindakan apa pun; "empat hari kerja
 * terjadwal yang orangnya tidak datang" menuntutnya pagi itu juga.
 * Karena itu tiap angka di bawah dipasangkan dengan apa yang
 * seharusnya.
 *
 * MESIN YANG BERHENTI MENGIRIM IKUT DIHITUNG, dan itu bukan
 * kelengkapan. Alat pos jaga yang mati menghasilkan layar absensi
 * yang tampak baik-baik saja — seluruh regunya tercatat absen, dan
 * angka itu terbaca sebagai mangkir massal, bukan sebagai alat rusak.
 * Satu-satunya tanda bahwa yang rusak adalah alatnya justru ada di
 * sini: alat yang terakhir menyapa kemarin.
 */
class HrisController extends Controller
{
    /** Sebuah mesin dianggap diam bila tak menyapa selama ini. */
    public const DIAM_JAM = 12;

    /** Jendela pemeriksaan fatigue ke depan, dalam hari. */
    public const JENDELA_FATIGUE = 14;

    public function index(Request $r)
    {
        $hari    = Waktu::kini()->startOfDay();
        $kemarin = $hari->copy()->subDay();

        return Inertia::render('Hris/Ringkasan', [
            'judul'    => 'HRIS — Ringkasan Tenaga Kerja',
            'subjudul' => 'Siapa yang seharusnya di site hari ini, dan apakah mereka benar-benar ada.',

            'tanggal' => $hari->toDateString(),

            'jadwal'   => $this->jadwal($hari),
            'hadir'    => $this->kehadiran($hari),

            /* Kemarin ikut ditampilkan, dan bukan sebagai pelengkap:
               hari ini shiftnya belum selesai, sehingga angkanya belum
               dapat dibandingkan dengan apa pun. Yang lengkap adalah
               kemarin. */
            'kemarin'  => $this->kehadiran($kemarin) + ['tanggal' => $kemarin->toDateString()],

            'fatigue'  => $this->fatigue($hari),
            'mesin'    => $this->mesin(),

            'KEADAAN'  => Absensi::KEADAAN,
            'SHIFT'    => Roster::SHIFT,
            'JENDELA'  => self::JENDELA_FATIGUE,
            'DIAM_JAM' => self::DIAM_JAM,
        ]);
    }

    /**
     * Apa yang dijadwalkan hari ini.
     *
     * @return array<string,int>
     */
    private function jadwal(Carbon $hari): array
    {
        $baris = Roster::query()
            ->antara($hari->toDateString(), $hari->toDateString())
            ->get(['keadaan', 'shift', 'halangan']);

        $kerja = $baris->where('keadaan', 'kerja');

        return [
            'kerja'     => $kerja->count(),
            'siang'     => $kerja->where('shift', 'siang')->count(),
            'malam'     => $kerja->where('shift', 'malam')->count(),
            'libur'     => $baris->where('keadaan', 'libur')->count(),

            /* Cuti, sakit, dan izin DILEBUR menjadi satu angka di sini.
               Dipisah tiga, tiga angka kecil berebut tempat dengan yang
               benar-benar perlu dilihat — dan ketiganya sama-sama
               berarti "tidak dijadwalkan dan memang begitu". */
            'berhalangan' => $baris->whereIn('keadaan', ['cuti', 'sakit', 'izin'])->count(),

            'terhalang' => $kerja->whereNotNull('halangan')->count(),
        ];
    }

    /**
     * Kehadiran sebuah tanggal, dipasangkan dengan jadwalnya.
     *
     * @return array<string,mixed>
     */
    private function kehadiran(Carbon $tanggal): array
    {
        $t = $tanggal->toDateString();

        $baris = Absensi::query()->antara($t, $t)->get(['keadaan', 'jam', 'telat_menit', 'dalam_area']);

        $n = fn (string $k) => $baris->where('keadaan', $k)->count();

        return [
            'hadir'        => $n('hadir'),
            'terlambat'    => $n('terlambat'),
            'belum_pulang' => $n('belum_pulang'),
            'absen'        => $n('absen'),
            'luar_roster'  => $n('luar_roster'),
            'luar_area'    => $baris->where('dalam_area', false)->count(),
            'jam'          => round((float) $baris->sum('jam'), 1),
            'telat'        => (int) $baris->sum('telat_menit'),
            'dicatat'      => $baris->count(),
        ];
    }

    /**
     * Pelanggaran batas jam kerja pada roster yang sudah tersusun.
     *
     * DIPERIKSA PER ORANG, bukan per regu. Batas fatigue melekat pada
     * orangnya: seorang yang dipinjamkan ke regu lain di tengah periode
     * kerjanya dapat melampaui empat belas hari berturut-turut meski
     * tidak satu pun regunya melanggar sendirian.
     *
     * Jendelanya dilebarkan ke BELAKANG pula. Rangkaian kerja yang
     * bersambung dari bulan lalu hanya terlihat dari sana — diperiksa
     * dari hari ini saja, periode yang sudah berjalan sepuluh hari
     * tampak baru dimulai pagi ini.
     *
     * @return array<string,mixed>
     */
    private function fatigue(Carbon $hari): array
    {
        $dari   = $hari->copy()->subDays(Fatigue::MAKS_HARI_BERUNTUN + Fatigue::MIN_HARI_ISTIRAHAT);
        $sampai = $hari->copy()->addDays(self::JENDELA_FATIGUE);

        $baris = Roster::query()
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->with('pekerja:id,nama')
            ->orderBy('tanggal')
            ->get();

        $menolak = [];

        foreach ($baris->groupBy('pekerja_id') as $milik) {
            foreach (Fatigue::periksa($milik->values()->all()) as $t) {
                if (! $t['menolak']) continue;

                $menolak[] = [
                    'pekerja' => $milik->first()->pekerja?->nama,
                    'jenis'   => $t['jenis'],
                    'label'   => $t['label'],
                    'tanggal' => $t['tanggal'],
                ];
            }
        }

        /* Diurut menurut tanggal supaya yang paling dekat terbaca lebih
           dahulu, lalu dipotong. Ditampilkan seluruhnya, satu pola yang
           salah menghasilkan ratusan baris yang isinya sama dan
           menenggelamkan segalanya — cacat yang persis begitu pernah
           mengubur kalender roster. */
        usort($menolak, fn ($a, $b) => strcmp((string) $a['tanggal'], (string) $b['tanggal']));

        return [
            'jumlah' => count($menolak),
            'daftar' => array_slice($menolak, 0, 8),
        ];
    }

    /**
     * Kesehatan alat pindai di lapangan.
     *
     * @return array<string,mixed>
     */
    private function mesin(): array
    {
        $mesin = MesinAbsensi::query()->aktif()->get(['id', 'nama', 'terakhir_hubung', 'token_hash']);

        $ambang = Waktu::kiniSimpan()->subHours(self::DIAM_JAM);

        $diam = $mesin->filter(
            fn (MesinAbsensi $m) => $m->terakhir_hubung === null || $m->terakhir_hubung->lt($ambang),
        );

        return [
            'aktif'    => $mesin->count(),
            'bertoken' => $mesin->whereNotNull('token_hash')->count(),
            'diam'     => $diam->count(),

            'nama' => $diam->take(5)->map(fn (MesinAbsensi $m) => [
                'nama'     => $m->nama,
                'terakhir' => $m->terakhir_hubung
                    ? Waktu::lokal($m->terakhir_hubung)?->format('d/m H:i')
                    : null,
            ])->values(),

            /* Peristiwa sehari terakhir — tanda hidup yang paling
               langsung. Nol pada site yang sedang bekerja berarti
               seluruh jalur pengirimannya putus, bukan tidak ada yang
               masuk kerja. */
            'jejak24' => AbsensiJejak::query()
                ->where('terjadi', '>=', Waktu::kiniSimpan()->subDay()->toDateTimeString())
                ->count(),
        ];
    }
}
