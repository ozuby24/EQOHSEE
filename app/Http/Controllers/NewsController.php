<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;

class NewsController extends Controller
{
    public function index()
    {
        $items = News::latest('published_at')->paginate(10);

        return Inertia::render('Berita/Daftar', [
            'judul'    => 'Berita',
            'subjudul' => 'Pengumuman dan kabar terbaru',

            'berita' => array_map(fn (News $n) => [
                'id'       => $n->id,
                'judul'    => $n->title,
                'tanggal'  => $n->published_at?->format('d M Y'),
                // Cuplikan dipotong di sini, bukan dengan CSS: isi berita
                // bisa sepanjang apa pun, dan mengirim seluruhnya hanya
                // untuk menampilkan tiga baris membuat halaman daftar
                // membawa muatan yang tidak dibaca siapa pun.
                'cuplikan' => Str::limit(strip_tags((string) $n->content), 220),
                'url'      => route('news.show', $n),
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
        News::create($this->v($r));

        return redirect()->route('news.index')->with('ok', 'Berita dipublikasikan.');
    }

    public function show(News $news)
    {
        return Inertia::render('Berita/Detail', [
            'judul'    => $news->title,
            'subjudul' => $news->published_at?->format('d F Y') ?? '',

            'berita' => [
                'judul'   => $news->title,
                'tanggal' => $news->published_at?->translatedFormat('d F Y'),
                'isi'     => (string) $news->content,
            ],

            'tautan' => ['daftar' => route('news.index')],
        ]);
    }

    public function edit(News $news)
    {
        return $this->formulir($news);
    }

    public function update(Request $r, News $news)
    {
        $news->update($this->v($r));

        return redirect()->route('news.index')->with('ok', 'Berita diperbarui.');
    }

    public function destroy(News $news)
    {
        $news->delete();

        return back()->with('ok', 'Berita dihapus.');
    }

    /** Formulir berita, dipakai bersama oleh create dan edit. */
    private function formulir(News $n)
    {
        return Inertia::render('Berita/Form', [
            'judul'    => $n->exists ? 'Edit Berita' : 'Tulis Berita',
            'subjudul' => $n->exists ? $n->title : 'Pengumuman baru untuk seluruh pengguna',

            'tersimpan' => $n->exists,

            'awal' => [
                'title'        => (string) ($n->title ?? ''),
                'content'      => (string) ($n->content ?? ''),
                'published_at' => $n->published_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
            ],

            'tautan' => [
                'simpan' => $n->exists ? route('news.update', $n) : route('news.store'),
                'batal'  => route('news.index'),
            ],
        ]);
    }

    private function v(Request $r): array
    {
        return $r->validate([
            'title'        => ['required', 'string', 'max:200'],
            'content'      => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
