<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Samakan penamaan kelas risiko menengah.
 *
 * Kelas risiko perusahaan bukan sekadar keterangan: ia memilih KOLOM pada
 * tabel mandays audit SMKP. Sejak tabel itu dipakai, hanya tiga nama yang
 * dikenal — Tinggi, Menengah, Rendah — dan baris yang masih bertuliskan
 * "Sedang" jatuh ke kelas paling ketat, sehingga perusahaan berisiko
 * menengah ditagih hari kerja audit sebanyak yang berisiko tinggi.
 *
 * Perubahan data, bukan perubahan bentuk tabel; dapat dibalik persis.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->where('risk_class', 'Sedang')->update(['risk_class' => 'Menengah']);
    }

    public function down(): void
    {
        DB::table('companies')->where('risk_class', 'Menengah')->update(['risk_class' => 'Sedang']);
    }
};
