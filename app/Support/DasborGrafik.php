<?php

namespace App\Support;

use App\Models\{
    AngkutMuatan, BiayaRealisasi, Document, EnergyFuelLog, GudangBarang,
    HazardReport, Inspection, KoObject, LingkunganPantau, MineOperationalRecord,
    Paspor, PasporMcu, PasporSertifikat, SmkpFinding, WaterLog, WorkOrder
};
use Illuminate\Support\Carbon;

/**
 * Deret angka untuk grafik dasbor.
 *
 * DIPISAHKAN DARI CONTROLLER-NYA karena jumlahnya: dua puluh grafik
 * berarti dua puluh kueri, dan controller yang memuat seluruhnya
 * berhenti dapat dibaca jauh sebelum berhenti bekerja.
 *
 * TIGA ATURAN YANG BERLAKU UNTUK SELURUHNYA:
 *
 *  1. Hari kosong dikembalikan sebagai NOL, bukan dilewati. Grafik yang
 *     melompati hari tanpa data menyambung dua titik berjauhan menjadi
 *     satu garis landai, dan berhentinya kegiatan terbaca sebagai
 *     penurunan bertahap.
 *
 *  2. Penyebut nol memulangkan NULL, bukan nol dan bukan seratus.
 *     "100% patuh" dari nol data adalah angka yang paling meyakinkan
 *     bentuknya dan paling salah artinya.
 *
 *  3. Kategori yang kosong tetap disebut. Donat yang menyembunyikan
 *     irisan bernilai nol membuat orang tidak dapat membedakan
 *     "tidak ada yang unfit" dari "kolom hasilnya belum diisi".
 */
final class DasborGrafik
{
    /** Pilihan rentang hari yang boleh diminta dari layar. */
    public const RENTANG = [7, 30, 90, 365];

    public const RENTANG_BAWAAN = 30;

    /* ═══════════ keselamatan ═══════════ */

    /**
     * Laporan bahaya per bulan: masuk dan yang sudah ditutup.
     *
     * Keduanya berdampingan karena satu tanpa yang lain menyesatkan.
     * Laporan masuk yang naik dapat berarti budaya lapor membaik ATAU
     * keadaan memburuk; yang membedakannya berapa yang ditutup.
     */
    public static function hazardBulanan(Carbon $kini, int $bulan = 6): array
    {
        $label = []; $masuk = []; $tutup = [];

        /* Deret bulannya dibangun DeretBulan, bukan `subMonths($i)` dari
           tanggal hari ini: yang kedua meluap pada tanggal 29–31,
           sehingga satu bulan dihitung dua kali dan bulan lain tidak
           pernah muncul. Grafiknya tetap enam batang — dua di antaranya
           berlabel sama — dan justru karena itu ia tidak terlihat salah. */
        foreach (DeretBulan::mundur($kini, $bulan) as $b) {

            $dalam = fn ($q) => $q->whereYear('tanggal', $b->year)->whereMonth('tanggal', $b->month);

            $label[] = $b->translatedFormat('M y');
            $masuk[] = HazardReport::query()->tap($dalam)->count();
            $tutup[] = HazardReport::query()->tap($dalam)->where('status', 'Closed')->count();
        }

        return ['label' => $label, 'masuk' => $masuk, 'tutup' => $tutup];
    }

    /** Laporan bahaya menurut tingkat risikonya. */
    public static function hazardRisiko(): array
    {
        return self::hitungKolom(HazardReport::query(), 'risiko');
    }

    /** Laporan bahaya menurut kategorinya. */
    public static function hazardKategori(): array
    {
        return self::hitungKolom(HazardReport::query(), 'kategori', 6);
    }

    public static function inspeksiStatus(): array
    {
        return self::hitungKolom(Inspection::query(), 'status');
    }

    public static function temuanStatus(): array
    {
        return self::hitungKolom(SmkpFinding::query(), 'status');
    }

    /* ═══════════ produksi ═══════════ */

