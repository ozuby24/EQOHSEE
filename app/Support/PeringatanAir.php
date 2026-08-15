<?php

namespace App\Support;

use App\Models\WaterSump;
use Illuminate\Support\Collection;

/**
 * Peringatan pengelolaan air dan penirisan.
 *
 * Sebentuk dengan tiga modul sebelumnya. Sifat bahayanya berbeda lagi:
 * produksi dan konservasi menurun perlahan, kerusakan alat datang
 * mendadak dengan tanda yang mendahului — sedangkan luapan kolam datang
 * dari luar dan sudah dapat dilihat sebelum terjadi, sebab hujannya
 * diramalkan. Karena itu peringatan di sini dinyatakan sebagai daya
 * tampung tersisa dalam milimeter hujan: angka yang dapat langsung
 * dibandingkan dengan ramalan cuaca, bukan meter kubik yang menuntut
 * hitungan tambahan di kepala pembacanya.
 */
final class PeringatanAir
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /* Ambang. Bernama supaya terlihat saat ditinjau. */
    private const TERISI_KRITIS      = 80.0;  // persen kapasitas
    private const HUJAN_RENCANA_MM   = 50.0;  // kejadian hujan yang dijadikan acuan
    private const HUJAN_AMAN_MIN_MM  = 25.0;  // daya tampung minimum yang dianggap cukup

    /* Baku mutu air limbah tambang, dipakai sebagai ambang bawaan.
       Dinyatakan di sini sebagai angka acuan yang dapat disesuaikan;
       izin tiap site dapat menetapkan nilai yang lebih ketat. */
    private const PH_MIN  = 6.0;
    private const PH_MAKS = 9.0;
    private const TSS_MAKS_MGL = 400.0;
    private const FE_MAKS_MGL  = 7.0;
    private const MN_MAKS_MGL  = 4.0;

    /**
     * @param Collection<int,WaterSump> $sumps
     * @param Collection<int,\App\Models\WaterLog> $logs catatan pada periode
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(Collection $sumps, Collection $logs, array $kelengkapan): array
    {
        $p = [];

        /* ---------- mutu data ---------- */

        if ($sumps->isEmpty()) {
            return [[
                'kode'  => 'tanpa-kolam',
                'level' => self::TINGGI,
                'judul' => 'Belum ada kolam terdaftar',
                'ket'   => 'Perkiraan luapan tidak dapat dihitung tanpa kapasitas dan luas daerah tangkapan.',
                'saran' => 'Daftarkan sump dan kolam pengendap beserta luas tangkapan serta koefisien limpasannya.',
            ]];
        }

        if (($kelengkapan['belumDilaporkan'] ?? 0) > 0) {
            $n = $kelengkapan['belumDilaporkan'];
            $p[] = [
                'kode'  => 'catatan-harian-bolong',
                'level' => $n > 3 ? self::TINGGI : self::SEDANG,
                'judul' => "{$n} catatan harian belum masuk",
                'ket'   => 'Level dan curah hujan yang tidak tercatat membuat perkiraan luapan memakai angka lama.',
                'saran' => 'Tagih pembacaan level dan penakar hujan kepada pengawas penirisan.',
            ];
        }

        if (($kelengkapan['menungguTinjauan'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'catatan-menunggu-tinjauan',
                'level' => self::SEDANG,
                'judul' => "{$kelengkapan['menungguTinjauan']} catatan menunggu tinjauan",
                'ket'   => 'Sudah diajukan tetapi belum disetujui, sehingga belum masuk laporan.',
                'saran' => 'Minta Kepala Teknik Tambang meninjau catatan yang tertahan.',
            ];
        }

        /* ---------- daya tampung ---------- */

        foreach ($sumps as $s) {
            $n = $s->neraca();
            $tampung = $n->hujanTertampungMm();

            if ($n->terisiPersen() >= self::TERISI_KRITIS) {
                $p[] = [
                    'kode'  => 'kolam-hampir-penuh-'.$s->id,
                    'level' => self::TINGGI,
                    'judul' => "{$s->kode} terisi ".number_format($n->terisiPersen(), 0).' %',
                    // Dinyatakan dalam milimeter, bukan meter kubik:
                    // inilah angka yang dapat langsung dibandingkan
                    // dengan ramalan cuaca esok hari.
                    'ket'   => 'Sisa daya tampung setara hujan '.number_format($tampung, 0).' mm.',
                    'saran' => 'Turunkan level sebelum hujan berikutnya; periksa kesiapan seluruh pompa pada kolam ini.',
                ];
            } elseif ($tampung < self::HUJAN_AMAN_MIN_MM) {
                $p[] = [
                    'kode'  => 'daya-tampung-tipis-'.$s->id,
                    'level' => self::SEDANG,
                    'judul' => "{$s->kode} hanya sanggup menahan ".number_format($tampung, 0).' mm hujan',
                    'ket'   => 'Di bawah '.self::HUJAN_AMAN_MIN_MM.' mm, satu kejadian hujan biasa sudah cukup melampauinya.',
                    'saran' => 'Jadwalkan pemompaan menjelang musim hujan, atau tambah kapasitas tampung.',
                ];
            }

            // Pompa terpasang cukup tetapi separuhnya rusak menghadapi
            // risiko yang sama dengan pompa yang memang kurang, dan hanya
            // satu dari keduanya yang diperbaiki dengan membeli pompa.
            if ($s->pompaRusak() > 0) {
                $p[] = [
                    'kode'  => 'pompa-rusak-'.$s->id,
                    'level' => self::TINGGI,
                    'judul' => "{$s->pompaRusak()} pompa {$s->kode} rusak",
                    'ket'   => 'Kapasitas siap '.number_format($s->kapasitasPompaSiap(), 0)
                               .' dari '.number_format($s->kapasitasPompaTerpasang(), 0).' m³/jam terpasang.',
                    'saran' => 'Buka perintah kerja pada modul Pemeliharaan; pompa penirisan termasuk alat berkritikalitas tertinggi.',
                ];
            }

            if ($s->kapasitasPompaSiap() <= 0 && $s->kapasitas_m3 > 0) {
                $p[] = [
                    'kode'  => 'tanpa-pompa-siap-'.$s->id,
                    'level' => self::TINGGI,
                    'judul' => "{$s->kode} tidak punya pompa siap jalan",
                    'ket'   => 'Kolam ini hanya mengandalkan daya tampungnya sendiri.',
                    'saran' => 'Tetapkan pompa yang melayani kolam ini, atau perbaiki yang ada.',
                ];
            }

            $sisaBersih = $s->sisaHariBersih();
            if ($sisaBersih !== null && $sisaBersih < 0) {
                $p[] = [
                    'kode'  => 'pembersihan-terlewat-'.$s->id,
                    'level' => self::SEDANG,
                    'judul' => "{$s->kode} terlewat jadwal pembersihan ".abs($sisaBersih).' hari',
                    'ket'   => 'Endapan yang menumpuk mengurangi kapasitas tampung nyata di bawah angka nominalnya.',
                    'saran' => 'Jadwalkan pengerukan; kapasitas pada halaman ini memakai angka nominal, bukan yang tersisa.',
                ];
            }
        }

        /* ---------- kualitas air ---------- */

        foreach (self::pelanggaranMutu($logs) as $q) $p[] = $q;

        return $p;
    }

    /** @param Collection<int,\App\Models\WaterLog> $logs */
    private static function pelanggaranMutu(Collection $logs): array
    {
        $sampel = $logs->filter(fn ($l) => $l->adaSampelAir());

        if ($sampel->isEmpty()) return [];

        $p = [];

        $lewat = [
            'ph-di-luar-rentang' => [
                'n' => $sampel->filter(fn ($l) => $l->ph !== null && ($l->ph < self::PH_MIN || $l->ph > self::PH_MAKS))->count(),
                'judul' => 'pH di luar rentang '.self::PH_MIN.'–'.self::PH_MAKS,
                'saran' => 'Periksa dosis kapur dan waktu tinggal pada kolam pengendap.',
            ],
            'tss-melampaui' => [
                'n' => $sampel->filter(fn ($l) => $l->tss_mgl !== null && $l->tss_mgl > self::TSS_MAKS_MGL)->count(),
                'judul' => 'TSS melampaui '.self::TSS_MAKS_MGL.' mg/L',
                'saran' => 'Endapan mungkin sudah penuh atau waktu tinggal terlalu singkat; periksa jadwal pengerukan.',
            ],
            'fe-melampaui' => [
                'n' => $sampel->filter(fn ($l) => $l->fe_mgl !== null && $l->fe_mgl > self::FE_MAKS_MGL)->count(),
                'judul' => 'Besi terlarut melampaui '.self::FE_MAKS_MGL.' mg/L',
                'saran' => 'Tinjau pengolahan air asam tambang pada kolam sumbernya.',
            ],
            'mn-melampaui' => [
                'n' => $sampel->filter(fn ($l) => $l->mn_mgl !== null && $l->mn_mgl > self::MN_MAKS_MGL)->count(),
                'judul' => 'Mangan melampaui '.self::MN_MAKS_MGL.' mg/L',
                'saran' => 'Mangan menuntut pH lebih tinggi daripada besi untuk mengendap; tinjau tahapan pengolahannya.',
            ],
        ];

        foreach ($lewat as $kode => $d) {
            if ($d['n'] > 0) {
                $p[] = [
                    'kode'  => $kode,
                    'level' => self::TINGGI,
                    'judul' => $d['judul'],
                    'ket'   => "{$d['n']} sampel pada periode ini melampaui ambang.",
                    'saran' => $d['saran'],
                ];
            }
        }

        return $p;
    }
}
