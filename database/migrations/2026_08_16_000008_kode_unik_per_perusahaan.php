<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use App\Support\Indeks;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor dan kode unik PER PERUSAHAAN, bukan se-pemasangan.
 *
 * Delapan tabel menyatakan kodenya unik secara global padahal
 * masing-masing punya company_id. Akibatnya pemasangan ini tidak dapat
 * menampung dua perusahaan yang kebetulan memakai penomoran yang sama —
 * dan penomoran yang sama justru yang WAJAR: hampir setiap perusahaan
 * tambang menomori prosedurnya K3L-PRO-01, gudang utamanya GD-01, dan
 * laporan bahaya pertamanya HZ-...-001.
 *
 * Kegagalannya muncul pada perusahaan KEDUA, bukan pertama, sehingga
 * tidak pernah terlihat selama pemasangan masih dipakai satu klien. Ia
 * pun tidak muncul sebagai pesan yang menjelaskan apa pun — hanya
 * "UNIQUE constraint failed" pada penyimpanan yang datanya sudah benar.
 *
 * Ditemukan ketika perusahaan contoh KEDUA dimuat: pemuatnya berhenti
 * pada gudang_lokasi.kode = 'GD-01' yang sudah dipakai perusahaan
 * contoh pertama.
 *
 * DUA YANG SENGAJA TETAP GLOBAL:
 *
 *   users.email — satu alamat surel satu akun, lintas perusahaan.
 *   certificates.verification_code — dipakai pada tautan verifikasi
 *   publik; kode yang sama pada dua perusahaan membuat tautannya
 *   menunjuk dua sertifikat sekaligus.
 *
 * Perubahan ini hanya MELONGGARKAN: setiap baris yang sah di bawah
 * aturan lama tetap sah di bawah aturan baru, jadi tidak ada data yang
 * dapat gagal memenuhinya.
 */
return new class extends Migration
{
    /**
     * tabel => [nama indeks lama, kolom penyusunnya]
     *
     * @var array<string,array{0:string,1:list<string>}>
     */
    private const INDEKS = [
        'hazard_reports'    => ['hazard_reports_kode_unique',    ['kode']],
        'inspections'       => ['inspections_kode_unique',       ['kode']],
        'ko_objects'        => ['ko_objects_kode_unique',        ['kode']],
        'documents'         => ['documents_kode_unique',         ['kode']],
        'energy_equipment'  => ['energy_equipment_kode_unique',  ['kode']],
        'gudang_lokasi'     => ['gudang_lokasi_kode_unique',     ['kode']],
        'gudang_barang'     => ['gudang_barang_kode_unique',     ['kode']],

        /* `area` di sini teks bebas — "Workshop" pada dua perusahaan
           berbeda adalah dua tempat berbeda, dan tanpa company_id
           keduanya bertabrakan pada tanggal yang sama. */
        'energy_power_logs' => ['energy_power_logs_tanggal_area_sumber_unique',
                                ['tanggal', 'area', 'sumber']],
    ];

    public function up(): void
    {
        foreach (self::INDEKS as $tabel => [$lama, $kolom]) {
            if (!Schema::hasTable($tabel) || !Schema::hasColumn($tabel, 'company_id')) continue;

            /* Keberadaan indeksnya DITANYAKAN, tidak dibungkus try/catch.

               Bentuk sebelumnya `try { $b->dropUnique($lama); } catch
               (\Throwable) {}` tidak menangkap apa pun: $b->dropUnique()
               hanya mencatat perintah ke daftar, dan daftar itu baru
               dijalankan sesudah closure selesai — di luar jangkauan
               try/catch. Pada basis data yang indeks lamanya sudah tidak
               ada, migrasi ini jatuh di tengah jalan, dengan pengaman
               yang tampak terpasang rapi tepat di atas baris yang
               menjatuhkannya. Lihat App\Support\Indeks. */
            $adaLama = Indeks::ada($tabel, $lama);
            $adaBaru = Indeks::ada($tabel, $tabel.'_company_unik');

            if ($adaBaru && !$adaLama) continue;   // sudah selesai sebelumnya

            Schema::table($tabel, function (Blueprint $b) use ($tabel, $kolom, $lama, $adaLama, $adaBaru) {
                if ($adaLama) $b->dropUnique($lama);
                if (!$adaBaru) $b->unique(array_merge(['company_id'], $kolom), $tabel.'_company_unik');
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEKS as $tabel => [$lama, $kolom]) {
            if (!Schema::hasTable($tabel) || !Schema::hasColumn($tabel, 'company_id')) continue;

            if (Indeks::ada($tabel, $tabel.'_company_unik')) {
                Schema::table($tabel, fn (Blueprint $b) => $b->dropUnique($tabel.'_company_unik'));
            }

            /* Aturan lama dikembalikan hanya bila datanya memang masih
               memenuhinya — turun ke aturan yang LEBIH ketat dapat gagal
               karena kode yang kini sah bagi dua perusahaan menjadi
               bentrok. Di sinilah try/catch memang tempatnya: ia
               membungkus Schema::table, yang benar-benar menjalankan
               SQL-nya, bukan Blueprint yang hanya mencatat. */
            try {
                Schema::table($tabel, fn (Blueprint $b) => $b->unique($kolom, $lama));
            } catch (\Throwable) {
                // Datanya sudah tidak memenuhi aturan lama; dibiarkan.
            }
        }
    }
};
