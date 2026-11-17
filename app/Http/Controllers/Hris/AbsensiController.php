<?php

namespace App\Http\Controllers\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hr\{Absensi, AbsensiJejak, MesinAbsensi, Roster};
use App\Models\Miners\{Blok, Pekerja};
use App\Support\Hr\Rekonsiliasi;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Absensi — dan rekonsiliasinya terhadap roster.
 *
 * PERTANYAANNYA BUKAN "SIAPA YANG HADIR", melainkan "siapa yang
 * seharusnya hadir tetapi tidak, dan siapa yang hadir padahal tidak
 * seharusnya". Yang pertama dijawab mesin pindai mana pun; yang kedua
 * hanya dapat dijawab bila jadwalnya tersimpan di aplikasi yang sama —
 * dan justru yang kedua yang berujung pada biaya: hari libur yang
 * dikerjakan adalah lembur yang belum diperintahkan, dan hari kerja
 * yang kosong adalah unit yang berhenti.
 */
class AbsensiController extends Controller
{
    /**
     * Urutan baca layar harian — makin mendesak makin atas.
     *
     * "Belum tap pulang" ditaruh di atas "absen" dengan sengaja: yang
     * absen sudah pasti tidak ada di site, sedangkan yang belum
     * tercatat keluar MUNGKIN MASIH DI DALAM — dan pada keadaan
     * darurat, itulah daftar yang pertama dicari.
     */
    private const URUTAN = [
        'belum_pulang' => 0,
        'absen'        => 1,
        'luar_roster'  => 2,
        'terlambat'    => 3,
        'hadir'        => 4,
    ];

    /* ═══════════════════ pemantauan harian ═══════════════════ */

    public function index(Request $r)
    {
        $tanggal = $r->query('tanggal') ? Waktu::tanggal($r->query('tanggal')) : Waktu::kini()->startOfDay();
        $hari    = $tanggal->toDateString();

        $blokId = $r->query('blok') ? (int) $r->query('blok') : null;

        $roster = Roster::query()
            ->antara($hari, $hari)
            ->with(['pekerja.jabatan', 'pekerja.blok', 'blok', 'pola'])
            ->get()
            ->keyBy('pekerja_id');

        $absen = Absensi::query()
            ->antara($hari, $hari)
            ->with(['pekerja.jabatan', 'pekerja.blok', 'blok'])
            ->get()
            ->keyBy('pekerja_id');

        /* Gabungan KEDUANYA, bukan salah satu. Diambil dari roster
           saja, orang yang menempelkan kartunya pada hari liburnya
           tidak muncul di mana pun — padahal justru itu yang perlu
           dilihat. Diambil dari absensi saja, yang tidak datang tidak
           muncul sama sekali, dan layar ketidakhadiran menjadi layar
           kosong yang terlihat seperti kabar baik. */
        $ids = $roster->keys()->merge($absen->keys())->unique()->values();

        $baris = $ids->map(function ($id) use ($roster, $absen) {
            $rs = $roster->get($id);
            $ab = $absen->get($id);
            $p  = $rs?->pekerja ?? $ab?->pekerja;

            return [
                'id'        => $ab?->id,
                'pekerja'   => $p?->nama,
                'nomor'     => $p?->no_registrasi ?: $p?->no_induk,
                'jabatan'   => $p?->jabatan?->nama,
                'blok'      => $ab?->blok?->nama ?? $rs?->blok?->nama ?? $p?->blok?->nama,
                'blok_id'   => $ab?->blok_id ?? $rs?->blok_id ?? $p?->blok_id,

                'roster'    => $rs?->keadaan,
                'shift'     => $rs?->shift,
                'jadwal'    => $rs && $rs->bekerja() ? $rs->pola?->mulaiShift($rs->shift) : null,

                'masuk'     => $ab?->masuk ? Waktu::lokal($ab->masuk)?->format('H:i') : null,
                'keluar'    => $ab?->keluar ? Waktu::lokal($ab->keluar)?->format('H:i') : null,
                'jam'       => $ab ? (float) $ab->jam : 0,
                'telat'     => $ab?->telat_menit,

                /* Hari tanpa baris absensi sama sekali TETAP disebut
                   keadaannya, tidak dibiarkan kosong. Dibiarkan kosong,
                   orang yang terjadwal kerja tetapi belum
                   direkonsiliasi tampak sama persis dengan orang yang
                   liburnya memang kosong. */
                'keadaan'   => $ab?->keadaan ?? ($rs?->bekerja() ? 'absen' : null),

                'sumber'    => $ab?->sumber,
                'luring'    => (bool) $ab?->luring,
                'area'      => $ab?->dalam_area,
                'dikoreksi' => (bool) $ab?->dikoreksi,
                'halangan'  => $rs?->halangan,
            ];
        });

        if ($blokId) $baris = $baris->where('blok_id', $blokId);

        /* YANG MENUNTUT TINDAKAN DI ATAS, bukan urut abjad.
           Diurut nama, layar pos jaga site berisi tiga ratus orang
           menaruh satu orang yang belum keluar area di baris kedua
           ratus tujuh belas — di antara ratusan baris libur yang
           seluruhnya kosong. Yang membacanya pada keadaan darurat
           tidak sedang menggulir. */
        $baris = $baris
            ->sortBy(fn (array $b) => [self::URUTAN[$b['keadaan'] ?? ''] ?? 9, (string) $b['pekerja']])
            ->values();

        return Inertia::render('Hris/Absensi/Harian', [
            'judul'    => 'Absensi — Pemantauan Harian',
            'subjudul' => 'Kehadiran yang tercatat, dibandingkan dengan yang dijadwalkan.',

            'tanggal' => $hari,
            'blok'    => Blok::pilihan(),
            'terpilihBlok' => $blokId,

            'baris'   => $baris,
            'ringkas' => $this->ringkas($baris),

            'pekerja' => Pekerja::query()->aktif()->orderBy('nama')->get(['id', 'nama', 'nik']),

            'KEADAAN'         => Absensi::KEADAAN,
            'KEADAAN_ROSTER'  => Roster::KEADAAN,
            'ARAH'            => AbsensiJejak::ARAH,
            'SHIFT'           => Roster::SHIFT,
        ]);
    }

