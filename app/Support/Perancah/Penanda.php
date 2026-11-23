<?php

namespace App\Support\Perancah;

use App\Models\Perancah\{Hitung, Inspeksi, Kajian, Tag, Unit};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siklus hidup perancah: hitung & kaji, inspeksi, terbitkan tag.
 *
 * Warna tag diputuskan di SATU tempat ini dan tidak dapat dipilih
 * tangan. Aturannya (FSD ScaffGuard §3), dengan satu pengetatan:
 *
 *   kajian TIDAK LAYAK                        → MERAH
 *   belum pernah diinspeksi                   → MERAH  (pengetatan: tidak
 *                                               ada tag hijau/kuning tanpa
 *                                               inspektur yang menandatangani)
 *   inspeksi gagal ≥ 1 butir kritis           → MERAH
 *   inspeksi bersyarat ATAU kajian bersyarat  → KUNING, 7 hari
 *   kajian layak DAN inspeksi lulus penuh     → HIJAU, 7 hari
 *   tag hijau/kuning lewat 7 hari             → MERAH "kedaluwarsa"
 */
final class Penanda
{
    public const LABEL = [
        'hijau'       => 'LAYAK DIGUNAKAN — SAFE FOR USE',
        'kuning'      => 'LAYAK DIGUNAKAN DENGAN SYARAT — RESTRICTED USE',
        'merah'       => 'TIDAK LAYAK DIGUNAKAN — DO NOT USE',
        'kedaluwarsa' => 'TIDAK LAYAK DIGUNAKAN — KEDALUWARSA, WAJIB INSPEKSI ULANG',
    ];

    /** Hitung ulang BoM & beban lalu buat kajian baru dari spesifikasi unit. */
    public static function kaji(Unit $u, ?string $peninjau = null): Kajian
    {
        $h = Kalkulator::hitung($u->spek());
        $k = Kelayakan::nilai($u->spek(), $h);

        Hitung::withoutGlobalScopes()->updateOrCreate(['unit_id' => $u->id], [
            'company_id' => $u->company_id,
            ...collect($h)->only((new Hitung)->getFillable())->all(),
        ]);

        return Kajian::withoutGlobalScopes()->create([
            'company_id' => $u->company_id, 'unit_id' => $u->id,
            'putusan' => $k['putusan'], 'skor' => $k['skor'], 'perlu_pe' => $k['perlu_pe'],
            'kriteria' => $k['kriteria'], 'rekomendasi' => $k['rekomendasi'],
            'peninjau' => $peninjau ?? 'Kajian otomatis sistem', 'ditinjau_at' => now(),
        ]);
    }

    /**
     * Nilai satu isian daftar periksa.
     *
     * @param  array<string,string>  $isian  kode => lulus|gagal|na
     * @return array{hasil:list<array<string,mixed>>,lulus:int,gagal_kritis:int,gagal_minor:int,putusan:string}
     */
    public static function nilaiPeriksa(array $isian, ?string $syarat = null): array
    {
        $hasil = [];
        $lulus = $kritis = $minor = 0;

        foreach (Katalog::PERIKSA as $b) {
            $s = $isian[$b['kode']] ?? 'na';
            if ($s === 'lulus') $lulus++;
            if ($s === 'gagal') $b['kritis'] ? $kritis++ : $minor++;
            $hasil[] = ['kode' => $b['kode'], 'grup' => $b['grup'], 'label' => $b['label'], 'kritis' => $b['kritis'], 'status' => $s];
        }

        $putusan = $kritis > 0 ? 'bahaya' : (($minor > 0 || filled($syarat)) ? 'hati_hati' : 'aman');

        return ['hasil' => $hasil, 'lulus' => $lulus, 'gagal_kritis' => $kritis, 'gagal_minor' => $minor, 'putusan' => $putusan];
    }

    /** Simpan inspeksi lalu terbitkan tag yang sesuai, dalam satu transaksi. */
    public static function inspeksi(Unit $u, array $data, ?int $userId = null, ?\DateTimeInterface $waktu = null): Tag
    {
        return DB::transaction(function () use ($u, $data, $userId, $waktu) {
            $n = self::nilaiPeriksa($data['periksa'] ?? [], $data['syarat_khusus'] ?? null);
            $pada = $waktu ? \Illuminate\Support\Carbon::instance($waktu) : now();

            $ins = Inspeksi::withoutGlobalScopes()->create([
                'company_id' => $u->company_id, 'unit_id' => $u->id, 'user_id' => $userId,
                'jenis' => $data['jenis'] ?? 'mingguan',
                'inspektur' => $data['inspektur'], 'inspektur_sertifikat' => $data['inspektur_sertifikat'],
                'hasil' => $n['hasil'], 'lulus' => $n['lulus'],
                'gagal_kritis' => $n['gagal_kritis'], 'gagal_minor' => $n['gagal_minor'],
                'putusan' => $n['putusan'],
                'syarat_khusus' => $data['syarat_khusus'] ?? null,
                'tindakan_perbaikan' => $data['tindakan_perbaikan'] ?? null,
                'diperiksa_at' => $pada,
                'berlaku_sampai' => $n['putusan'] === 'bahaya' ? null : $pada->copy()->addDays(Katalog::MASA_BERLAKU_HARI),
            ]);

            return self::terbitkan($u->fresh(), $u->kajianTerakhir()->withoutGlobalScopes()->first(), $ins, $data['inspektur'], $pada);
        });
    }

