<?php

namespace App\Support\Angkat;

use App\Models\Angkat\Alat;

/**
 * Isian kolom rencana dari masukan + simulasi. Dipakai controller dan
 * data contoh supaya rencana contoh dihitung dengan rumus yang sama
 * dengan rencana yang dibuat orang.
 */
final class Rencanakan
{
    public const ORANG = ['operator_nama', 'operator_sio', 'rigger_nama', 'rigger_sertifikat', 'signalman_nama', 'pengawas_nama'];

    public static function kolom(array $data, ?Alat $alat): array
    {
        $m = array_intersect_key($data, Simulasi::BAWAAN);
        if ($alat) $m['jenis_alat'] = $alat->jenis;
        $h = Simulasi::hitung($m, $alat?->spek());

        return [
            'alat_id' => $alat?->id, 'judul' => $data['judul'], 'kategori_beban' => $h['masukan']['kategori_beban'],
            'klasifikasi' => $h['klasifikasi'], 'jenis_alat' => $h['masukan']['jenis_alat'],
            'lokasi' => $data['lokasi'], 'area' => $data['area'] ?? null, 'jadwal_at' => $data['jadwal_at'] ?? null,
            'masukan' => $h['masukan'], 'hasil' => $h, 'gear' => array_values(array_map('intval', $data['gear'] ?? [])),
            'berat_bersih_t' => $h['berat_bersih_t'], 'berat_kotor_t' => $h['berat_kotor_t'], 'radius_m' => $h['radius_m'],
            'boom_m' => $h['boom_m'], 'kapasitas_t' => $h['kapasitas_t'], 'utilisasi_pct' => $h['utilisasi_pct'],
            'sudut_sling' => $h['rigging']['sudut_efektif'], 'tegangan_maks_t' => $h['rigging']['tegangan_maks_t'],
            'tekanan_tanah' => $h['tekanan_tanah'], 'putusan' => $h['putusan'], 'skor' => $h['skor'],
            'catatan' => $data['catatan'] ?? null,
        ] + array_intersect_key($data, array_flip(self::ORANG));
    }
}
