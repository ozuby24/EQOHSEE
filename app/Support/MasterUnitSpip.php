<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Daftar awal jenis unit SPIP.
 *
 * TITIK BERANGKAT, BUKAN DAFTAR TETAP — dan bedanya menentukan cara
 * memakainya. Data D'Best memperlihatkan master_unitkelayakans di sana
 * berisi jenis unit yang benar-benar dipakai perusahaannya, bukan
 * daftar baku dari peraturan; setiap tambang punya susunan alat yang
 * berbeda. Karena itu baris di sini ditanam sebagai milik BERSAMA
 * (company_id NULL) dan tiap perusahaan bebas menambah jenisnya
 * sendiri di atasnya.
 *
 * Daftar yang dianggap lengkap justru berbahaya: yang jenisnya tidak
 * ada akan diakali dengan mengetik bebas, mengembalikan persis masalah
 * yang hendak dihilangkan daftar ini.
 *
 * Interval uji yang tercantum adalah kelaziman di pertambangan
 * Indonesia, bukan kutipan pasal. Ia dipakai MENGUSULKAN tanggal
 * kadaluarsa saat uji dicatat, dan tetap dapat ditimpa — yang berlaku
 * adalah yang tertulis di sertifikat dari lembaga ujinya.
 */
final class MasterUnitSpip
{
    /** @var list<array{0:string,1:string,2:string,3:int}> kode, unit, kategori, interval */
    public const DAFTAR = [
        /* Alat angkut */
        ['DT',    'Dump Truck',                    'Sarana',    1],
        ['HD',    'Heavy Dump Truck',              'Sarana',    1],
        ['LV',    'Light Vehicle',                 'Sarana',    1],
        ['BUS',   'Bus Karyawan',                  'Sarana',    1],
        ['WT',    'Water Truck',                   'Sarana',    1],
        ['FT',    'Fuel Truck',                    'Sarana',    1],
        ['LT',    'Lube Truck',                    'Sarana',    1],
        ['TB',    'Trailer / Lowboy',              'Sarana',    1],
        ['AMB',   'Ambulance',                     'Sarana',    1],
        ['FIRE',  'Fire Truck',                    'Sarana',    1],

        /* Alat gali–muat dan pendukung */
        ['EX',    'Excavator',                     'Sarana',    1],
        ['WL',    'Wheel Loader',                  'Sarana',    1],
        ['BD',    'Bulldozer',                     'Sarana',    1],
        ['MG',    'Motor Grader',                  'Sarana',    1],
        ['CP',    'Compactor / Vibro Roller',      'Sarana',    1],
        ['DR',    'Drilling Rig',                  'Sarana',    1],

        /* Alat angkat — masa berlakunya paling pendek, dan memang
           begitu: kegagalan alat angkat menimpa orang di bawahnya. */
        ['CR',    'Mobile Crane',                  'Peralatan', 1],
        ['OHC',   'Overhead Crane',                'Peralatan', 1],
        ['FL',    'Forklift',                      'Peralatan', 1],
        ['MHC',   'Man Lift / Hoist',              'Peralatan', 1],
        ['CB',    'Chain Block / Lifting Gear',    'Peralatan', 1],

        /* Bejana dan instalasi bertekanan */
        ['CMP',   'Kompresor Udara',               'Instalasi', 1],
        ['BJT',   'Bejana Tekan',                  'Instalasi', 1],
        ['BLR',   'Boiler',                        'Instalasi', 1],
        ['TNK',   'Tangki Timbun BBM',             'Instalasi', 3],

        /* Kelistrikan */
        ['GEN',   'Genset',                        'Instalasi', 1],
        ['TRF',   'Trafo / Gardu Distribusi',      'Instalasi', 3],
        ['PNL',   'Panel Listrik Utama',           'Instalasi', 1],
        ['PTR',   'Instalasi Penyalur Petir',      'Instalasi', 2],

        /* Prasarana */
        ['JLN',   'Jalan Tambang / Hauling Road',  'Prasarana', 1],
        ['JBT',   'Jembatan Timbang',              'Prasarana', 1],
        ['CPP',   'Crushing Plant',                'Prasarana', 1],
        ['CVR',   'Conveyor',                      'Prasarana', 1],
        ['PORT',  'Pelabuhan / Jetty',             'Prasarana', 1],
        ['SETL',  'Settling Pond',                 'Prasarana', 1],
        ['WSP',   'Workshop',                      'Prasarana', 3],
        ['MGZ',   'Gudang Handak',                 'Prasarana', 1],
        ['APAR',  'APAR & Sistem Proteksi Kebakaran', 'Peralatan', 1],
    ];

    /**
     * Menanam daftar awal sebagai milik bersama.
     *
     * Memakai DB::table() dengan sengaja, bukan model KoUnitMaster:
     * trait BerpemilikPerusahaan akan mengisi company_id dengan
     * perusahaan siapa pun yang kebetulan menjalankan ini, sehingga
     * daftar yang dimaksudkan milik bersama menjadi milik satu
     * perusahaan — dan perusahaan lain membuka menu jenis unit yang
     * kosong tanpa tahu sebabnya.
     *
     * updateOrInsert, bukan insert: menjalankannya dua kali tidak boleh
     * melahirkan baris kedua, dan juga tidak boleh menimpa keterangan
     * yang sudah disunting pemakainya.
     */
    public static function tanam(): int
    {
        $n = 0;

        foreach (self::DAFTAR as $i => [$kode, $unit, $kategori, $interval]) {
            DB::table('ko_unit_master')->updateOrInsert(
                ['company_id' => null, 'kode' => $kode],
                [
                    'unit'           => $unit,
                    'kategori'       => $kategori,
                    'interval_tahun' => $interval,
                    'aktif'          => true,
                    'urutan'         => $i,
                    'updated_at'     => now(),
                    'created_at'     => now(),
                ],
            );
            $n++;
        }

        return $n;
    }
}
