<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hr\{AbsensiJejak, MesinAbsensi};
use App\Models\Miners\Pekerja;
use App\Support\Hr\{Geofence, Rekonsiliasi};
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan pindaian dari mesin absensi di lapangan.
 *
 * Push SDK ZKTeco dan Hikvision mengirim ke sebuah endpoint dengan
 * kunci alatnya sendiri — tidak ada orang yang masuk ke dalam alat
 * pindai. Karena itu di sini TIDAK ADA sesi pengguna, dan seluruh
 * akibatnya harus ditanggung dengan tegas:
 *
 *   BATAS PERUSAHAAN DIPASANG TANGAN. Global scope MilikPerusahaan
 *   sengaja tidak menyaring apa pun ketika tidak ada pengguna — itu
 *   yang membuat perintah konsol dan antrean bekerja atas seluruh
 *   data. Di sini keadaan itu berbahaya: dibiarkan, sebuah alat di
 *   pos jaga satu perusahaan dapat menuliskan absensi atas nama
 *   pekerja perusahaan lain hanya dengan menyebut nomor induknya.
 *   Karena itu tiap pencarian di bawah menyebut batasnya sendiri.
 *
 *   BATCH TIDAK PERNAH DITOLAK SELURUHNYA KARENA SATU BARIS.
 *   Alat mengirim ulang sampai berhasil; satu peristiwa atas nomor
 *   induk yang tidak dikenal akan menggantung SELURUH hari itu
 *   selamanya — alat mengirim lagi, ditolak lagi, dan absensi seluruh
 *   regu tidak pernah masuk. Yang sah diterima, yang tidak dilaporkan
 *   per barisnya supaya dapat diperbaiki di pangkalnya.
 *
 *   IDEMPOTEN LEWAT `kunci`. Sinyal site putus-putus adalah keadaan
 *   biasa, bukan luar biasa. Batch yang sama dikirim lima kali harus
 *   menghasilkan data yang sama persis dengan dikirim sekali.
 */
class AbsensiIngestController extends Controller
{
    /**
     * Batas jumlah peristiwa dalam satu kiriman.
     *
     * Alat yang baru terhubung kembali sesudah seminggu luring
     * mengirim ribuan baris sekaligus. Dibatasi di sini, ia terpaksa
     * memecahnya — dan satu kiriman yang gagal di tengah tidak
     * menyeret seminggu data bersamanya.
     */
    public const MAKS_BATCH = 500;

    public function store(Request $r)
    {
        $mesin = $this->mesin($r);

        $data = $r->validate([
            'jejak'                       => ['required', 'array', 'min:1', 'max:'.self::MAKS_BATCH],
            'jejak.*.kunci'               => ['required', 'string', 'max:100'],
            'jejak.*.pekerja'             => ['required', 'string', 'max:60'],
            'jejak.*.terjadi'             => ['required', 'date'],
            'jejak.*.arah'                => ['required', 'in:masuk,keluar'],
            'jejak.*.sumber'              => ['nullable', 'in:mesin,ponsel,manual'],
            'jejak.*.lat'                 => ['nullable', 'numeric', 'between:-90,90'],
            'jejak.*.lng'                 => ['nullable', 'numeric', 'between:-180,180'],
            'jejak.*.luring'              => ['nullable', 'boolean'],
            'jejak.*.rujukan_biometrik'   => ['nullable', 'string', 'max:100'],
            'jejak.*.catatan'             => ['nullable', 'string', 'max:500'],
        ]);

        $baris = $data['jejak'];

        /* Kunci yang sudah ada dibaca SEKALI di muka. Diperiksa satu
           per satu, kiriman ulang lima ratus baris membaca basis data
           lima ratus kali untuk jawaban yang seluruhnya "sudah ada". */
        $sudah = AbsensiJejak::withoutGlobalScopes()
            ->whereIn('kunci', array_column($baris, 'kunci'))
            ->pluck('kunci')
            ->flip();

        $blok = $mesin->blok;

        $hasil  = ['diterima' => 0, 'terulang' => 0, 'ditolak' => []];
        $orang  = [];
        $awal   = null;
        $akhir  = null;

        /* Kunci yang berulang DI DALAM satu kiriman juga dijaga.
           Batasan unik di basis data akan menangkapnya sebagai galat
           500 di tengah transaksi — dan alat yang menerima 500
           mengirim ulang, selamanya. */
        $dalamBatch = [];

        DB::transaction(function () use ($baris, $sudah, $mesin, $blok, &$hasil, &$orang, &$awal, &$akhir, &$dalamBatch) {
            foreach ($baris as $i => $b) {
                $kunci = $b['kunci'];

                if (isset($sudah[$kunci]) || isset($dalamBatch[$kunci])) {
                    $hasil['terulang']++;
                    continue;
                }

                $p = $this->pekerja($b['pekerja'], $mesin);

                if (! $p) {
                    $hasil['ditolak'][] = [
                        'baris' => $i,
                        'kunci' => $kunci,
                        'sebab' => 'Pekerja tidak dikenal: '.$b['pekerja'],
                    ];
                    continue;
                }

                $saat = Waktu::lokal($b['terjadi']);

                if (! $saat) {
                    $hasil['ditolak'][] = ['baris' => $i, 'kunci' => $kunci, 'sebab' => 'Waktu tidak terbaca.'];
                    continue;
                }

                $lat = isset($b['lat']) ? (float) $b['lat'] : null;
                $lng = isset($b['lng']) ? (float) $b['lng'] : null;

                /* Geofence dihitung SEKARANG lalu disimpan. Dihitung
                   ulang saat dibaca, menggeser pin peta sebuah blok
                   mengubah keputusan yang sudah diambil atas absen
                   tahun lalu. */
                $area = Geofence::periksa($blok, $lat, $lng);

                AbsensiJejak::withoutGlobalScopes()->create([
                    'company_id'        => $mesin->company_id,
                    'pekerja_id'        => $p->id,
                    'mesin_id'          => $mesin->id,
                    'terjadi'           => Waktu::simpan($saat),
                    'diterima'          => Waktu::kiniSimpan(),
                    'arah'              => $b['arah'],
                    'sumber'            => $b['sumber'] ?? 'mesin',
                    'lat'               => $lat,
                    'lng'               => $lng,
                    'jarak_m'           => $area['jarak'],
                    'dalam_area'        => $area['dalam'],
                    'rujukan_biometrik' => $b['rujukan_biometrik'] ?? null,
                    'luring'            => (bool) ($b['luring'] ?? false),
                    'kunci'             => $kunci,
                    'catatan'           => $b['catatan'] ?? null,
                ]);

                $dalamBatch[$kunci] = true;
                $hasil['diterima']++;

                $orang[$p->id] = $p->id;

                $hari  = $saat->toDateString();
                $awal  = ($awal === null || $hari < $awal) ? $hari : $awal;
                $akhir = ($akhir === null || $hari > $akhir) ? $hari : $akhir;
            }

            $mesin->forceFill(['terakhir_hubung' => Waktu::kiniSimpan()])->save();
        });

        /* Rekonsiliasi dijalankan atas hari yang benar-benar tersentuh
           saja, DIMUNDURKAN SEHARI. Tap pulang pukul 06.00 adalah
           milik shift malam KEMARIN; dihitung mulai hari tapnya, baris
           kemarin tidak pernah ikut diperbarui dan orangnya tercatat
           "belum tap pulang" pada hari ia sebenarnya sudah pulang. */
        $rekon = null;

        if ($orang !== [] && $awal !== null) {
            $rekon = Rekonsiliasi::jalankan(
                array_values($orang),
                Carbon::parse($awal, Waktu::zona())->subDay(),
                Carbon::parse($akhir, Waktu::zona()),
            );
        }

        return response()->json([
            'diterima'  => $hasil['diterima'],
            'terulang'  => $hasil['terulang'],
            'ditolak'   => $hasil['ditolak'],
            'rekonsiliasi' => $rekon,
        ], $hasil['ditolak'] === [] ? 200 : 207);
    }

