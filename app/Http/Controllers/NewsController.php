<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\NewsRead;
use App\Support\{Berkas, Pengumuman};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function index()
    {
        $items = Pengumuman::kueri(auth()->id())->latest('published_at')->paginate(10);

        return Inertia::render('Berita/Daftar', [
            'judul'    => 'Pengumuman',
            'subjudul' => 'Kabar dan surat edaran untuk seluruh pengguna',

            'berita' => array_map(fn (News $n) => Pengumuman::muatan($n) + [
                'urlUbah'  => route('news.edit', $n),
                'urlHapus' => route('news.destroy', $n),
            ], $items->items()),

            'halaman' => [
                'kini'   => $items->currentPage(),
                'akhir'  => $items->lastPage(),
                'total'  => $items->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $items->linkCollection()->all()),
            ],

            'bolehUbah' => Gate::allows('admin'),
            'tautan'    => ['buat' => route('news.create')],
        ]);
    }

    public function create()
    {
        return $this->formulir(new News());
    }

    public function store(Request $r)
    {
        $news = News::create($this->v($r));
        $this->simpanBerkas($r, $news);

        return redirect()->route('news.index')->with('ok', 'Pengumuman dipublikasikan.');
    }

    public function show(News $news)
    {
        /* Diambil ulang lewat Pengumuman::kueri supaya jumlah pembaca
           dan penanda terbaca ikut — pengikatan model bawaan Laravel
           mengambil barisnya polos, tanpa keduanya. */
        $n = Pengumuman::kueri(auth()->id())->findOrFail($news->id);

        return Inertia::render('Berita/Detail', [
            'judul'    => $n->title,
            'subjudul' => $n->published_at?->translatedFormat('d F Y') ?? '',

            'berita' => Pengumuman::muatan($n),

            'tautan' => ['daftar' => route('news.index')],
        ]);
    }

    /**
     * Tandai pengumuman ini sudah dibaca oleh yang membukanya.
     *
     * firstOrCreate, bukan create: pengumuman yang dibuka empat kali
     * oleh sepuluh orang tidak boleh terbaca sebagai empat puluh
     * pembaca. Kolom uniknya menjaga hal yang sama di basis data —
     * keduanya, sebab yang di sini menjawab tanpa galat sedangkan yang
     * di sana tetap berlaku bagi jalan masuk yang belum ditulis.
     *
     * Tidak dijaga Gate apa pun. Yang dapat membukanya memang yang
     * boleh menandainya, dan batas perusahaannya sudah ditegakkan scope
     * MilikPerusahaan pada pengikatan modelnya.
     */
    public function baca(News $news)
    {
        NewsRead::firstOrCreate(['news_id' => $news->id, 'user_id' => auth()->id()]);

        return back(fallback: route('news.index'));
    }

    public function edit(News $news)
    {
        return $this->formulir($news);
    }

    public function update(Request $r, News $news)
    {
        $news->update($this->v($r));
        $this->simpanBerkas($r, $news);

        return redirect()->route('news.index')->with('ok', 'Pengumuman diperbarui.');
    }

    public function destroy(News $news)
    {
        /* Berkasnya dibuang bersama barisnya. Baris yang hilang tanpa
           berkasnya meninggalkan sampul dan lampiran di diska selamanya
           — tak terjangkau halaman mana pun, tetapi tetap memakan tempat
           dan tetap terbaca oleh siapa pun yang punya akses berkas ke
           servernya. */
        Berkas::buang($news->cover);
        Berkas::buang($news->lampiran);

        $news->delete();

        return back()->with('ok', 'Pengumuman dihapus.');
    }

    /** Formulir berita, dipakai bersama oleh create dan edit. */
    private function formulir(News $n)
    {
        return Inertia::render('Berita/Form', [
            'judul'    => $n->exists ? 'Edit Pengumuman' : 'Tulis Pengumuman',
            'subjudul' => $n->exists ? $n->title : 'Kabar baru untuk seluruh pengguna',

            'tersimpan' => $n->exists,

            'awal' => [
                'title'        => (string) ($n->title ?? ''),
                'excerpt'      => (string) ($n->excerpt ?? ''),
                'content'      => (string) ($n->content ?? ''),
                'published_at' => $n->published_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            ],

            /* Berkas yang SUDAH terpasang dikirim terpisah dari `awal`.
               Dimasukkan ke dalamnya, useForm akan mengirim balik jalur
               berkas lama sebagai nilai medan unggahan, dan validasi
               `image` menolak tali teks — formulir edit yang tidak
               mengganti gambarnya menjadi tidak dapat disimpan sama
               sekali. */
            'berkas' => [
                'sampul'   => Berkas::url($n, 'brt'),
                'lampiran' => $n->lampiran ? [
                    'nama' => $n->lampiran_nama ?: 'Lampiran',
                    'url'  => route('berkas.unduh', ['jenis' => 'brl', 'baris' => $n->id]),
                ] : null,
            ],

            'tautan' => [
                'simpan' => $n->exists ? route('news.update', $n) : route('news.store'),
                'batal'  => route('news.index'),
            ],
        ]);
    }

    /**
     * Simpan sampul dan lampiran, bila memang ada yang diunggah.
     *
     * Terpisah dari v() dan dijalankan SESUDAH barisnya tersimpan.
     * Menggabungkannya berarti berkas ikut tersimpan meski validasi
     * medan lain menggagalkan permintaannya — meninggalkan berkas yatim
     * pada setiap judul yang kelupaan diisi.
     *
     * Yang tidak mengunggah apa pun tidak kehilangan yang lama. Itu
     * syarat agar formulir edit dapat dipakai memperbaiki salah ketik
     * pada judul tanpa harus mengunggah ulang gambarnya.
     */
    private function simpanBerkas(Request $r, News $n): void
    {
        $ubah = [];

        if ($r->hasFile('cover')) {
            Berkas::buang($n->cover);
            $ubah['cover'] = Berkas::simpan($r->file('cover'), 'berita');
        }

        if ($r->hasFile('lampiran')) {
            Berkas::buang($n->lampiran);
            $ubah['lampiran'] = Berkas::simpan($r->file('lampiran'), 'berita');

            /* Nama aslinya dibersihkan, bukan dipakai apa adanya. Ia
               hanya tampil sebagai teks tombol unduh — berkasnya sendiri
               sudah bernama acak di diska — tetapi nama yang memuat
               tanda kutip atau garis miring tidak punya alasan apa pun
               untuk sampai ke sana. */
            $ubah['lampiran_nama'] = mb_substr(
                preg_replace('/[^\p{L}\p{N}\s._-]+/u', '', $r->file('lampiran')->getClientOriginalName()) ?: 'Lampiran',
                0, 200,
            );
        }

        if ($ubah) $n->update($ubah);
    }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'title'        => ['required', 'string', 'max:200'],
            'excerpt'      => ['nullable', 'string', 'max:300'],
            'content'      => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],

            'cover'    => array_merge(['nullable'], Berkas::ATURAN_GAMBAR),
            'lampiran' => array_merge(['nullable'], Berkas::ATURAN_DOKUMEN),
        ], [], [
            'cover'    => 'sampul',
            'lampiran' => 'lampiran',
        ]);

        /* Kedua medan berkas DIVALIDASI di sini tetapi tidak ikut
           disimpan dari sini. Yang dikembalikan validate() untuk medan
           unggahan adalah objek UploadedFile, dan mengisikannya ke
           kolom tali teks menyimpan nama kelasnya, bukan jalurnya.
           Penyimpanannya di simpanBerkas(), sesudah barisnya ada. */
        unset($d['cover'], $d['lampiran']);

        return $d;
    }
}