    /* ═══════════════════ rekap ═══════════════════ */

    public function rekap(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);

        $baris = Absensi::query()
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->with(['pekerja.jabatan', 'pekerja.blok'])
            ->get()
            ->groupBy('pekerja_id')
            ->map(function ($g) {
                $p = $g->first()->pekerja;

                $hitung = fn (string $k) => $g->where('keadaan', $k)->count();

                return [
                    'pekerja'     => $p?->nama,
                    'nomor'       => $p?->no_registrasi ?: $p?->no_induk,
                    'jabatan'     => $p?->jabatan?->nama,
                    'blok'        => $p?->blok?->nama,

                    'hadir'       => $hitung('hadir'),
                    'terlambat'   => $hitung('terlambat'),
                    'belum'       => $hitung('belum_pulang'),
                    'absen'       => $hitung('absen'),
                    'luar'        => $hitung('luar_roster'),

                    /* Hari kerja dihitung dari keadaan yang BERARTI
                       bekerja, bukan dari jumlah barisnya: baris
                       "absen" ada dan tidak boleh ikut terhitung
                       sebagai hari kerja. */
                    'hariKerja'   => $g->filter(fn (Absensi $a) => $a->bekerja())->count(),

                    'jam'         => round((float) $g->sum('jam'), 2),
                    'telat'       => (int) $g->sum('telat_menit'),

                    /* Jam DI LUAR ROSTER dipisahkan, tidak dilebur ke
                       dalam jumlah jam. Dilebur, tunjangan site
                       terhitung atas hari libur yang dikerjakan — dan
                       lembur yang belum diperintahkan justru terbayar
                       diam-diam sebagai hari biasa. */
                    'jamLuar'     => round((float) $g->where('keadaan', 'luar_roster')->sum('jam'), 2),

                    'luarArea'    => $g->where('dalam_area', false)->count(),
                    'dikoreksi'   => $g->where('dikoreksi', true)->count(),
                ];
            })
            ->sortBy('pekerja')
            ->values();

