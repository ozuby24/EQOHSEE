<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Miners mengikuti Safe Track — tahap 3: SIMPER dan pengajuan lanjutannya.
 *
 * Tidak ada kolom atau tabel baru: seluruh kolom yang dibutuhkan sudah
 * dipasang tahap dasar (2026_11_07_100001). Migrasi ini hanya MENGISI
 * kolom baru dari kolom lama pada baris yang sudah ada, supaya data lama
 * terbaca menurut aturan Safe Track tanpa ada yang hilang:
 *
 *   1. `authority` unit SIMPER (F/R/P/I/T) diturunkan dari `kewenangan`
 *      lama — operator → F, pengawas → R, trainer → I — hanya bila
 *      masih kosong. `kewenangan` tetap disimpan.
 *   2. Type Unit lama (satu `jenis_unit_id` per baris) disalin ke tabel
 *      perantara `mnr_simper_unit_jenis` / `mnr_simper_ajuan_unit_jenis`
 *      yang kini menampung BANYAK Type Unit per baris (`jenis_unit`
 *      berkoma Safe Track). Kolom lamanya tetap.
 *   3. Nomor SIMPER kosong diisi `SIMPER-00{id}` (bentuk Safe Track).
 *   4. Sumber tanggal SIM yang kosong ditandai `manual` bila tanggalnya
 *      ada — tanggal itu memang diketik orang, bukan terbaca otomatis.
 *
 * Masa berlaku SIMPER kini MENGIKUTI TANGGAL SIM (keputusan pemilik),
 * bukan lagi tanggal tersempit dari 31 Desember, SIM, dan permit. Baris
 * lama yang tidak punya tanggal SIM tetap memakai `berlaku_sampai`-nya
 * (Simper::habisEfektif) — tidak ada kartu yang mendadak "belum diisi".
 */
return new class extends Migration
{
    private const AUTHORITY = ['operator' => 'F', 'pengawas' => 'R', 'trainer' => 'I'];

    public function up(): void
    {
        foreach (['mnr_simper_unit', 'mnr_simper_ajuan_unit'] as $tabel) {
            if (! Schema::hasColumn($tabel, 'authority')) continue;

            foreach (self::AUTHORITY as $lama => $baru) {
                DB::table($tabel)->whereNull('authority')->where('kewenangan', $lama)->update(['authority' => $baru]);
            }
        }

        $this->salinJenis('mnr_simper_unit', 'mnr_simper_unit_jenis', 'simper_unit_id');
        $this->salinJenis('mnr_simper_ajuan_unit', 'mnr_simper_ajuan_unit_jenis', 'ajuan_unit_id');

        DB::table('mnr_simper')->whereNull('no_simper')->orderBy('id')->each(function ($s) {
            DB::table('mnr_simper')->where('id', $s->id)->update(['no_simper' => 'SIMPER-00'.$s->id]);
        });

        DB::table('mnr_simper')->whereNull('simpol_sumber')->whereNotNull('simpol_berlaku_sampai')
            ->update(['simpol_sumber' => 'manual']);
    }

    /**
     * Isian balik di atas tidak dibatalkan: kolom asalnya (`kewenangan`,
     * `jenis_unit_id`) tidak pernah diubah, jadi keadaan lama tetap utuh,
     * dan membuang baris perantara dapat ikut membuang Type Unit yang
     * ditambahkan sesudah migrasi ini.
     */
    public function down(): void
    {
    }

    private function salinJenis(string $sumber, string $tujuan, string $kunci): void
    {
        if (! Schema::hasTable($tujuan)) return;

        $sudah = DB::table($tujuan)->pluck($kunci)->flip();
        $kini  = now();

        DB::table($sumber)->whereNotNull('jenis_unit_id')->orderBy('id')
            ->each(function ($u) use ($tujuan, $kunci, $sudah, $kini) {
                if ($sudah->has($u->id)) return;

                DB::table($tujuan)->insert([
                    $kunci          => $u->id,
                    'jenis_unit_id' => $u->jenis_unit_id,
                    'created_at'    => $kini,
                    'updated_at'    => $kini,
                ]);
            });
    }
};
