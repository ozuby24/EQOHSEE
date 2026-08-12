@extends('layouts.app')
@section('title','Register Dokumen')

@section('content')
@php use App\Support\Dokumen; @endphp
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Ringkasan --}}
  <div class="grid gap-3 grid-cols-2 lg:grid-cols-4">
    @foreach ([
      ['Total dokumen', $stat['total'],   '#0F1720', 'Dokumen'],
      ['Berlaku',       $stat['berlaku'], '#4FA82E', 'Berlaku'],
      ['Draft',         $stat['draft'],   '#9AA3AE', 'Draft'],
      ['Perlu ditinjau',$stat['lewat'],   '#F0921E', 'Tinjau'],
    ] as [$label,$nilai,$warna,$ik])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 relative overflow-hidden">
        <span class="absolute -right-6 -top-6 w-20 h-20 rounded-full" style="background:{{ $warna }}0F"></span>
        <div class="relative flex items-start justify-between gap-2">
          <div class="min-w-0">
            <div class="stat stat-sm leading-none" style="color:{{ $warna }}">{{ $nilai }}</div>
            <div class="text-[11px] text-stone-400 mt-1.5 leading-snug">{{ $label }}</div>
          </div>
          <span class="shrink-0 w-8 h-8 rounded-lg grid place-items-center" style="background:{{ $warna }}1A">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="{{ $warna }}" stroke-width="1.9" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h3"/>
            </svg>
          </span>
        </div>
      </div>
    @endforeach
  </div>

  {{-- Bilah filter --}}
  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3">
    <div class="flex flex-wrap items-center gap-2">
      <input name="q" value="{{ $f['q'] }}" placeholder="Cari kode, judul, atau ringkasan..."
             class="ring-focus flex-1 min-w-0 basis-[180px] rounded-xl border border-stone-200 px-4 py-2.5 text-[13px] transition">

      <select name="jenis" onchange="this.form.submit()"
              class="ring-focus flex-1 basis-[8.5rem] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition">
        <option value="">Semua jenis</option>
        @foreach(Dokumen::JENIS as $j)<option value="{{ $j }}" @selected($f['jenis']===$j)>{{ $j }}</option>@endforeach
      </select>

      <select name="status" onchange="this.form.submit()"
              class="ring-focus flex-1 basis-[8.5rem] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition">
        <option value="">Semua status</option>
        @foreach(Dokumen::STATUS as $s)<option value="{{ $s }}" @selected($f['status']===$s)>{{ ucfirst($s) }}</option>@endforeach
      </select>

      <select name="tinjau" onchange="this.form.submit()"
              class="ring-focus flex-1 basis-[8.5rem] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600 transition">
        <option value="">Semua masa tinjau</option>
        <option value="lewat"  @selected($f['tinjau']==='lewat')>Lewat jatuh tempo</option>
        <option value="segera" @selected($f['tinjau']==='segera')>Segera ({{ Dokumen::AMBANG_PERINGATAN }} hari)</option>
      </select>

      <button class="rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:bg-cam-panel transition">Cari</button>
      <a href="{{ route('dokumen.create') }}" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Dokumen</a>
    </div>
  </form>

  {{-- Daftar --}}
  <div class="space-y-2.5">
    @forelse($documents as $d)
      <a href="{{ route('dokumen.show',$d) }}"
         class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 transition">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="num text-[11.5px] font-bold text-stone-500">{{ $d->kode }}</span>
              <span class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded"
                    style="background:{{ Dokumen::warna($d->status) }}">{{ $d->status }}</span>
              <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100 text-stone-500 px-2 py-0.5 rounded">{{ $d->jenis }}</span>
              @if($d->perluTinjau())
                <span class="text-[9.5px] font-bold uppercase tracking-wide bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Lewat tinjau</span>
              @elseif($d->segeraTinjau())
                <span class="text-[9.5px] font-bold uppercase tracking-wide bg-amber-50 text-amber-600 px-2 py-0.5 rounded">Segera ditinjau</span>
              @endif
            </div>
            <div class="text-[13.5px] font-bold text-cam-ink mt-1.5 clamp-1">{{ $d->judul }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5">
              {{ $d->labelRevisi() }}
              @if($d->departemen) · {{ $d->departemen }} @endif
              @if($d->tanggal_tinjau) · tinjau {{ $d->tanggal_tinjau->format('d M Y') }} @endif
            </div>
          </div>
          @if($d->berkas)
            <span class="shrink-0 text-[11px] font-bold text-cam-lime-deep">Ada berkas</span>
          @endif
        </div>
      </a>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center">
        <p class="text-[13px] text-stone-400">Belum ada dokumen.
          <a href="{{ route('dokumen.create') }}" class="text-cam-lime-deep font-bold hover:underline">Daftarkan yang pertama</a>.</p>
      </div>
    @endforelse
  </div>

  {{ $documents->links() }}
</div>
@endsection