    /**
     * Mesin yang mengirim, beserta pembuktian tokennya.
     *
     * Nomor seri menyebut SIAPA, token membuktikannya. Yang tersimpan
     * hanyalah hash — alat yang dicabut dari dinding pos jaga membawa
     * tokennya, dan token itu tidak boleh dapat dibaca balik dari basis
     * data oleh siapa pun yang membukanya.
     */
    private function mesin(Request $r): MesinAbsensi
    {
        $seri  = (string) $r->header('X-Mesin-Seri', '');
        $token = (string) ($r->bearerToken() ?? $r->header('X-Mesin-Token', ''));

        $gagal = fn () => throw ValidationException::withMessages([
            'mesin' => 'Mesin tidak dikenal atau token tidak cocok.',
        ])->status(401);

        if ($seri === '' || $token === '') $gagal();

        $mesin = MesinAbsensi::withoutGlobalScopes()
            ->where('nomor_seri', $seri)
            ->first();

        /* Mesin yang TIDAK AKTIF ditolak seperti mesin yang tidak ada,
           dan pesannya dibuat sama persis. Dibedakan, siapa pun yang
           memegang alat curian dapat mengetahui apakah nomor serinya
           masih terdaftar — dan itulah setengah pertama dari
           serangannya. */
        if (! $mesin || ! $mesin->aktif || ! $mesin->tokenCocok($token)) $gagal();

        return $mesin;
    }

    /**
     * Pekerja yang ditunjuk sebuah peristiwa.
     *
     * Alat menyimpan nomor, bukan nama. Nomor registrasi dicoba lebih
     * dahulu karena itulah yang dicetak pada kartu; nomor induk dan NIK
     * menyusul, sebab alat yang dienrol pemasangnya sering memakai
     * salah satunya.
     *
     * BATASNYA DISEBUT DI SINI, bukan diserahkan pada global scope:
     * tanpa pengguna, scope itu tidak menyaring apa pun.
     */
    private function pekerja(string $nomor, MesinAbsensi $mesin): ?Pekerja
    {
        $nomor = trim($nomor);

        if ($nomor === '') return null;

        return Pekerja::withoutGlobalScopes()
            ->where(function ($q) use ($mesin) {
                /* Baris tanpa perusahaan terlihat oleh semua — arti
                   kolomnya, sama seperti pada MilikPerusahaan. Baris
                   milik perusahaan LAIN tidak pernah. */
                $q->whereNull('company_id');

                if ($mesin->company_id) $q->orWhere('company_id', $mesin->company_id);
            })
            ->where(fn ($q) => $q
                ->where('no_registrasi', $nomor)
                ->orWhere('no_induk', $nomor)
                ->orWhere('nik', $nomor))
            ->first();
    }
}
