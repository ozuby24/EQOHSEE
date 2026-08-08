@extends('layouts.app')
@section('title','Kursus')

@section('content')
<div class="max-w-6xl mx-auto">
  @if(session('ok'))
    <div class="mb-5 rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  {{-- Bilah filter --}}
  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 mb-5">
    <div class="flex flex-wrap items-center gap-2">
      <div class="relative flex-1 min-w-0 basis-[180px]">
        <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-stone-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input name="q" value="{{ $q }}" placeholder="Cari judul atau deskripsi kursus..."
               class="ring-focus w-full rounded-xl border border-stone-200 pl-10 pr-3 py-2.5 text-[13px] transition">
      </div>

      <select name="kategori" onchange="this.form.submit()"
              class="ring-focus flex-1 basis-[8.5rem] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition">
        <option value="">Semua jenis</option>
        @foreach($kategori as $k)
          <option value="{{ $k }}" @selected($kat === $k)>{{ $k }}</option>
        @endforeach
      </select>

      <select name="status" onchange="this.form.submit()"
              class="ring-focus flex-1 basis-[8.5rem] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition">
        <option value="">Semua status</option>
        <option value="diikuti" @selected($status === 'diikuti')>Sedang diikuti</option>
        <option value="belum"   @selected($status === 'belum')>Belum diikuti</option>
      </select>

      <select name="urut" onchange="this.form.submit()"
              class="ring-focus flex-1 basis-[8.5rem] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition">
        <option value="baru"  @selected($urut === 'baru')>Terbaru</option>
        <option value="judul" @selected($urut === 'judul')>Judul A–Z</option>
        <option value="modul" @selected($urut === 'modul')>Modul terbanyak</option>
      </select>

      <button class="rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:bg-cam-panel transition">Cari</button>

      @if($q || $kat || $status || $urut !== 'baru')
        <a href="{{ route('courses.index') }}" class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink transition">Reset</a>
      @endif
    </div>
  </form>

  {{-- Pintasan jenis kursus --}}
  @if($kategori->count())
    <div class="flex flex-wrap gap-1.5 mb-5">
      <a href="{{ route('courses.index', array_filter(['q'=>$q,'status'=>$status,'urut'=>$urut])) }}"
         class="px-3 py-1.5 rounded-full text-[11.5px] font-bold transition {{ !$kat ? 'lime-gradient text-white shadow-glow' : 'bg-white border border-stone-200 text-stone-500 hover:border-cam-lime' }}">Semua</a>
      @foreach($kategori as $k)
        <a href="{{ route('courses.index', array_filter(['q'=>$q,'status'=>$status,'urut'=>$urut,'kategori'=>$k])) }}"
           class="px-3 py-1.5 rounded-full text-[11.5px] font-bold transition {{ $kat === $k ? 'lime-gradient text-white shadow-glow' : 'bg-white border border-stone-200 text-stone-500 hover:border-cam-lime' }}">{{ $k }}</a>
      @endforeach
    </div>
  @endif

  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <p class="text-[12.5px] text-stone-400"><span class="num font-semibold text-stone-600">{{ $courses->total() }}</span> kursus ditemukan</p>
    @can('admin')
      <a href="{{ route('courses.create') }}"
         class="lime-gradient shadow-glow inline-flex items-center gap-2 rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
        Tambah Kursus
      </a>
    @endcan
  </div>

  @if($courses->isEmpty())
    <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
      <p class="text-[13px] text-stone-400">Belum ada kursus.</p>
    </div>
  @else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      @foreach($courses as $course)
        <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden card-hover">
          <div class="h-28 brand-gradient relative">
            @if($course->image)
              <img src="{{ asset('storage/'.$course->image) }}" alt="" class="w-full h-full object-cover">
            @else
              <div class="w-full h-full grid place-items-center">
                <span class="font-display text-3xl font-black text-white/15">{{ strtoupper(substr($course->title,0,1)) }}</span>
              </div>
            @endif
            <div class="absolute top-2.5 left-2.5 flex gap-1.5">
              @if($course->category)
                <span class="glass rounded-full text-[10px] font-bold text-white px-2.5 py-1">{{ $course->category }}</span>
              @endif
              @if($course->require_code)
                <span class="glass rounded-full text-[10px] font-bold text-white px-2 py-1 inline-flex items-center gap-1" title="Perlu kode akses">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </span>
              @endif
              @if(in_array($course->id, $diikuti))
                <span class="rounded-full bg-cam-lime text-white text-[10px] font-bold px-2.5 py-1">Diikuti</span>
              @endif
            </div>
          </div>

          <div class="p-4">
            <h3 class="text-[14px] font-bold text-cam-ink clamp-1">{{ $course->title }}</h3>
            <p class="text-[12px] text-stone-400 mt-1 clamp-2 leading-relaxed">{{ $course->description ?: '—' }}</p>

            <div class="flex items-center gap-2 mt-3 text-[11px] text-stone-400">
              <span class="inline-flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
                <span class="num">{{ $course->modules_count }}</span> modul
              </span>
            </div>

            <div class="mt-3 pt-3 border-t border-stone-100 flex items-center gap-1.5">
              <a href="{{ route('learn.show', $course) }}"
                 class="lime-gradient rounded-lg text-white px-3.5 py-2 text-[11.5px] font-bold hover:brightness-105 transition">Belajar</a>
              <a href="{{ route('courses.show', $course) }}"
                 class="px-2.5 py-2 text-[11.5px] font-semibold rounded-lg text-stone-500 hover:bg-stone-50 transition">Detail</a>
              @can('admin')
                <a href="{{ route('manage.course', $course) }}"
                   class="px-2.5 py-2 text-[11.5px] font-semibold rounded-lg text-cam-lime-deep hover:bg-cam-lime-soft transition">Kelola</a>
                <form action="{{ route('courses.destroy', $course) }}" method="POST" onsubmit="return confirm('Hapus kursus ini?')" class="ml-auto">
                  @csrf @method('DELETE')
                  <button class="p-2 rounded-lg text-stone-300 hover:text-red-500 hover:bg-red-50 transition" title="Hapus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M19 7l-.87 12.14A2 2 0 0116.14 21H7.86a2 2 0 01-1.99-1.86L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                  </button>
                </form>
              @endcan
            </div>
          </div>
        </div>
      @endforeach
    </div>
    <div class="mt-6">{{ $courses->links() }}</div>
  @endif
</div>
@endsection
