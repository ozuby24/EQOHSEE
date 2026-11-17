<?php

namespace App\Http\Controllers;

use App\Models\Miners\{Induksi, Mcu, Permit, Simper};
use App\Support\Miners\{Jalur, RincianDokumen};
use Illuminate\Http\Request;

/**
 * Pintu alur bersama Miners — "Kirim" (Sending) dan "Re-Submit" bagi MCU,
 * induksi, Mine Permit, dan SIMPER.
 *
 * Seluruh layar dan aturan tiap dokumen sudah pindah ke controllernya
 * sendiri di App\Http\Controllers\Miners (McuController, InduksiController,
 * PermitController, SimperController, SimperLanjutanController), mengikuti
 * layar Safe Track masing-masing. Yang tersisa di sini hanya dua tindakan
 * yang bentuknya sama persis bagi keempat jenis.
 */
class MinersDokumenController extends Controller
{
    /**
     * Kirim berkas ke penyetuju pertama — tombol "Kirim" / "Sending".
     *
     * Jenisnya dibaca dari DEFAULT RUTE, bukan dari tanda tangan: Laravel
     * mengisi parameter controller menurut urutan, bukan menurut nama.
     */
    public function kirim(Request $r, string $id)
    {
        $jenis   = (string) ($r->route()?->defaults['jenis'] ?? '');
        $dokumen = $this->dokumen($jenis, (int) $id);

        if ($alasan = Jalur::kirim($dokumen, $r->user())) {
            return back()->withErrors(['kirim' => $alasan]);
        }

        return $this->balas((RincianDokumen::LABEL[$jenis] ?? 'Berkas').' terkirim ke penyetuju.');
    }

    /** Ajukan ulang pengajuan yang dikembalikan atau ditolak. */
    public function ajukanUlang(Request $r, string $id)
    {
        $jenis   = (string) ($r->route()?->defaults['jenis'] ?? '');
        $dokumen = $this->dokumen($jenis, (int) $id);

        if ($alasan = Jalur::ajukanUlang($dokumen, $r->user())) {
            return back()->withErrors(['ajukanUlang' => $alasan]);
        }

        return $this->balas('Pengajuan dikirim ulang; alurnya diulang dari awal.');
    }

    /**
     * Pesan sukses beserta peringatan pemberitahuan, bila ada — dua pesan,
     * seperti st_sukses dan st_gagal Safe Track.
     */
    private function balas(string $sukses)
    {
        $balas = back()->with('sukses', $sukses);

        return ($pesan = Jalur::pesanSurel()) ? $balas->with('galat', $pesan) : $balas;
    }

    /**
     * Satu dokumen Miners menurut jenisnya, di bawah batas perusahaannya:
     * dokumen perusahaan lain memang tidak dapat ditemukan (404, bukan 403).
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    private function dokumen(string $jenis, int $id)
    {
        $kelas = [
            'mcu'     => Mcu::class,
            'induksi' => Induksi::class,
            'permit'  => Permit::class,
            'simper'  => Simper::class,
        ][$jenis] ?? abort(404);

        return $kelas::with('alur')->findOrFail($id);
    }
}
