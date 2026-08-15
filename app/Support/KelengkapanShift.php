<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Seberapa lengkap laporan shift pada sebuah periode.
 *
 * Ruang kendali yang tidak mengukur ini akan menampilkan capaian rendah
 * sebagai masalah produksi, padahal yang terjadi adalah laporannya belum
 * masuk. Dua hal itu menuntut tindakan yang sama sekali berbeda —
 * menambah alat, atau menagih laporan — dan angka capaian saja tidak
 * membedakannya.
 *
 * Yang dihitung ada dua celah, dan keduanya perlu dibedakan:
 *
 * - Shift yang tidak ada laporannya sama sekali.
 * - Shift yang laporannya ada tetapi belum ditinjau, sehingga angkanya
 *   belum boleh terhitung.
 *
 * Yang pertama ditagih kepada pengawas lapangan, yang kedua kepada
 * peninjau. Menggabungkan keduanya menjadi satu angka "data tidak
 * lengkap" membuat tagihannya salah alamat.
 */
final class KelengkapanShift
{
    /** @param Collection<int,object> $records seluruh baris pada periode, tanpa disaring status */
    public function __construct(
        private readonly Collection $records,
        private readonly Carbon $dari,
        private readonly Carbon $sampai,
        private readonly array $shift = ['siang', 'malam'],
        private readonly ?Carbon $kini = null,
    ) {}

    /**
     * Hari yang shift-nya sudah semestinya dilaporkan.
     *
     * Hari yang belum tiba tidak ikut dihitung: menagih laporan untuk
     * besok akan membuat kelengkapan selalu terlihat buruk sepanjang
     * bulan berjalan, dan peringatan yang selalu menyala berhenti
     * dibaca.
     */
    public function hariWajib(): int
    {
        $kini = $this->kini ?? Carbon::now();
        $ujung = $kini->lt($this->sampai) ? $kini : $this->sampai;

        if ($ujung->lt($this->dari)) return 0;

        return $this->dari->diffInDays($ujung) + 1;
    }

    public function shiftWajib(): int
    {
        return $this->hariWajib() * count($this->shift);
    }

    /** Pasangan tanggal+shift yang sudah ada laporannya, apa pun statusnya. */
    public function shiftTerlapor(): int
    {
        return $this->pasangan($this->records);
    }

    /** Pasangan tanggal+shift yang laporannya sudah disetujui. */
    public function shiftDisetujui(): int
    {
        return $this->pasangan(
            $this->records->whereIn('status', Alur::terhitung())
        );
    }

    /** Shift yang tidak ada laporannya sama sekali. */
    public function belumDilaporkan(): int
    {
        return max(0, $this->shiftWajib() - $this->shiftTerlapor());
    }

    /** Shift yang sudah dilaporkan tetapi belum ditinjau. */
    public function menungguTinjauan(): int
    {
        return max(0, $this->shiftTerlapor() - $this->shiftDisetujui());
    }

    /** Persentase shift wajib yang angkanya sudah boleh dipakai. */
    public function persen(): float
    {
        $wajib = $this->shiftWajib();

        return $wajib > 0 ? $this->shiftDisetujui() / $wajib * 100 : 100.0;
    }

    /**
     * Tanggal dan shift yang laporannya belum ada.
     *
     * Dikembalikan sebagai daftar, bukan sekadar jumlah, supaya
     * penagihannya dapat menyebut hari yang mana — daftar yang bisa
     * ditindaklanjuti, bukan angka yang hanya bisa dikeluhkan.
     */
    public function daftarBolong(int $batas = 20): array
    {
        $ada = $this->records
            ->map(fn ($r) => $this->kunci($r->tanggal, $r->shift))
            ->flip();

        $bolong = [];
        $kini = $this->kini ?? Carbon::now();
        $ujung = $kini->lt($this->sampai) ? $kini : $this->sampai;

        for ($h = $this->dari->copy(); $h->lte($ujung); $h->addDay()) {
            foreach ($this->shift as $s) {
                if ($ada->has($this->kunci($h, $s))) continue;

                $bolong[] = ['tanggal' => $h->toDateString(), 'shift' => $s];

                if (count($bolong) >= $batas) return $bolong;
            }
        }

        return $bolong;
    }

    public function toArray(): array
    {
        return [
            'shiftWajib'       => $this->shiftWajib(),
            'shiftTerlapor'    => $this->shiftTerlapor(),
            'shiftDisetujui'   => $this->shiftDisetujui(),
            'belumDilaporkan'  => $this->belumDilaporkan(),
            'menungguTinjauan' => $this->menungguTinjauan(),
            'persen'           => $this->persen(),
            'bolong'           => $this->daftarBolong(),
        ];
    }

    /** @param Collection<int,object> $rows */
    private function pasangan(Collection $rows): int
    {
        return $rows
            ->map(fn ($r) => $this->kunci($r->tanggal, $r->shift))
            ->unique()
            ->count();
    }

    private function kunci(mixed $tanggal, ?string $shift): string
    {
        $t = $tanggal instanceof Carbon ? $tanggal : Carbon::parse((string) $tanggal);

        return $t->toDateString().'|'.($shift ?? '');
    }
}
