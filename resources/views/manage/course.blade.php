@extends('layouts.app')
@section('title','Kelola: '.$course->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif
  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
      @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
    </div>
  @endif

  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kelola Konten</div>
      <h2 class="text-[18px] font-bold text-cam-ink mt-0.5">{{ $course->title }}</h2>
    </div>
    <div class="flex gap-2">
      <a href="{{ route('learn.show',$course) }}" class="rounded-xl border border-stone-200 px-4 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50">Pratinjau</a>
      <a href="{{ route('courses.edit',$course) }}" class="rounded-xl border border-stone-200 px-4 py-2 text-[12px] font-bold text-cam-lime-deep hover:bg-cam-lime-soft">Info Kursus</a>
    </div>
  </div>

  {{-- Kode akses --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 flex flex-wrap items-center justify-between gap-4">
    <div>
      <div class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Kode Akses Kursus</div>
      <div class="flex items-center gap-3 mt-1.5">
        <span class="text-[22px] font-bold tracking-[0.3em] text-cam-ink num">{{ $course->access_code ?: '—' }}</span>
        @if($course->require_code)
          <span class="text-[10px] font-bold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded">WAJIB</span>
        @else
          <span class="text-[10px] font-bold bg-stone-100 text-stone-400 px-2 py-0.5 rounded">TIDAK AKTIF</span>
        @endif
      </div>
      <p class="text-[11.5px] text-stone-400 mt-1">Berikan kode ini kepada peserta yang berhak mengambil kursus.</p>
    </div>
    <a href="{{ route('courses.edit', $course) }}" class="rounded-xl border border-stone-200 px-4 py-2 text-[12px] font-bold text-cam-lime-deep hover:bg-cam-lime-soft transition">Ubah kode</a>
  </div>

  {{-- ===== MODUL ===== --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <h3 class="text-[14px] font-bold text-cam-ink mb-4">Modul &amp; Materi</h3>

    <div class="space-y-3">
      @forelse($course->modules as $m)
        <div class="rounded-xl border border-stone-200 p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="text-[13.5px] font-bold text-cam-ink">{{ $m->order_index }}. {{ $m->title }}</div>
              @if($m->description)<p class="text-[12px] text-stone-400 mt-0.5">{{ $m->description }}</p>@endif
            </div>
            <form action="{{ route('manage.module.destroy',$m) }}" method="POST" onsubmit="return confirm('Hapus modul beserta materinya?')">
              @csrf @method('DELETE')
              <button class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">Hapus</button>
            </form>
          </div>

          @if($m->materials->count())
            <ul class="mt-3 space-y-1.5">
              @foreach($m->materials as $mat)
                <li class="flex items-center justify-between gap-2 text-[12.5px] bg-stone-50 rounded-lg px-3 py-2">
                  <span class="flex items-center gap-2 min-w-0">
                    <span class="text-[9.5px] uppercase font-bold bg-cam-lime-soft text-cam-lime-deep px-1.5 py-0.5 rounded tracking-wide">{{ $mat->type ?: 'file' }}</span>
                    <span class="text-stone-600 clamp-1">{{ $mat->title }}</span>
                  </span>
                  <form action="{{ route('manage.material.destroy',$mat) }}" method="POST">
                    @csrf @method('DELETE')
                    <button class="text-[11px] text-red-400 hover:text-red-600">✕</button>
                  </form>
                </li>
              @endforeach
            </ul>
          @endif

          {{-- tambah materi --}}
          <details class="mt-3">
            <summary class="text-[11.5px] font-bold text-cam-lime-deep cursor-pointer hover:underline">+ Tambah materi</summary>
            <form action="{{ route('manage.material.store',$m) }}" method="POST" class="mt-2.5 grid sm:grid-cols-[1fr_130px] gap-2">
              @csrf
              <input name="title" placeholder="Judul materi" required class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
              <select name="type" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
                <option value="document">Dokumen</option><option value="pdf">PDF</option>
                <option value="pptx">PPTX</option><option value="video">Video</option>
              </select>
              <input name="url" placeholder="Tautan (opsional) https://..." class="ring-focus sm:col-span-2 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
              <button class="sm:col-span-2 lime-gradient rounded-lg text-white py-2 text-[12px] font-bold hover:brightness-105">Simpan Materi</button>
            </form>
          </details>
        </div>
      @empty
        <p class="text-[12.5px] text-stone-400 text-center py-6">Belum ada modul.</p>
      @endforelse
    </div>

    {{-- tambah modul --}}
    <form action="{{ route('manage.module.store',$course) }}" method="POST" class="mt-4 pt-4 border-t border-stone-100 grid gap-2">
      @csrf
      <input name="title" placeholder="Judul modul baru" required class="ring-focus rounded-xl border border-stone-200 px-4 py-2.5 text-[13px]">
      <input name="description" placeholder="Deskripsi singkat (opsional)" class="ring-focus rounded-xl border border-stone-200 px-4 py-2.5 text-[13px]">
      <button class="lime-gradient shadow-glow rounded-xl text-white py-2.5 text-[12.5px] font-bold hover:brightness-105">+ Tambah Modul</button>
    </form>
  </div>

  {{-- ===== KUIS ===== --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <h3 class="text-[14px] font-bold text-cam-ink mb-4">Kuis &amp; Soal</h3>

    <div class="space-y-3">
      @forelse($course->quizzes as $qz)
        <div class="rounded-xl border border-stone-200 p-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="text-[13.5px] font-bold text-cam-ink">{{ $qz->title }}</div>
              <div class="text-[11px] text-stone-400 mt-0.5">{{ $qz->questions->count() }} soal · lulus ≥ {{ $qz->pass_score }}</div>
            </div>
            <form action="{{ route('manage.quiz.destroy',$qz) }}" method="POST" onsubmit="return confirm('Hapus kuis beserta soalnya?')">
              @csrf @method('DELETE')
              <button class="text-[11.5px] font-semibold text-red-500 hover:bg-red-50 px-2.5 py-1 rounded-lg">Hapus</button>
            </form>
          </div>

          @if($qz->questions->count())
            <ul class="mt-3 space-y-1.5">
              @foreach($qz->questions as $qq)
                <li class="flex items-start justify-between gap-2 text-[12.5px] bg-stone-50 rounded-lg px-3 py-2">
                  <div class="min-w-0">
                    <div class="text-stone-600">{{ $loop->iteration }}. {{ $qq->question }}</div>
                    <div class="text-[11px] text-cam-lime-deep mt-0.5">✓ {{ ($qq->options[$qq->correct_index] ?? '—') }}</div>
                  </div>
                  <form action="{{ route('manage.question.destroy',$qq) }}" method="POST">
                    @csrf @method('DELETE')
                    <button class="text-[11px] text-red-400 hover:text-red-600">✕</button>
                  </form>
                </li>
              @endforeach
            </ul>
          @endif

          {{-- tambah soal --}}
          <details class="mt-3">
            <summary class="text-[11.5px] font-bold text-cam-lime-deep cursor-pointer hover:underline">+ Tambah soal</summary>
            <form action="{{ route('manage.question.store',$qz) }}" method="POST" class="mt-2.5 space-y-2">
              @csrf
              <input name="question" placeholder="Tulis pertanyaan" required class="ring-focus w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
              @for($i=0;$i<4;$i++)
                <div class="flex items-center gap-2">
                  <input type="radio" name="correct_index" value="{{ $i }}" @if($i===0) checked @endif class="text-cam-lime-dark focus:ring-cam-lime/40" title="Tandai jawaban benar">
                  <input name="options[{{ $i }}]" placeholder="Pilihan {{ chr(65+$i) }}" required class="ring-focus flex-1 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
                </div>
              @endfor
              <p class="text-[11px] text-stone-400">Bulatan di kiri = kunci jawaban.</p>
              <button class="w-full lime-gradient rounded-lg text-white py-2 text-[12px] font-bold hover:brightness-105">Simpan Soal</button>
            </form>
          </details>
        </div>
      @empty
        <p class="text-[12.5px] text-stone-400 text-center py-6">Belum ada kuis.</p>
      @endforelse
    </div>

    {{-- tambah kuis --}}
    <form action="{{ route('manage.quiz.store',$course) }}" method="POST" class="mt-4 pt-4 border-t border-stone-100 grid sm:grid-cols-[1fr_130px] gap-2">
      @csrf
      <input name="title" placeholder="Judul kuis baru" required class="ring-focus rounded-xl border border-stone-200 px-4 py-2.5 text-[13px]">
      <input type="number" name="pass_score" value="70" min="0" max="100" class="ring-focus rounded-xl border border-stone-200 px-4 py-2.5 text-[13px]" title="Nilai lulus">
      <button class="sm:col-span-2 lime-gradient shadow-glow rounded-xl text-white py-2.5 text-[12.5px] font-bold hover:brightness-105">+ Tambah Kuis</button>
    </form>
  </div>
</div>
@endsection
