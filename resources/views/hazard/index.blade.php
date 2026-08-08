@extends('layouts.app')
@section('title','Monitor Hazard Report')

@section('content')
@php use App\Support\Hazard; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Statistik --}}
  <div class="grid gap-3 grid-cols-2 lg:grid-cols-5">
    @foreach ([
      ['Total laporan', $stat['total'],  'text-cam-ink'],
      ['Open',          $stat['open'],   'text-red-500'],
      ['In Progress',   $stat['proses'], 'text-amber-500'],
      ['Closed',        $stat['closed'], 'text-cam-lime-deep'],
      ['Risiko tinggi belum tutup', $stat['tinggi'], 'text-red-600'],
    ] as [$l,$v,$c])
      <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
        <div class="stat stat-sm {{ $c }}">{{ $v }}</div>
        <div class="text-[11px] text-stone-400 mt-1.5 leading-tight">{{ $l }}</div>
      </div>
    @endforeach
  </div>

  {{-- Filter --}}
  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3">
    <div class="flex flex-wrap items-center gap-2">
      <input name="q" value="{{ $f['q'] }}" placeholder="Cari kode, lokasi, deskripsi, pelapor..."
             class="ring-focus flex-1 min-w-0 basis-[180px] rounded-xl border border-stone-200 px-4 py-2.5 text-[13px] transition">
      <select name="bulan" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
        <option value="">Semua bulan</option>
        @foreach($bulanOpsi as $b)<option value="{{ $b }}" @selected($f['bulan']===$b)>{{ \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y') }}</option>@endforeach
      </select>
      <select name="risiko" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
        <option value="">Semua risiko</option>
        @foreach(Hazard::RISIKO as $r)<option value="{{ $r }}" @selected($f['risiko']===$r)>{{ $r }}</option>@endforeach
      </select>
      <select name="status" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
        <option value="">Semua status</option>
        @foreach(Hazard::STATUS as $s)<option value="{{ $s }}" @selected($f['status']===$s)>{{ $s }}</option>@endforeach
      </select>
      <select name="kategori" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
        <option value="">Semua kategori</option>
        @foreach(Hazard::KATEGORI as $k)<option value="{{ $k }}" @selected($f['kategori']===$k)>{{ $k }}</option>@endforeach
      </select>
      <select name="perusahaan" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
        <option value="">Semua perusahaan</option>
        @foreach($companies as $c)<option value="{{ $c->id }}" @selected($f['perusahaan']==$c->id)>{{ $c->name }}</option>@endforeach
      </select>
      <button class="rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:bg-cam-panel transition">Cari</button>
      <a href="{{ route('hazard.index') }}" class="px-3 py-2.5 text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink">Reset</a>
      <a href="{{ route('hazard.create') }}" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Buat Laporan</a>
    </div>

    <div class="flex flex-wrap items-center gap-2 mt-2.5 pt-2.5 border-t border-stone-100">
      <span class="text-[11px] font-bold uppercase tracking-wide text-stone-400 px-1">Ekspor hasil saringan</span>
      <a href="{{ route('hazard.ekspor.csv', request()->query()) }}"
         class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">⤓ Excel (CSV)</a>
      <a href="{{ route('hazard.ekspor.cetak', request()->query()) }}" target="_blank" rel="noopener"
         class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">⎙ PDF</a>
      @php
        $ringkasWa = "*Rekap Hazard Report — EQOHSEE*\n\n"
          ."Total: {$stat['total']} · Open: {$stat['open']} · Proses: {$stat['proses']} · Closed: {$stat['closed']}\n"
          ."Risiko tinggi belum tutup: {$stat['tinggi']}\n\n"
          .$reports->take(10)->map(fn($x) => "• [{$x->kode}] {$x->risiko} — ".\Illuminate\Support\Str::limit($x->deskripsi, 60)
              ." (📍".($x->lokasi ?: '-').", ".($x->company?->name ?: $x->terlapor ?: '-').", {$x->status})")->implode("\n")
          ."\n\nMohon ditindaklanjuti sesuai PIC masing-masing.";
      @endphp
      <a href="{{ \App\Support\Ekspor::waLink($ringkasWa) }}" target="_blank" rel="noopener"
         class="rounded-lg bg-[#25D366] text-white px-3 py-1.5 text-[11.5px] font-bold hover:brightness-105 transition">Bagikan ke Grup WA</a>
      <a href="{{ route('hazard.pengingat') }}"
         class="ml-auto rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11.5px] font-bold text-amber-700 hover:bg-amber-100 transition">Pengingat PIC →</a>
    </div>
  </form>

  {{-- Daftar --}}
  <div class="space-y-2.5">
    @forelse($reports as $r)
      <a href="{{ route('hazard.show', $r) }}" class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ $r->kode }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white" style="background: {{ Hazard::WARNA_RISIKO[$r->risiko] ?? '#a8a29e' }}">{{ $r->risiko }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white" style="background: {{ Hazard::WARNA_STATUS[$r->status] ?? '#a8a29e' }}">{{ $r->status }}</span>
              @if($r->kategori)<span class="text-[10px] font-semibold bg-stone-100 text-stone-500 px-2 py-0.5 rounded-full">{{ $r->kategori }}</span>@endif
            </div>
            <p class="text-[13.5px] font-semibold text-cam-ink mt-2 clamp-2 leading-relaxed">{{ $r->deskripsi }}</p>
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11.5px] text-stone-400 mt-2">
              <span>📍 {{ $r->lokasi ?: '—' }}</span>
              <span>👤 {{ $r->pelapor_nama }}</span>
              <span>{{ optional($r->tanggal)->format('d M Y') }}</span>
            </div>
            {{-- Ditujukan kepada — memudahkan penanggung jawab menutup temuan --}}
            <div class="mt-2 inline-flex items-center gap-1.5 bg-stone-50 rounded-lg px-2.5 py-1.5">
              <svg class="w-3.5 h-3.5 text-stone-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
              <span class="text-[11.5px] text-stone-400">Ditujukan kepada</span>
              <span class="text-[12px] font-bold text-cam-ink">{{ $r->company?->name ?: ($r->terlapor ?: '— belum diisi —') }}</span>
              @if($r->terlapor && $r->company)
                <span class="text-[11px] text-stone-400">· {{ $r->terlapor }}</span>
              @endif
            </div>
          </div>
          @if($r->foto && count($r->foto))
            <img src="{{ asset('storage/'.$r->foto[0]) }}" class="w-20 h-20 object-cover rounded-xl shrink-0">
          @endif
        </div>
      </a>
    @empty
      @php $adaFilter = collect($f)->filter()->isNotEmpty(); @endphp
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        @if($adaFilter)
          <p class="text-[13px] text-stone-400">Tidak ada laporan yang cocok dengan filter.</p>
          <a href="{{ route('hazard.index') }}" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">Hapus filter →</a>
        @else
          <p class="text-[13px] text-stone-400">Belum ada laporan bahaya.</p>
          <a href="{{ route('hazard.create') }}" class="inline-block mt-3 text-[12.5px] font-bold text-cam-lime-deep hover:underline">Buat laporan pertama →</a>
        @endif
      </div>
    @endforelse
  </div>

  <div>{{ $reports->links() }}</div>
</div>
@endsection
