<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Ramalan capaian akhir periode dari laju yang sudah berjalan.
 *
 * Pertanyaan yang dijawab: kalau laju hari-hari kemarin diteruskan,
 * berapa produksi pada akhir bulan, dan apakah target akan tercapai.
 * Ini yang membedakan ruang kendali dari papan angka — capaian 60%
 * pada tanggal 10 sehat, sementara capaian 60% pada tanggal 25 sudah
 * hampir pasti meleset, dan angka 60% itu sendiri tidak membedakan
 * keduanya.
 *
 * Lajunya dihitung dari hari kalender yang sudah lewat, bukan dari
 * jumlah hari yang ada datanya. Membaginya dengan hari-berdata membuat
 * hari yang shift-nya belum dilaporkan justru menaikkan ramalan: makin
 * banyak laporan yang belum masuk, makin optimis angkanya. Kegagalan
 * itu diam — tidak ada galat, hanya ramalan yang terlalu bagus persis
 * ketika pelaporannya sedang buruk.
 *
 * Hanya angka yang sudah ditinjau yang boleh masuk ke sini; pemanggil
 * yang menyaringnya, lewat Alur::terhitung().
 */
final class Ramalan
{
    /** Di bawah ini dianggap meleset, di atasnya berisiko. */
    private const AMBANG_MELESET  = 0.90;
    private const AMBANG_BERISIKO = 1.00;

    public function __construct(
        private readonly float $aktual,
        private readonly float $target,
        private readonly Carbon $dari,
        private readonly Carbon $sampai,
        private readonly ?Carbon $kini = null,
    ) {}

    /**
     * Hari kalender dalam periode. Inklusif: 1–31 Agustus adalah 31 hari,
     * bukan 30.
     */
    public function hariTotal(): int
    {
        return $this->dari->diffInDays($this->sampai) + 1;
    }

    /**
     * Hari yang sudah lewat, dibatasi ujung periode.
     *
     * Periode yang seluruhnya sudah lampau menghasilkan hari-berjalan
     * sama dengan hari-total, sehingga ramalannya jatuh tepat pada
     * angka aktual — sebagaimana mestinya, sebab tidak ada lagi yang
     * perlu diramalkan.
     */
    public function hariBerjalan(): int
    {
        $kini = $this->kini ?? Carbon::now();

        if ($kini->lt($this->dari))   return 0;
        if ($kini->gte($this->sampai)) return $this->hariTotal();

        return $this->dari->diffInDays($kini) + 1;
    }

    public function hariTersisa(): int
    {
        return max(0, $this->hariTotal() - $this->hariBerjalan());
    }

    /** Produksi rata-rata per hari kalender yang sudah lewat. */
    public function laju(): float
    {
        $hari = $this->hariBerjalan();

        return $hari > 0 ? $this->aktual / $hari : 0.0;
    }

    /** Perkiraan capaian pada akhir periode bila laju diteruskan. */
    public function proyeksi(): float
    {
        return $this->laju() * $this->hariTotal();
    }

    /** Proyeksi terhadap target, dalam persen. */
    public function proyeksiPersen(): float
    {
        return $this->target > 0 ? $this->proyeksi() / $this->target * 100 : 0.0;
    }

    /** Kekurangan yang diperkirakan pada akhir periode; 0 bila tercapai. */
    public function kekurangan(): float
    {
        return max(0.0, $this->target - $this->proyeksi());
    }

    /**
     * Laju yang diperlukan pada sisa hari agar target tetap tercapai.
     *
     * Nol bila target sudah terlampaui, dan nol pula bila tidak ada hari
     * tersisa — pada titik itu yang tersisa bukan lagi laju, melainkan
     * kekurangan yang sudah pasti.
     */
    public function lajuDibutuhkan(): float
    {
        $sisa = $this->hariTersisa();

        if ($sisa <= 0) return 0.0;

        return max(0.0, ($this->target - $this->aktual) / $sisa);
    }

    /**
     * Seberapa keras laju harus naik dari sekarang, sebagai pengali.
     *
     * Angka 1,4 berarti sisa bulan harus berjalan 40 % lebih cepat
     * daripada rata-rata sejauh ini. Ini lebih terbaca daripada selisih
     * ton, sebab langsung menyatakan apakah tuntutannya masuk akal.
     */
    public function pengaliDibutuhkan(): float
    {
        $laju = $this->laju();

        return $laju > 0 ? $this->lajuDibutuhkan() / $laju : 0.0;
    }

    public function status(): string
    {
        if ($this->target <= 0)      return 'tanpa-target';
        if ($this->hariBerjalan() === 0) return 'belum-mulai';

        $rasio = $this->proyeksi() / $this->target;

        if ($rasio < self::AMBANG_MELESET)  return 'meleset';
        if ($rasio < self::AMBANG_BERISIKO) return 'berisiko';

        return 'aman';
    }

    public function toArray(): array
    {
        return [
            'aktual'            => $this->aktual,
            'target'            => $this->target,
            'hariTotal'         => $this->hariTotal(),
            'hariBerjalan'      => $this->hariBerjalan(),
            'hariTersisa'       => $this->hariTersisa(),
            'laju'              => $this->laju(),
            'proyeksi'          => $this->proyeksi(),
            'proyeksiPersen'    => $this->proyeksiPersen(),
            'kekurangan'        => $this->kekurangan(),
            'lajuDibutuhkan'    => $this->lajuDibutuhkan(),
            'pengaliDibutuhkan' => $this->pengaliDibutuhkan(),
            'status'            => $this->status(),
        ];
    }
}
