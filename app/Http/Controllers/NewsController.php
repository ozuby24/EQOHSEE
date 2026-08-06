<?php
namespace App\Http\Controllers;
use App\Models\News;
use Illuminate\Http\Request;
class NewsController extends Controller
{
    public function index()  { return view('news.index', ['items' => News::latest('published_at')->paginate(10)]); }
    public function create() { return view('news.form', ['item' => new News()]); }
    public function store(Request $r)  { News::create($this->v($r)); return redirect()->route('news.index')->with('ok','Berita dipublikasikan.'); }
    public function show(News $news)   { return view('news.show', ['item' => $news]); }
    public function edit(News $news)   { return view('news.form', ['item' => $news]); }
    public function update(Request $r, News $news) { $news->update($this->v($r)); return redirect()->route('news.index')->with('ok','Berita diperbarui.'); }
    public function destroy(News $news){ $news->delete(); return back()->with('ok','Berita dihapus.'); }

    private function v(Request $r): array
    {
        return $r->validate([
            'title'        => ['required','string','max:200'],
            'content'      => ['nullable','string'],
            'published_at' => ['nullable','date'],
        ]);
    }
}
