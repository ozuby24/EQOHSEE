<?php

namespace App\Models\Concerns;

use App\Models\PersetujuanParaf;
use App\Models\User;
use App\Support\Tahap;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RuntimeException;

/**
 * Rantai paraf yang terlihat, di atas alur yang tetap satu keputusan.
 *
 * Dipasang BERSAMA trait Ditinjau, tidak menggantikannya. Ditinjau yang
 * memegang status dan perpindahannya; trait ini hanya menambahkan
 * catatan siapa-sudah-melihat pada tahap-tahap sebelum keputusan.
 *
 * Pembagian itu disengaja dan penting. Selama paraf tinggal di tabelnya
 * sendiri dan tidak punya jalan menyentuh kolom status, "tahap
 * sebelumnya tidak menerbitkan apa pun" bukan aturan yang harus diingat
 * penulis kode berikutnya — ia keadaan yang memang tidak dapat
 * dilanggar.
 *
 * Modelnya wajib memakai Ditinjau juga; kelasNya diperiksa saat boot
 * supaya pemasangan yang keliru ketahuan seketika, bukan nanti ketika
 * seseorang menekan tombol setujui dan mendapat galat yang tidak
 * menyebut sebabnya.
 */
trait Bertahap
{
    public static function bootBertahap(): void
    {
        if (!method_exists(static::class, 'sudahDisetujui')) {
            throw new RuntimeException(
                static::class.' memakai Bertahap tanpa Ditinjau. Rantai paraf '
                .'menempel pada alur tinjauan, bukan menggantikannya.'
            );
        }

        /* Paraf ikut terbuang bersama subjeknya. Tanpa ini, menghapus
           pengajuan meninggalkan barisnya di persetujuan_paraf, dan
           pengajuan berikutnya yang kebetulan mendapat id yang sama akan
           lahir dengan rantai yang sudah setengah terparaf oleh orang
           yang tidak pernah melihatnya. */
        static::deleting(function ($model) {
            $model->paraf()->delete();
        });
    }

    public function paraf(): MorphMany
    {
        return $this->morphMany(PersetujuanParaf::class, 'subjek');
    }

    /**
     * Rantai untuk digambar: tiap tahap beserta parafnya bila sudah ada.
     *
     * Tahap penentu ikut dipulangkan, tetapi keadaannya diambil dari
     * STATUS — bukan dari tabel paraf, sebab ia memang tidak pernah
     * diparaf. Menggambarnya dari sumber yang sama dengan tahap lain
     * akan membuatnya selamanya tampak "menunggu" walaupun kartunya
     * sudah terbit.
     *
     * @return list<array<string,mixed>>
     */
    public function rantaiTahap(): array
    {
        $terparaf = $this->paraf->keyBy('tahap');

        $hasil = [];

        foreach (Tahap::RANTAI as $kode => $t) {
            $p = $terparaf->get($kode);

            $hasil[] = [
                'kode'    => $kode,

                /* Nomor urut ikut dikirim, tidak dihitung ulang dari
                   posisi array di layar. Urutan tahap adalah data —
                   pemiliknya rantai di App\Support\Tahap — dan
                   menggambarnya dari indeks perulangan membuat dua
                   sumber kebenaran untuk hal yang sama. */
                'urut'    => count($hasil) + 1,

                'label'   => $t['label'],
                'terang'  => $t['terang'],
                'penentu' => $t['penentu'],

                'keadaan' => $t['penentu']
                    ? $this->status                      // draf|diajukan|disetujui|ditolak
                    : ($p ? 'paraf' : 'menunggu'),

                'oleh'    => $p?->nama,
                'jabatan' => $p?->jabatan,
                'pada'    => $p?->created_at?->toDateTimeString(),
                'catatan' => $p?->catatan,
            ];
        }

        return $hasil;
    }

    /**
     * Tahap non-penentu yang belum diparaf.
     *
     * TIDAK menahan keputusan OHSE — dipulangkan justru supaya
     * disebutkan di layar sebelum ia memutuskan. Menyetujui pengajuan
     * yang belum dilihat atasannya boleh saja; melakukannya tanpa tahu
     * itulah yang sedang terjadi, tidak.
     *
     * @return list<string> label tahapnya
     */
    public function parafTertinggal(): array
    {
        $ada = $this->paraf->pluck('tahap')->all();

        return collect(Tahap::RANTAI)
            ->reject(fn ($t) => $t['penentu'])
            ->keys()
            ->reject(fn ($kode) => in_array($kode, $ada, true))
            ->map(fn ($kode) => Tahap::label($kode))
            ->values()->all();
    }

    /**
     * Membubuhkan paraf pada satu tahap.
     *
     * Hanya selagi pengajuannya masih berjalan. Memaraf yang sudah
     * diputus bukan sekadar tak berguna — ia menambah nama ke dalam
     * catatan sebuah keputusan yang sudah diambil tanpa nama itu.
     */
    public function bubuhkanParaf(string $tahap, ?User $u = null, ?string $catatan = null): void
    {
        $u ??= auth()->user();

        if (!Tahap::dapatDiparaf($tahap)) {
            throw new RuntimeException(
                'Tahap "'.$tahap.'" bukan tahap paraf. Tahap penentu diputus, bukan diparaf.'
            );
        }

        if ($this->sudahDisetujui() || $this->status === \App\Support\Alur::DITOLAK) {
            throw new RuntimeException('Pengajuan ini sudah diputus; parafnya tidak dapat ditambah.');
        }

        /* Pengaju tidak memaraf pengajuannya sendiri, sama alasannya
           dengan pengaju tidak meninjau pekerjaannya sendiri: paraf yang
           dibubuhkan sendiri tidak memberi tahu siapa pun apa-apa. */
        if ($this->diajukan_oleh !== null && $this->diajukan_oleh === $u?->getKey()) {
            throw new RuntimeException('Pengaju tidak dapat memaraf pengajuannya sendiri.');
        }

        $this->paraf()->updateOrCreate(
            ['tahap' => $tahap],
            [
                'user_id' => $u?->getKey(),
                'nama'    => $u?->name ?? 'Tanpa nama',
                'jabatan' => $u?->position,
                'catatan' => $catatan,
            ],
        );

        $this->load('paraf');
    }
}
