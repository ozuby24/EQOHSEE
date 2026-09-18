<?php

namespace App\Support;

use App\Models\News;
use Illuminate\Database\Eloquent\Builder;

/**
 * Satu bentuk pengumuman, dipakai di mana pun ia muncul.
 *
 * Pengumuman kini tampil di tiga tempat dengan isi yang sama: panel di
 * dasbor pembelajaran, daftar berita, dan pop-out yang dibuka dari
 * keduanya. Sebelum berkas ini ada, ketiganya menyusun muatannya
 * sendiri-sendiri — dan yang di dasbor sudah berbeda dari yang di
 * daftar: satu memotong isi pada 74 huruf, satunya pada 220, dan
 * keduanya memakai format tanggal yang berlainan.
 *
 * Selisih semacam itu tidak pernah tampak sebagai galat. Yang tampak
 * adalah pengumuman yang "berubah" ketika dibuka dari tempat lain.
 *
 * Disusun sebagai satu fungsi, bukan sebagai trait atau kelas induk:
 * yang dibagi di sini bentuk DATA-nya, bukan perilaku, dan data yang
 * dibagi lewat pewarisan akan segera ditimpa sebagian oleh salah satu
 * pemakainya.
 */
final class Pengumuman
{
    /**
     * Kueri yang sudah membawa jumlah pembaca dan penanda terbaca.
     *
     * Dipakai SEBELUM mengambil barisnya, bukan sesudah. Memeriksa
     * keduanya per baris menghasilkan dua kueri tambahan untuk setiap
     * pengumuman yang tergambar — tiga pengumuman di dasbor menjadi
     * enam kueri yang tidak perlu ada, dan sepuluh di halaman daftar
     * menjadi dua puluh.
     */
    public static function kueri(?int $penggunaId): Builder
    {
        return News::query()
            ->withCount('bacaan')
            ->withExists(['bacaan as sudah_dibaca' => fn ($b) => $b->where('user_id', $penggunaId)]);
    }

    /**
     * Muatan satu pengumuman untuk pop-out maupun halaman penuhnya.
     *
     * `$batasRingkas` hanya berlaku bagi yang TIDAK menulis ringkasannya
     * sendiri — lihat News::ringkasan(). Panel dasbor memakai angka yang
     * lebih pendek karena kolomnya sempit, bukan karena isinya berbeda.
     *
     * @return array<string,mixed>
     */
    public static function muatan(News $n, int $batasRingkas = 220): array
    {
        return [
            'id'       => $n->id,
            'judul'    => $n->title,

            /* Dua format tanggal, sengaja. Panel sempit di dasbor hanya
               muat "18 Sep"; pop-out dan halaman penuh punya ruang untuk
               tanggal yang lengkap, dan pengumuman yang menyebut
               tenggat pantas menyebut bulannya utuh. */
            'tanggal'       => ($n->published_at ?? $n->created_at)?->translatedFormat('d F Y'),
            'tanggalPendek' => ($n->published_at ?? $n->created_at)?->translatedFormat('d M'),

            'ringkasan' => $n->ringkasan($batasRingkas),

            /* Isi UTUH ikut di sini, dan itu memang yang dikehendaki:
               pop-out yang masih harus mengambil isinya lewat jaringan
               akan gagal terbuka justru di sambungan site yang lambat —
               cacat yang persis sama pernah menimpa pop-out pengenalan
               dan membuatnya berubah menjadi kotak galat berulang. */
            'isi' => (string) $n->content,

            'sampul' => Berkas::url($n, 'brt'),

            'lampiran' => $n->lampiran ? [
                'nama' => $n->lampiran_nama ?: 'Lampiran',
                'url'  => route('berkas.unduh', ['jenis' => 'brl', 'baris' => $n->id]),
            ] : null,

            'url'     => route('news.show', $n),
            'urlBaca' => route('news.baca', $n),

            /* Atribut bentukan dari withCount/withExists. Dibaca dengan
               ?? supaya baris yang diambil tanpa Pengumuman::kueri()
               tetap menghasilkan muatan yang sah — tanpa itu, satu
               pemanggil yang lupa akan menjatuhkan halamannya. */
            'sudahDibaca'  => (bool) ($n->sudah_dibaca ?? false),
            'jumlahDibaca' => (int) ($n->bacaan_count ?? 0),
        ];
    }
}
