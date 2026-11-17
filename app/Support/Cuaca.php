<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Perkiraan cuaca otomatis dari koordinat situs.
 *
 * Pendamping KondisiSitus, bukan penggantinya. Yang dicatat sendiri di
 * modul Penirisan SELALU menang: itu hujan yang benar-benar terukur di
 * lokasi, oleh alat milik perusahaan itu, dan keputusan lapangan memang
 * diambil dari angka semacam itu. Kelas ini hanya mengisi ketika
 * catatannya belum ada — supaya lencana cuaca tidak berdiri kosong
 * berbulan-bulan pada pemasangan yang belum sempat mengisi penirisan.
 *
 * ─────────────────────────────────────────────────────────────────────
 * KOORDINATNYA YANG PALING SULIT, BUKAN CUACANYA
 *
 * Mengambil cuaca dari sepasang koordinat itu sepele. Yang sulit
 * menentukan koordinat mana, dan di situlah letak seluruh bahaya fitur
 * ini: koordinat yang meleset tetap memulangkan angka hujan yang
 * terlihat masuk akal, dan tidak ada satu pun tanda bahwa ia milik
 * tempat lain.
 *
 * Terukur pada geocoder Open-Meteo, dengan nama-nama yang benar-benar
 * dipakai perusahaan tambang Indonesia:
 *
 *   "Berau"  → Dili, Timor-Leste        (−8,30  125,56)
 *   "Kutai"  → Chittagong, Bangladesh   (24,18   91,12)
 *
 * Keduanya kabupaten di Kalimantan Timur. Disaring ke Indonesia saja,
 * "Kutai" masih jatuh ke Kutai di JAWA TIMUR — pulau yang berbeda,
 * seribu kilometer dari tambangnya, dan cuacanya tetap tergambar rapi.
 *
 * Karena itu sumbernya berjenjang, dari yang paling dapat dipercaya ke
 * yang paling kasar, dan JENJANG ITU IKUT DISEBUTKAN pada tampilannya.
 * Yang kasar boleh dipakai; yang tidak boleh adalah yang kasar menyamar
 * sebagai yang tepat.
 */
final class Cuaca
{
    /** Sedekat apa ke (0,0) sebuah titik dianggap belum berkoordinat. */
    private const AMBANG_NOL = 0.05;

    /**
     * Perkiraan untuk sebuah perusahaan, atau null.
     *
     * @return array{hujanMm:float, sumber:string, tempat:string, kasar:bool}|null
     */
    public static function untuk(?Company $perusahaan): ?array
    {
        if (!$perusahaan || !config('cuaca.aktif', true)) return null;

        $titik = self::koordinat($perusahaan);

        if ($titik === null) return null;

        $mm = self::hujan($titik['lat'], $titik['lon']);

        if ($mm === null) return null;

        return [
            'hujanMm' => $mm,
            'sumber'  => 'perkiraan',
            'tempat'  => $titik['tempat'],
            'kasar'   => $titik['kasar'],
        ];
    }

    /**
     * Koordinat situs, berjenjang menurut kepercayaan.
     *
     * @return array{lat:float, lon:float, tempat:string, kasar:bool}|null
     */
    public static function koordinat(Company $perusahaan): ?array
    {
        /* 1. Peta tambang yang digambar sendiri. Ini satu-satunya sumber
              yang benar-benar menunjuk wilayah izinnya, dan karena itu
              ia selalu didahulukan. */
        $peta = KondisiSitus::titikPetaPublik($perusahaan);

        if ($peta !== null) {
            return $peta + ['tempat' => trim((string) $perusahaan->location) ?: $perusahaan->name, 'kasar' => false];
        }

        /* 2. Nama tempat pada kolom lokasi atau alamat, diterjemahkan
              sekali lalu disimpan. */
        foreach ([$perusahaan->location, $perusahaan->address] as $sebutan) {
            $sebutan = trim((string) $sebutan);

            if ($sebutan === '') continue;

            if ($t = self::terjemahkan($sebutan)) return $t;
        }

        return null;
    }