    /**
     * Produksi harian: batubara, tanah penutup, dan nisbah kupasnya.
     *
     * Nisbah dihitung per hari dari kedua angkanya, bukan dari
     * totalnya: nisbah rata-rata sebulan menyembunyikan hari-hari
     * dengan pengupasan berat yang justru menentukan biayanya.
     */
    public static function produksi(Carbon $kini, int $hari): array
    {
        $mulai = $kini->copy()->subDays($hari - 1)->startOfDay();

        $harian = MineOperationalRecord::query()
            ->whereDate('tanggal', '>=', $mulai)
            ->selectRaw('date(tanggal) as hari, sum(produksi_ton) as ton, sum(overburden_bcm) as ob,'
                .' sum(jam_operasi) as jam, sum(jam_delay) as delay')
            ->groupBy('hari')->get()->keyBy('hari');

        $label = []; $ton = []; $ob = []; $nisbah = []; $jam = []; $delay = [];

        foreach (self::hari($mulai, $kini) as $tgl) {
            $b = $harian[$tgl->toDateString()] ?? null;

            $label[]  = $tgl->format('d/m');
            $ton[]    = $t = (float) ($b->ton ?? 0);
            $ob[]     = $o = (float) ($b->ob ?? 0);
            $jam[]    = (float) ($b->jam ?? 0);
            $delay[]  = (float) ($b->delay ?? 0);

            /* Nisbah tanpa produksi tidak terdefinisi — null, bukan nol.
               Nol berarti "tidak ada pengupasan", dan itu keadaan yang
               berbeda sama sekali dari "tidak ada batubara". */
            $nisbah[] = $t > 0 ? round($o / $t, 2) : null;
        }

        return compact('label', 'ton', 'ob', 'nisbah', 'jam', 'delay');
    }

    /** Tonase tertimbang per hari. */
    public static function angkutan(Carbon $kini, int $hari): array
    {
        $mulai = $kini->copy()->subDays($hari - 1)->startOfDay();

        $harian = AngkutMuatan::query()
            ->whereDate('waktu_timbang', '>=', $mulai)
            ->selectRaw('date(waktu_timbang) as hari, sum(muatan_ton) as ton, count(*) as rit')
            ->groupBy('hari')->get()->keyBy('hari');

        $label = []; $ton = []; $rit = [];

        foreach (self::hari($mulai, $kini) as $tgl) {
            $b = $harian[$tgl->toDateString()] ?? null;

            $label[] = $tgl->format('d/m');
            $ton[]   = (float) ($b->ton ?? 0);
            $rit[]   = (int) ($b->rit ?? 0);
        }

        return compact('label', 'ton', 'rit');
    }

    /* ═══════════ air dan lingkungan ═══════════ */

    public static function air(Carbon $kini, int $hari): array
    {
        $mulai = $kini->copy()->subDays($hari - 1)->startOfDay();

        $harian = WaterLog::query()
            ->whereDate('tanggal', '>=', $mulai)
            ->selectRaw('date(tanggal) as hari, sum(curah_hujan_mm) as hujan,'
                .' sum(debit_keluar_m3) as pompa')
            ->groupBy('hari')->get()->keyBy('hari');

        $label = []; $hujan = []; $pompa = [];

        foreach (self::hari($mulai, $kini) as $tgl) {
            $b = $harian[$tgl->toDateString()] ?? null;

            $label[] = $tgl->format('d/m');
            $hujan[] = (float) ($b->hujan ?? 0);
            $pompa[] = (float) ($b->pompa ?? 0);
        }

        return compact('label', 'hujan', 'pompa');
    }

    /**
     * Hasil pantau lingkungan: sesuai baku mutu atau melampaui.
     *
     * Dihitung terhadap ambang yang berlaku SEKARANG, bukan disimpan
     * saat pencatatan: baku mutu berubah, dan pelanggaran yang dihitung
     * sekali akan menyebut angka lama selamanya.
     */
    public static function lingkungan(): array
    {
        $pantau = LingkunganPantau::with('parameter')->get();
        $langgar = $pantau->filter(fn (LingkunganPantau $p) => $p->melanggar())->count();

        return [
            ['label' => 'Sesuai baku mutu', 'nilai' => $pantau->count() - $langgar],
            ['label' => 'Melampaui',        'nilai' => $langgar],
        ];
    }

    /* ═══════════ aset dan biaya ═══════════ */

    public static function koStatus(): array
    {
        return self::hitungKolom(KoObject::query(), 'status_operasi');
    }

    public static function workOrderStatus(): array
    {
        return self::hitungKolom(WorkOrder::query(), 'status');
    }

    public static function gudangKategori(): array
    {
        return self::hitungKolom(GudangBarang::query(), 'kategori');
    }