        return Inertia::render('Hris/Absensi/Rekap', [
            'judul'    => 'Absensi — Rekap Periode',
            'subjudul' => 'Hari kerja, jam tercatat, dan keterlambatan per orang.',

            'rentang' => ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'baris'   => $baris,
            'KEADAAN' => Absensi::KEADAAN,
        ]);
    }

    /* ═══════════════════ mesin ═══════════════════ */

    public function mesin()
    {
        return Inertia::render('Hris/Absensi/Mesin', [
            'judul'    => 'Absensi — Mesin Lapangan',
            'subjudul' => 'Alat pindai di pos jaga, beserta tokennya.',

            'mesin' => MesinAbsensi::query()->with('blok')->withCount('jejak')->orderBy('nama')->get()
                ->map(fn (MesinAbsensi $m) => [
                    'id'         => $m->id,
                    'nama'       => $m->nama,
                    'merek'      => $m->merek,
                    'nomor_seri' => $m->nomor_seri,
                    'ip'         => $m->ip,
                    'blok'       => $m->blok?->nama,
                    'blok_id'    => $m->blok_id,
                    'aktif'      => $m->aktif,
                    'jejak'      => $m->jejak_count,

                    /* Yang dikirim ke peramban hanyalah ADA ATAU
                       TIDAKNYA token, tidak pernah hash-nya. Hash yang
                       sampai ke peramban dapat diserang tanpa batas
                       kecepatan di mesin penyerang sendiri. */
                    'bertoken'   => $m->token_hash !== null,

                    'terakhir'   => $m->terakhir_hubung
                        ? Waktu::lokal($m->terakhir_hubung)?->format('d/m/Y H:i')
                        : null,
                ]),

            'blok'      => Blok::pilihan(),
            'MEREK'     => MesinAbsensi::MEREK,
            'MAKS_BATCH'=> \App\Http\Controllers\Api\AbsensiIngestController::MAKS_BATCH,
        ]);
    }

    public function mesinSimpan(Request $r)
    {
        MesinAbsensi::create($this->validasiMesin($r));

        return back()->with('sukses', 'Mesin absensi ditambahkan. Terbitkan tokennya sebelum dipasang.');
    }

    public function mesinUbah(Request $r, MesinAbsensi $mesin)
    {
        $mesin->update($this->validasiMesin($r, $mesin));

        return back()->with('sukses', 'Mesin absensi diperbarui.');
    }

    public function mesinHapus(MesinAbsensi $mesin)
    {
        $mesin->delete();

        return back()->with('sukses', 'Mesin absensi dihapus.');
    }

    /**
     * Terbitkan token baru bagi sebuah mesin.
     *
     * TOKENNYA HANYA TAMPIL SEKALI, dan halamannya mengatakannya
     * demikian. Yang tersimpan hanya hash — tidak ada cara membacanya
     * kembali, dan memang itu maksudnya. Hilang berarti diterbitkan
     * ulang, dan alat yang dicabut dari dinding pos jaga tidak membawa
     * apa pun yang dapat dibaca dari basis data.
     */
    public function mesinToken(MesinAbsensi $mesin)
    {
        $terang = $mesin->terbitkanToken();

        return back()->with('token', [
            'mesin' => $mesin->nama,
            'seri'  => $mesin->nomor_seri,
            'nilai' => $terang,
        ])->with('sukses', 'Token baru diterbitkan. Salin sekarang — ia tidak dapat ditampilkan lagi.');
    }

    /* ═══════════════════ catat & koreksi ═══════════════════ */

    /**
     * Catat pindaian secara manual.
     *
     * DITULIS SEBAGAI JEJAK, bukan langsung ke catatan hariannya.
     * Ditulis langsung, catatan tangan pengawas tidak dapat dibedakan
     * dari pindaian alat — dan pertanyaan auditor ("dari mana angka
     * ini") kehilangan jawabannya justru pada baris yang paling
     * mungkin dipersoalkan. Sumbernya tercatat "manual", dan siapa yang
     * mencatat tersimpan pada catatannya.
     */
    public function catat(Request $r)
    {
        $data = $r->validate([
            'pekerja_id' => ['required', 'exists:mnr_pekerja,id'],
            'tanggal'    => ['required', 'date'],
            'jam'        => ['required', 'date_format:H:i'],
            'arah'       => ['required', 'in:'.implode(',', array_keys(AbsensiJejak::ARAH))],
            'catatan'    => ['nullable', 'string', 'max:200'],
        ]);

        $p = Pekerja::findOrFail($data['pekerja_id']);

        $saat = Carbon::parse($data['tanggal'].' '.$data['jam'], Waktu::zona());

        AbsensiJejak::create([
            'company_id' => $p->company_id,
            'pekerja_id' => $p->id,
            'terjadi'    => Waktu::simpan($saat),
            'diterima'   => Waktu::kiniSimpan(),
            'arah'       => $data['arah'],
            'sumber'     => 'manual',

            /* Kunci idempotennya dibuat di sini pula, sebab batasan
               uniknya berlaku bagi seluruh tabel — bukan hanya bagi
               yang datang dari mesin. Tanpa ini, pencatatan manual
               kedua melempar galat 500 pada tombol yang baru saja
               berhasil ditekan. */
            'kunci'      => 'manual-'.$p->id.'-'.$saat->format('YmdHis').'-'.Str::random(8),

            'catatan'    => trim(($data['catatan'] ?? '').' — dicatat '.($r->user()?->name ?? 'sistem')),
        ]);

        Rekonsiliasi::jalankan([$p->id], $saat->copy()->subDay(), $saat->copy(), $r->user()?->id);

        return back()->with('sukses', 'Pindaian manual dicatat dan direkonsiliasi.');
    }

    /**
     * Koreksi jam pada catatan harian.
     *
     * JEJAK ASLINYA TIDAK DISENTUH. Yang berubah hanyalah kesimpulannya,
     * dan barisnya ditandai `dikoreksi` supaya rekonsiliasi berikutnya
     * tidak menimpanya kembali dari jejak yang sama. Tanpa tanda itu,
     * koreksi yang sudah disetujui pengawas lenyap begitu satu batch
     * luring datang terlambat — tanpa satu galat pun, sebab barisnya
     * memang tertulis ulang dengan benar dari jejaknya.
     */
    public function koreksi(Request $r, Absensi $absensi)
    {
        $data = $r->validate([
            'masuk'  => ['nullable', 'date_format:H:i'],
            'keluar' => ['nullable', 'date_format:H:i'],
            'alasan' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        $hari = $absensi->tanggal->toDateString();

        $jam = fn (?string $v) => $v ? Carbon::parse($hari.' '.$v, Waktu::zona()) : null;

        $masuk  = $jam($data['masuk'] ?? null);
        $keluar = $jam($data['keluar'] ?? null);

        /* Penafsiran shift malam yang menyeberang tengah malam ada di
           Rekonsiliasi::koreksi(), bukan di sini — satu aturan, satu
           tempat. Ditafsirkan pula di sini, koreksi lewat layar cepat
           atau lambat berbeda dari koreksi lewat jalan lain. */
        $absensi->forceFill(Rekonsiliasi::koreksi($absensi, $masuk, $keluar) + [
            'dikoreksi'      => true,
            'dikoreksi_oleh' => $r->user()?->id,
            'dikoreksi_pada' => Waktu::kiniSimpan(),
            'alasan_koreksi' => $data['alasan'],
        ])->save();

        return back()->with('sukses', 'Catatan absensi dikoreksi.');
    }

    /** Jalankan ulang rekonsiliasi atas satu rentang. */
    public function rekonsiliasi(Request $r)
    {
        [$dari, $sampai] = $this->rentang($r);

        $ids = Pekerja::query()->aktif()->pluck('id')->all();

        $n = Rekonsiliasi::jalankan($ids, $dari, $sampai, $r->user()?->id);

        return back()->with('sukses', sprintf(
            '%d baris dibuat, %d diperbarui, %d dilewati karena sudah dikoreksi. %d absen, %d di luar roster.',
            $n['dibuat'], $n['diperbarui'], $n['dilewati'], $n['absen'], $n['luar_roster'],
        ));
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function validasiMesin(Request $r, ?MesinAbsensi $mesin = null): array
    {
        return $r->validate([
            'nama'       => ['required', 'string', 'max:120'],
            'merek'      => ['required', 'in:'.implode(',', array_keys(MesinAbsensi::MEREK))],

            /* Nomor seri UNIK, sebab itulah yang dipakai endpoint
               mesin untuk mengetahui siapa yang mengirim. Dua alat
               bernomor seri sama membuat kiriman keduanya dibuktikan
               terhadap token salah satunya — dan yang kalah undian
               berhenti mengirim tanpa satu pesan galat pun. */
            'nomor_seri' => ['nullable', 'string', 'max:80', 'unique:hr_mesin_absensi,nomor_seri'.($mesin ? ','.$mesin->id : '')],

            'ip'         => ['nullable', 'string', 'max:45'],
            'blok_id'    => ['nullable', 'exists:mnr_blok,id'],
            'aktif'      => ['nullable', 'boolean'],
        ]);
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $r): array
    {
        $dari = $r->query('dari') ? Waktu::tanggal($r->query('dari')) : Waktu::kini()->startOfMonth();

        $sampai = $r->query('sampai') ? Waktu::tanggal($r->query('sampai')) : $dari->copy()->endOfMonth();

        if ($sampai->lt($dari)) $sampai = $dari->copy()->endOfMonth();

        /* Dibatasi seperti pada roster, dan karena alasan yang sama:
           rekap setahun untuk site berisi tiga ratus orang membaca
           seratus ribu baris untuk satu halaman. */
        if ($dari->diffInDays($sampai) > 92) $sampai = $dari->copy()->addDays(92);

        return [$dari, $sampai];
    }

    /** @return array<string,int> */
    private function ringkas($baris): array
    {
        $n = ['hadir' => 0, 'terlambat' => 0, 'belum_pulang' => 0, 'absen' => 0, 'luar_roster' => 0, 'luar_area' => 0, 'dijadwalkan' => 0];

        foreach ($baris as $b) {
            if (isset($n[$b['keadaan'] ?? '']))  $n[$b['keadaan']]++;
            if ($b['area'] === false)            $n['luar_area']++;
            if (in_array($b['roster'], Roster::BEKERJA, true)) $n['dijadwalkan']++;
        }

        return $n;
    }
}