    /**
     * Nama tempat → koordinat.
     *
     * Disimpan lama: nama tempat tidak berpindah, dan tiap penerjemahan
     * adalah satu permintaan ke luar.
     *
     * @return array{lat:float, lon:float, tempat:string, kasar:bool}|null
     */
    private static function terjemahkan(string $sebutan): ?array
    {
        $kunci = 'cuaca:titik:'.md5(mb_strtolower($sebutan));
        $jam   = (int) config('cuaca.simpan_koordinat_jam', 720);

        $hasil = Cache::remember($kunci, now()->addHours($jam), function () use ($sebutan) {
            /* Provinsi lebih dulu, sebelum geocoder disentuh.
               Geocoder Open-Meteo hanya mengindeks tempat BERPENDUDUK,
               sehingga "Kalimantan Timur" tidak pernah ketemu di sana —
               dan itu justru isi kolom lokasi yang paling lazim. */
            if ($p = self::provinsi($sebutan)) return $p;

            return self::geocode($sebutan) ?? ['kosong' => true];
        });

        return ($hasil['kosong'] ?? false) ? null : $hasil;
    }

    /** Titik tengah provinsi, bila sebutannya memang nama provinsi. */
    private static function provinsi(string $sebutan): ?array
    {
        $berkas = resource_path('data/wilayah/provinsi.json');

        if (!is_file($berkas)) return null;

        $daftar = json_decode((string) file_get_contents($berkas), true)['provinsi'] ?? [];
        $cari   = mb_strtolower($sebutan);

        foreach ($daftar as $p) {
            $nama = mb_strtolower($p['nama']);

            /* Cocok penuh, atau namanya termuat di dalam sebutan —
               "Site Sangatta, Kalimantan Timur" ikut tertangkap. */
            if ($cari === $nama || str_contains($cari, $nama)) {
                return [
                    'lat' => (float) $p['lat'], 'lon' => (float) $p['lon'],
                    'tempat' => $p['nama'],

                    /* KASAR, dan disebut kasar. Kalimantan Timur luasnya
                       129.000 km²; hujan di titik tengahnya tidak
                       menjanjikan apa pun tentang hujan di pit. */
                    'kasar' => true,
                ];
            }
        }

        return null;
    }

    /** @return array{lat:float, lon:float, tempat:string, kasar:bool}|null */
    private static function geocode(string $sebutan): ?array
    {
        try {
            $r = Http::timeout((int) config('cuaca.jeda_detik', 4))
                ->get(config('cuaca.url_geocode'), ['name' => $sebutan, 'count' => 10]);

            if (!$r->successful()) return null;

            /* DISARING KE INDONESIA. Tanpa saringan ini "Berau"
               memulangkan Dili di Timor-Leste, dan cuacanya tergambar
               seolah itu cuaca tambangnya. */
            foreach ($r->json('results') ?? [] as $hasil) {
                if (($hasil['country_code'] ?? null) !== 'ID') continue;

                return [
                    'lat' => (float) $hasil['latitude'],
                    'lon' => (float) $hasil['longitude'],

                    /* Nama yang KETEMU, bukan nama yang dicari. Keduanya
                       kerap berbeda, dan selisihnya itulah satu-satunya
                       tanda yang dipunyai pembacanya bahwa penerjemahan
                       namanya meleset. */
                    'tempat' => trim(($hasil['name'] ?? $sebutan).', '.($hasil['admin1'] ?? ''), ' ,'),
                    'kasar'  => false,
                ];
            }
        } catch (\Throwable) {
            // Jaringan site tambang kerap putus; itu bukan galat aplikasi.
        }

        return null;
    }

    /** Curah hujan sejam terakhir pada sebuah titik, dalam milimeter. */
    private static function hujan(float $lat, float $lon): ?float
    {
        if (abs($lat) < self::AMBANG_NOL && abs($lon) < self::AMBANG_NOL) return null;

        $kunci = sprintf('cuaca:hujan:%.3f,%.3f', $lat, $lon);

        $nilai = Cache::remember($kunci, now()->addMinutes((int) config('cuaca.simpan_menit', 30)), function () use ($lat, $lon) {
            try {
                $r = Http::timeout((int) config('cuaca.jeda_detik', 4))
                    ->get(config('cuaca.url'), [
                        'latitude'  => $lat,
                        'longitude' => $lon,
                        'current'   => 'precipitation',
                        'timezone'  => 'Asia/Makassar',
                    ]);

                if (!$r->successful()) return ['kosong' => true];

                $mm = $r->json('current.precipitation');

                return $mm === null ? ['kosong' => true] : ['mm' => (float) $mm];
            } catch (\Throwable) {
                return ['kosong' => true];
            }
        });

        return ($nilai['kosong'] ?? false) ? null : round((float) $nilai['mm'], 1);
    }
}
