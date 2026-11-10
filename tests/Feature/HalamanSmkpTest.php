<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Halaman dan lembar cetak audit SMKP (porting Aurorasys, bagian 2).
 *
 * Bagian SMKP dari HalamanTest Aurorasys — setiap halaman terbuka dan
 * merender komponen yang benar — ditambah penjaga konvensi EQOHSEE yang
 * tidak menimbulkan galat bila dilanggar: berkas komponennya ada, lembar
 * cetak memakai BlankLayout, halaman aplikasi berada di dalam cakupan
 * gaya `.smkp-ui`, dan kop kerangka ditolak karena halaman SMKP membawa
 * kepalanya sendiri.
 */
class HalamanSmkpTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    /** @return array<string, string> url => komponen */
    private function halaman(SmkpAudit $a): array
    {
        return [
            '/smkp' => 'Smkp/Index', '/smkp/acuan' => 'Smkp/Acuan', '/smkp/buat' => 'Smkp/Form',
            '/smkp/tindak-lanjut' => 'Smkp/TindakLanjut', '/smkp/tindak-lanjut?status=Closed&q=I.1' => 'Smkp/TindakLanjut',
            "/smkp/{$a->id}" => 'Smkp/Show', "/smkp/{$a->id}/ubah" => 'Smkp/Form', "/smkp/{$a->id}/tahap-1" => 'Smkp/Tahap1',
            "/smkp/{$a->id}/rencana" => 'Smkp/Rencana', "/smkp/{$a->id}/rapat" => 'Smkp/Rapat',
            "/smkp/{$a->id}/elemen/IV" => 'Smkp/Nilai', "/smkp/{$a->id}/bukti" => 'Smkp/Bukti',
            "/smkp/{$a->id}/temuan" => 'Smkp/Temuan', "/smkp/{$a->id}/formulir" => 'Smkp/Formulir',
            "/smkp/{$a->id}/laporan/isian" => 'Smkp/Laporan',

            "/smkp/{$a->id}/berita-acara" => 'Print/Smkp/BeritaAcara',
            "/smkp/{$a->id}/rencana/cetak" => 'Print/Smkp/RencanaAudit',
            "/smkp/{$a->id}/daftar-hadir/pembukaan" => 'Print/Smkp/DaftarHadir',
            "/smkp/{$a->id}/laporan" => 'Print/Smkp/LaporanAudit',
            "/smkp/{$a->id}/formulir/penilaian/cetak" => 'Print/Smkp/FormulirPenilaian',
            "/smkp/{$a->id}/formulir/kesesuaian/cetak" => 'Print/Smkp/FormulirKesesuaian',
            "/smkp/{$a->id}/formulir/ketidaksesuaian/cetak" => 'Print/Smkp/FormulirKetidaksesuaian',
            "/smkp/{$a->id}/formulir/rekapitulasi/cetak" => 'Print/Smkp/FormulirRekapitulasi',
            "/smkp/{$a->id}/formulir/rekomendasi/cetak" => 'Print/Smkp/FormulirRekomendasi',
            "/smkp/{$a->id}/formulir/tindak-lanjut/cetak" => 'Print/Smkp/FormulirTindakLanjut',
        ];
    }

    public function test_seluruh_halaman_smkp_terbuka_dan_komponennya_ada(): void
    {
        $this->siapkanGrup();
        $adm = $this->admin();
        $a = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2,
            'hasil' => ['I.1' => ['v' => 1]],
        ]);

        foreach ($this->halaman($a) as $url => $komponen) {
            $this->assertFileExists(resource_path("js/Pages/{$komponen}.vue"), "Komponen {$komponen} tidak ada.");

            // Prop bersama `pengguna` tidak boleh tertimpa prop halaman.
            $this->actingAs($adm)->get($url)->assertOk()->assertInertia(fn (Inertia $p) => $p
                ->component($komponen)
                ->where('pengguna.id', $adm->id));
        }
    }

    public function test_halaman_aplikasi_smkp_menolak_kop_kerangka(): void
    {
        $this->siapkanGrup();
        $adm = $this->admin();

        // KepalaHalaman/KepalaAudit membawa judul, jejak, dan tombol; kop
        // kerangka di atasnya mencetak judul dua kali.
        $this->actingAs($adm)->get('/smkp')->assertInertia(fn (Inertia $p) => $p->where('kop', false));
        $this->actingAs($adm)->get('/smkp/acuan')->assertInertia(fn (Inertia $p) => $p->where('kop', false));

        // Halaman modul lain tidak ikut.
        $this->actingAs($adm)->get('/dashboard')->assertInertia(fn (Inertia $p) => $p->where('kop', null));
    }

    public function test_lembar_cetak_smkp_memakai_blank_layout_dan_bingkai_cetak(): void
    {
        foreach (glob(resource_path('js/Pages/Print/Smkp/*.vue')) as $berkas) {
            $isi = file_get_contents($berkas);
            $nama = basename($berkas);

            $this->assertStringContainsString('defineOptions({ layout: BlankLayout })', $isi,
                "{$nama}: lembar cetak harus memakai BlankLayout, bukan kerangka aplikasi.");
            $this->assertStringContainsString('<CetakLayout', $isi,
                "{$nama}: lembar cetak SMKP dibungkus CetakLayout (cakupan gaya .smkp-cetak dan @page).");
            $this->assertDoesNotMatchRegularExpression('/class="[^"]*\blembar\b/', $isi,
                "{$nama}: lembar SMKP memakai .smkp-lembar; .lembar milik dokumen terkendali EQOHSEE lain.");
        }
    }

    public function test_halaman_aplikasi_smkp_berada_dalam_cakupan_gaya(): void
    {
        foreach (glob(resource_path('js/Pages/Smkp/*.vue')) as $berkas) {
            $isi = file_get_contents($berkas);

            // Kelas komponen Aurorasys (kartu, btn-*, input, label, …)
            // hanya berlaku di dalam .smkp-ui; tanpa pembungkus itu
            // halamannya tampil tanpa gaya sama sekali — tanpa galat.
            $this->assertMatchesRegularExpression('/<template>\s*<div class="smkp-ui">/', $isi,
                basename($berkas).': isi template harus dibungkus <div class="smkp-ui">.');
        }
    }

    public function test_tidak_ada_lagi_halaman_smkp_lama(): void
    {
        foreach (['Smkp/Halaman', 'Smkp/Penilaian', 'Smkp/Sampel', 'Smkp/Ofi', 'Smkp/Dasbor', 'Print/Smkp',
                  'Print/SmkpKriteria', 'Print/SmkpRekapNc', 'Print/SmkpRespon', 'Print/SmkpRtl',
                  'Print/SmkpNcTindak', 'Print/SmkpOfi'] as $lama) {
            $this->assertFileDoesNotExist(resource_path("js/Pages/{$lama}.vue"));
        }
    }
}
