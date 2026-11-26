<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * "Milik bersama" sebagai KEPUTUSAN, bukan sekadar kolom yang kosong.
 *
 * Prosedur dan berita tanpa perusahaan terbaca oleh seluruh perusahaan.
 * Sebagian memang dimaksudkan begitu — prosedur baku dan pengumuman
 * se-pemasangan — tetapi sampai sekarang keadaan itu tidak dapat
 * dibedakan dari baris yang pemiliknya belum sempat ditetapkan.
 * Akibatnya peringatan "Prosedur & berita tanpa pemilik" di Pemeriksaan
 * Mandiri tidak pernah dapat padam: satu-satunya cara memadamkannya
 * adalah memberinya pemilik, yang justru menyembunyikannya dari
 * perusahaan lain yang berhak membacanya.
 *
 * Kolom `bersama` mencatat bahwa seseorang sudah memutuskan baris itu
 * milik semua perusahaan. Batas datanya tidak berubah sama sekali —
 * yang menentukan siapa membaca tetap company_id; kolom ini hanya
 * memberi tahu Pemeriksaan Mandiri bahwa barisnya sudah ditinjau.
 *
 * Baris contoh DemoSeeder ditandai sekarang. Keempatnya konten umum
 * (SOP LOTO, SOP bekerja di ketinggian, dua pengumuman Bulan K3) yang
 * ditanam sebagai bahan bersama sejak awal; mencocokkannya lewat kode
 * DAN judul aslinya memastikan baris yang sudah diubah orang tidak ikut
 * dianggap diputuskan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['procedures', 'news'] as $tabel) {
            if (Schema::hasColumn($tabel, 'bersama')) continue;

            Schema::table($tabel, function (Blueprint $t) {
                $t->boolean('bersama')->default(false)->after('company_id');
            });
        }

        DB::table('procedures')->whereNull('company_id')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('code', 'SOP-001')->where('title', 'Prosedur Lock Out Tag Out (LOTO)'))
                ->orWhere(fn ($w) => $w->where('code', 'SOP-002')->where('title', 'Prosedur Bekerja di Ketinggian')))
            ->update(['bersama' => true]);

        DB::table('news')->whereNull('company_id')
            ->whereIn('title', ['Bulan K3 Nasional 2026 Dimulai', 'Pembaruan Prosedur LOTO'])
            ->update(['bersama' => true]);
    }

    public function down(): void
    {
        foreach (['procedures', 'news'] as $tabel) {
            if (! Schema::hasColumn($tabel, 'bersama')) continue;

            Schema::table($tabel, fn (Blueprint $t) => $t->dropColumn('bersama'));
        }
    }
};