    /** Putuskan warna dan terbitkan tag baru; tag lama dinonaktifkan. */
    public static function terbitkan(Unit $u, ?Kajian $kaj, ?Inspeksi $ins, ?string $oleh = null, ?\DateTimeInterface $waktu = null): Tag
    {
        $pada = $waktu ? \Illuminate\Support\Carbon::instance($waktu) : now();
        $berlaku = $pada->copy()->addDays(Katalog::MASA_BERLAKU_HARI);

        [$warna, $label, $syarat, $alasan, $sampai] = match (true) {
            $kaj?->putusan === 'tidak_layak' => ['merah', self::LABEL['merah'], null,
                implode(' ', $kaj->rekomendasi ?: ['Tidak memenuhi kajian kelayakan teknis.']), null],
            $ins === null => ['merah', self::LABEL['merah'], null,
                'Belum diinspeksi inspektur kompeten. Perancah tidak layak digunakan sebelum inspeksi awal.', null],
            $ins->gagal_kritis > 0 => ['merah', self::LABEL['merah'], null,
                'Gagal '.$ins->gagal_kritis.' butir kritis inspeksi. '.($ins->tindakan_perbaikan ?: 'Perbaiki lalu inspeksi ulang.'), null],
            $ins->putusan === 'hati_hati' || $kaj?->putusan === 'bersyarat' => ['kuning', self::LABEL['kuning'],
                trim(($ins->syarat_khusus ?: '').' '.($kaj?->putusan === 'bersyarat' ? implode(' ', $kaj->rekomendasi ?? []) : ''))
                    ?: 'Wajib full body harness 100% tie-off dan pengawasan langsung.', null, $berlaku],
            default => ['hijau', self::LABEL['hijau'], null, null, $berlaku],
        };

        return self::catat($u, $warna, $label, $syarat, $alasan, $sampai, $ins, $oleh ?? $ins?->inspektur ?? 'Penandaan otomatis', $pada);
    }

    /** Tag hijau/kuning yang lewat masa berlakunya diganti merah. */
    public static function kedaluwarsakan(Unit $u): Tag
    {
        return self::catat($u, 'merah', self::LABEL['kedaluwarsa'], null,
            'Masa berlaku inspeksi '.Katalog::MASA_BERLAKU_HARI.' hari terlewati sejak '
            .optional($u->inspeksi_berikut)->translatedFormat('d M Y H:i').'. Wajib inspeksi ulang sebelum dipakai.',
            null, null, 'Penjadwal sistem', now());
    }

    /** Perancah dibongkar: tag aktif dicabut, unit keluar dari peta aktif. */
    public static function bongkar(Unit $u): void
    {
        DB::transaction(function () use ($u) {
            Tag::withoutGlobalScopes()->where('unit_id', $u->id)->where('aktif', true)->update(['aktif' => false]);
            $u->update(['status_tag' => 'dibongkar', 'inspeksi_berikut' => null, 'dibongkar_at' => now()]);
        });
    }

    private static function catat(Unit $u, string $warna, string $label, ?string $syarat, ?string $alasan,
                                  $sampai, ?Inspeksi $ins, string $oleh, $pada): Tag
    {
        Tag::withoutGlobalScopes()->where('unit_id', $u->id)->where('aktif', true)->update(['aktif' => false]);

        $urut = (int) Tag::withoutGlobalScopes()->max('id') + 1;
        $awal = ['hijau' => 'GRN', 'kuning' => 'YLW', 'merah' => 'RED'][$warna];

        $tag = Tag::withoutGlobalScopes()->create([
            'company_id' => $u->company_id, 'unit_id' => $u->id, 'inspeksi_id' => $ins?->id,
            'kode' => sprintf('STG-%s-%s-%05d', $awal, $pada->format('Y'), $urut),
            'token' => Str::lower(Str::random(32)),
            'warna' => $warna, 'label' => $label, 'swl_kg_m2' => $u->swl(),
            'syarat_khusus' => $syarat ?: null, 'alasan_tolak' => $alasan,
            'terbit_oleh' => $oleh, 'terbit_at' => $pada, 'kedaluwarsa_at' => $sampai, 'aktif' => true,
        ]);

        $u->forceFill(['status_tag' => $warna, 'kode_tag' => $tag->kode, 'inspeksi_berikut' => $sampai])->save();

        return $tag;
    }
}
