<?php

use App\Support\Geometri;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyimpan ukuran terhitung pada tiap layer peta.
 *
 * Luas dan keliling sebenarnya sudah terkandung di dalam koordinat yang
 * tersimpan, tetapi menghitungnya ulang setiap kali daftar dibuka berarti
 * mengurai gumpalan JSON yang boleh mencapai lima juta karakter — untuk
 * setiap layer, pada setiap permintaan, hanya demi satu angka hektare di
 * ujung baris.
 *
 * Karena disimpan, nilainya bisa basi. Itu dicegah di model: ukurannya
 * dihitung ulang pada setiap penyimpanan, bukan diserahkan kepada
 * pemanggil. Ukuran yang boleh diisi dari luar cepat atau lambat akan
 * diisi salah, dan tidak ada yang dapat mengetahuinya dari angkanya saja.
 *
 * Tanggal dan sumber survei ikut ditambahkan di sini. Peta tambang selalu
 * merupakan potret pada satu tanggal; dua layer pit tanpa tanggal tidak
 * dapat dibandingkan, dan kemajuan area tidak dapat dihitung sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mine_map_layers', function (Blueprint $t) {
            $t->decimal('luas_m2', 16, 2)->default(0)->after('geojson');
            $t->decimal('keliling_m', 14, 2)->default(0)->after('luas_m2');
            $t->decimal('titik_lon', 11, 7)->nullable()->after('keliling_m');
            $t->decimal('titik_lat', 10, 7)->nullable()->after('titik_lon');
            $t->unsignedSmallInteger('jumlah_fitur')->default(0)->after('titik_lat');

            $t->date('tanggal_survey')->nullable()->after('jumlah_fitur');
            $t->string('sumber_survey')->nullable()->after('tanggal_survey');
        });

        // Kemajuan area dibaca per tipe menurut tanggal survei; tanpa
        // indeks ini tiap pembacaan memindai seluruh tabel beserta
        // kolom geojson-nya.
        Schema::table('mine_map_layers', function (Blueprint $t) {
            $t->index(['tipe', 'tanggal_survey'], 'mine_map_layers_kemajuan_idx');
        });

        // Layer yang sudah terlanjur tersimpan ikut diukur, supaya tidak
        // ada baris yang diam-diam berluas nol.
        DB::table('mine_map_layers')->orderBy('id')->chunk(50, function ($baris) {
            foreach ($baris as $r) {
                $u = Geometri::ukur((string) $r->geojson);

                DB::table('mine_map_layers')->where('id', $r->id)->update([
                    'luas_m2'      => $u['luas_m2'],
                    'keliling_m'   => $u['keliling_m'],
                    'titik_lon'    => $u['titik'][0] ?? null,
                    'titik_lat'    => $u['titik'][1] ?? null,
                    'jumlah_fitur' => $u['fitur'],
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('mine_map_layers', function (Blueprint $t) {
            $t->dropIndex('mine_map_layers_kemajuan_idx');
            $t->dropColumn([
                'luas_m2', 'keliling_m', 'titik_lon', 'titik_lat',
                'jumlah_fitur', 'tanggal_survey', 'sumber_survey',
            ]);
        });
    }
};