    /** Realisasi biaya per bulan pada tahun berjalan. */
    public static function biayaBulanan(Carbon $kini): array
    {
        $per = BiayaRealisasi::query()
            ->where('tahun', $kini->year)
            ->selectRaw('bulan, sum(nilai_rp) as rp')
            ->groupBy('bulan')->pluck('rp', 'bulan');

        $label = []; $rp = [];

        for ($b = 1; $b <= 12; $b++) {
            $label[] = Carbon::create($kini->year, $b, 1)->translatedFormat('M');
            $rp[]    = (float) ($per[$b] ?? 0);
        }

        return ['label' => $label, 'rp' => $rp];
    }

    /* ═══════════ energi ═══════════ */

    public static function energi(Carbon $kini, int $hari): array
    {
        $mulai = $kini->copy()->subDays($hari - 1)->startOfDay();

        $harian = EnergyFuelLog::query()
            ->whereDate('tanggal', '>=', $mulai)
            ->selectRaw('date(tanggal) as hari, sum(liter) as liter, sum(idle_jam) as idle')
            ->groupBy('hari')->get()->keyBy('hari');

        $label = []; $liter = []; $idle = [];

        foreach (self::hari($mulai, $kini) as $tgl) {
            $b = $harian[$tgl->toDateString()] ?? null;

            $label[] = $tgl->format('d/m');
            $liter[] = (float) ($b->liter ?? 0);
            $idle[]  = (float) ($b->idle ?? 0);
        }

        return compact('label', 'liter', 'idle');
    }

    /* ═══════════ orang ═══════════ */

    /**
     * Sebaran hasil MCU terakhir tiap orang.
     *
     * Yang TERAKHIR saja: memasukkan seluruh riwayat membuat orang yang
     * pernah "unfit" lalu dinyatakan pulih tetap terhitung unfit
     * selamanya.
     */
    public static function mcuHasil(): array
    {
        $per = [];

        foreach (Paspor::with('mcu')->get() as $p) {
            $m = $p->mcuTerakhir();
            $kunci = $m?->hasil ?: 'Belum ada MCU';

            $per[$kunci] = ($per[$kunci] ?? 0) + 1;
        }

        arsort($per);

        return collect($per)->map(fn ($n, $k) => ['label' => $k, 'nilai' => $n])->values()->all();
    }

    /**
     * Sertifikat kompetensi yang habis dalam enam bulan ke depan.
     *
     * Dikelompokkan per bulan supaya terlihat bulan mana yang menumpuk —
     * itu yang menentukan kapan pelatihan ulang harus dijadwalkan, dan
     * jadwal pelatihan disusun berbulan-bulan sebelumnya.
     */
    public static function sertifikatJatuhTempo(Carbon $kini, int $bulan = 6): array
    {
        $label = []; $nilai = [];

        foreach (DeretBulan::maju($kini, $bulan) as $b) {

            $label[] = $b->translatedFormat('M y');
            $nilai[] = PasporSertifikat::whereNotNull('tgl_expired')
                ->whereYear('tgl_expired', $b->year)
                ->whereMonth('tgl_expired', $b->month)
                ->count();
        }

        return ['label' => $label, 'nilai' => $nilai];
    }

    public static function dokumenStatus(): array
    {
        return self::hitungKolom(Document::query(), 'status');
    }

    /* ═══════════ pembantu ═══════════ */

    /**
     * Jumlah baris per nilai sebuah kolom, terurut dari yang terbanyak.
     *
     * Nilai kosong disebut apa adanya sebagai "tidak diisi", bukan
     * dibuang: kolom yang sering kosong adalah temuan tersendiri, dan
     * membuangnya membuat grafiknya tampak rapi justru saat datanya
     * paling buruk.
     *
     * @return list<array{label:string,nilai:int}>
     */
    private static function hitungKolom($kueri, string $kolom, ?int $batas = null): array
    {
        $per = $kueri->selectRaw("{$kolom} as k, count(*) as n")
            ->groupBy($kolom)->pluck('n', 'k')->all();

        $keluar = [];
        foreach ($per as $k => $n) {
            $keluar[] = ['label' => (string) ($k ?: 'Tidak diisi'), 'nilai' => (int) $n];
        }

        usort($keluar, fn ($a, $b) => $b['nilai'] <=> $a['nilai']);

        return $batas ? array_slice($keluar, 0, $batas) : $keluar;
    }

    /**
     * Tiap hari dalam rentang, tanpa lubang.
     *
     * @return list<Carbon>
     */
    private static function hari(Carbon $mulai, Carbon $sampai): array
    {
        $keluar = [];

        for ($h = $mulai->copy(); $h->lte($sampai); $h->addDay()) {
            $keluar[] = $h->copy();
        }

        return $keluar;
    }
}
