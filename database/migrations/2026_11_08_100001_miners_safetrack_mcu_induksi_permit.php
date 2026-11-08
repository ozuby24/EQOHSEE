<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Miners mengikuti Safe Track — tahap 2: MCU, induksi, Mine Permit, dan
 * perpanjangan Mine Permit.
 *
 * Sama seperti tahap dasar: DIKEMBANGKAN DI TEMPAT. Tidak ada tabel yang
 * dibuang lalu dibuat ulang, dan tidak ada baris lama yang dihapus.
 *
 * ATURAN YANG BERUBAH (keputusan pemilik: aturan yang diperdebatkan
 * mengikuti Safe Track):
 *
 *   1. MASA BERLAKU MINE PERMIT MENGIKUTI MCU (`tgl_expired = nom`),
 *      dengan kunci manual dan sinkron malam — bukan 31 Desember atau
 *      jumlah hari per tipe permit. Permit lama yang tanggalnya dihitung
 *      menurut aturan lama (`tahunan`/`tipe`) dipindah ke `mcu` bila
 *      MCU dasarnya bertanggal; tanggal dan sumber LAMANYA disimpan di
 *      `berlaku_asal` / `sumber_asal` — tidak ada yang hilang. Permit
 *      yang MCU dasarnya tidak bertanggal DIKUNCI manual pada tanggal
 *      lamanya, supaya tidak ada kartu yang mendadak kehilangan masa
 *      berlakunya.
 *   2. PERMIT DIMILIKI PERUSAHAAN PEMBERI KERJA — `COALESCE(contractor_id,
 *      corps_id)` Safe Track. Kartu karyawan mitra yang dibuat dari akun
 *      IUP sebelumnya bermilik IUP, sehingga mitranya sendiri tidak
 *      dapat melihatnya. Kontraktornya diisi dari perusahaan pekerjanya
 *      bila perusahaan itu memang mitra IUP penerbit.
 *   3. HASIL "RUJUKAN" ditambahkan ke master hasil MCU (dipakai dokter
 *      untuk menahan seseorang tanpa menyatakannya Unfit), dan hasil
 *      yang KOSONG pada MCU yang belum selesai diisi "Belum Dinilai" —
 *      keadaan awal Safe Track yang menahan persetujuan dokter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mnr_permit', function (Blueprint $t) {
            /* Tanggal habis dan sumbernya MENURUT ATURAN LAMA, sebelum
               dipindah mengikuti MCU. Arsip, bukan dipakai menghitung. */
            $t->date('berlaku_asal')->nullable();
            $t->string('sumber_asal', 12)->nullable();
        });

        Schema::table('mnr_mcu_orang', function (Blueprint $t) {
            /* NIK dicari pada setiap "tambah manpower" — menolak NIK yang
               sudah punya dokumen aktif dan menautkan orang yang sama. */
            $t->index('nik');
        });

        $this->hasilRujukan();
        $this->hasilBelumDinilai();
        $this->permitIkutMcu();
        $this->permitPemberiKerja();
    }

    /** Baris "Rujukan" pada master hasil MCU bersama, bila belum ada. */
    private function hasilRujukan(): void
    {
        $ada = DB::table('mnr_hasil_mcu')->whereRaw('LOWER(nama) = ?', ['rujukan'])->exists();

        if ($ada) return;

        $kini = now();

        DB::table('mnr_hasil_mcu')->insert([
            'company_id' => null,
            'kunci'      => 'rujukan',
            'nama'       => 'Rujukan',
            'nilai'      => 20,
            'layak'      => false,
            'urutan'     => 60,
            'aktif'      => true,
            'created_at' => $kini,
            'updated_at' => $kini,
        ]);
    }

    /**
     * Hasil kosong pada MCU yang belum selesai menjadi "Belum Dinilai".
     *
     * Hanya yang belum selesai: hasil kosong pada MCU yang sudah ditutup
     * adalah riwayat, dan riwayat tidak diubah.
     */
    private function hasilBelumDinilai(): void
    {
        $belum = DB::table('mnr_hasil_mcu')->whereNull('company_id')
            ->whereRaw('LOWER(nama) = ?', ['belum dinilai'])->value('id');

        if (! $belum) return;

        $berjalan = DB::table('mnr_mcu')->whereNotIn('status', ['selesai', 'kedaluwarsa'])->pluck('id');

        foreach ($berjalan->chunk(500) as $ids) {
            DB::table('mnr_mcu_orang')->whereIn('mcu_id', $ids)->whereNull('hasil_id')
                ->update(['hasil_id' => $belum]);
        }
    }

    /** Aturan 1 — lihat keterangan kelas. */
    private function permitIkutMcu(): void
    {
        $mcu = DB::table('mnr_mcu_orang')->whereNotNull('berlaku_sampai')->pluck('berlaku_sampai', 'id');

        foreach (DB::table('mnr_permit')->get(['id', 'mcu_orang_id', 'berlaku_sampai', 'sumber_berlaku']) as $p) {
            if (in_array($p->sumber_berlaku, ['mcu', 'manual', 'perpanjangan'], true)) continue;

            $tanggalMcu = $p->mcu_orang_id ? ($mcu[$p->mcu_orang_id] ?? null) : null;

            $ubah = [
                'berlaku_asal' => $p->berlaku_sampai,
                'sumber_asal'  => $p->sumber_berlaku,
            ];

            if ($tanggalMcu) {
                $ubah['berlaku_sampai'] = substr((string) $tanggalMcu, 0, 10);
                $ubah['sumber_berlaku'] = 'mcu';
            } elseif ($p->berlaku_sampai) {
                $ubah['sumber_berlaku'] = 'manual';
            } else {
                $ubah['sumber_berlaku'] = 'mcu';
            }

            DB::table('mnr_permit')->where('id', $p->id)->update($ubah);
        }
    }

    /** Aturan 2 — lihat keterangan kelas. */
    private function permitPemberiKerja(): void
    {
        $induk   = DB::table('companies')->pluck('parent_id', 'id')->all();
        $majikan = DB::table('mnr_pekerja')->pluck('company_id', 'id')->all();

        foreach (DB::table('mnr_permit')->get(['id', 'company_id', 'kontraktor_id', 'iup_id', 'pekerja_id']) as $p) {
            $ubah = [];

            $kerja = $majikan[$p->pekerja_id] ?? null;

            if ($p->kontraktor_id === null && $kerja && ($induk[$kerja] ?? null)
                && (! $p->iup_id || (int) $induk[$kerja] === (int) $p->iup_id)) {
                $ubah['kontraktor_id'] = $kerja;
            }

            $pemilik = $ubah['kontraktor_id'] ?? $p->kontraktor_id;

            if ($pemilik && (int) $pemilik !== (int) $p->company_id) {
                $ubah['company_id'] = $pemilik;
            }

            if ($ubah) DB::table('mnr_permit')->where('id', $p->id)->update($ubah);
        }
    }

    public function down(): void
    {
        /* Tanggal lama dipulihkan dari arsipnya; pemilik baris dan hasil
           "Belum Dinilai" tidak dikembalikan (keduanya tidak menghapus
           informasi apa pun). */
        foreach (DB::table('mnr_permit')->whereNotNull('sumber_asal')->get(['id', 'berlaku_asal', 'sumber_asal']) as $p) {
            DB::table('mnr_permit')->where('id', $p->id)->update([
                'berlaku_sampai' => $p->berlaku_asal,
                'sumber_berlaku' => $p->sumber_asal,
            ]);
        }

        Schema::table('mnr_mcu_orang', fn (Blueprint $t) => $t->dropIndex(['nik']));
        Schema::table('mnr_permit', fn (Blueprint $t) => $t->dropColumn(['berlaku_asal', 'sumber_asal']));
    }
};
