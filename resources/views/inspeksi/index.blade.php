@extends('layouts.app')
@section('title','Daftar Inspeksi')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <form method="GET" class="bg-white rounded-2xl shadow-card border border-stone-100 p-3 flex flex-wrap items-center gap-2">
    <select name="template" onchange="this.form.submit()" class="ring-focus flex-1 min-w-[160px] rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
      <option value="">Semua jenis inspeksi</option>
      @foreach($templates as $t)<option value="{{ $t->id }}" @selected($template==$t->id)>{{ $t->nama }}</option>@endforeach
    </select>
    <select name="status" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] font-semibold text-stone-600">
      <option value="">Semua status</option>
      <option value="Berjalan" @selected($status==='Berjalan')>Berjalan</option>
      <option value="Selesai"  @selected($status==='Selesai')>Selesai</option>
    </select>
    <a href="{{ route('inspeksi.template.index') }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Jenis Inspeksi</a>
    <a href="{{ route('inspeksi.create') }}" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">+ Inspeksi Baru</a>

    <div class="w-full flex flex-wrap items-center gap-2 mt-1 pt-2.5 border-t border-stone-100">
      <span class="text-[11px] font-bold uppercase tracking-wide text-stone-400 px-1">Ekspor</span>
      <a href="{{ route('inspeksi.ekspor.csv', request()->query()) }}"
         class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">⤓ Excel (CSV)</a>
      <a href="{{ route('inspeksi.ekspor.cetak', request()->query()) }}" target="_blank" rel="noopener"
         class="rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-600 hover:border-cam-lime hover:bg-cam-lime-soft transition">⎙ PDF</a>
      <a href="{{ route('hazard.evaluasi') }}"
         class="ml-auto rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-cam-lime-deep hover:bg-cam-lime-soft transition">Evaluasi Temuan →</a>
    </div>
  </form>

  <div class="space-y-2.5">
    @forelse($inspections as $i)
      @php $r = $i->ringkas(); @endphp
      <a href="{{ route('inspeksi.show', $i) }}" class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 transition">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-bold bg-cam-ink text-white px-2 py-0.5 rounded num">{{ $i->kode }}</span>
              @if($i->template)<span class="text-[10px] font-semibold bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded-full">{{ $i->template->nama }}</span>@endif
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white {{ $i->status === 'Selesai' ? 'bg-cam-lime' : 'bg-amber-500' }}">{{ $i->status }}</span>
            </div>
            <h3 class="text-[14px] font-bold text-cam-ink mt-2">{{ $i->judul }}</h3>
            <div class="text-[11.5px] text-stone-400 mt-1">
              📍 {{ $i->lokasi ?: '—' }} · {{ optional($i->tanggal)->format('d M Y') }} ·
              <span class="num">{{ $i->inspectors->count() }}</span> inspektur
            </div>
            @if($r['total'])
              <div class="flex gap-3 mt-2 text-[11px]">
                <span class="text-cam-lime-deep font-semibold num">{{ $r['sesuai'] }} sesuai</span>
                <span class="text-red-500 font-semibold num">{{ $r['tidak'] }} tidak sesuai</span>
                @if($r['belum'])<span class="text-stone-400 num">{{ $r['belum'] }} belum diisi</span>@endif
              </div>
            @endif
          </div>
          <div class="text-right shrink-0">
            <div class="stat stat-sm {{ $r['total'] && $r['belum']===0 ? 'text-cam-lime-deep' : 'text-stone-300' }}">
              {{ $r['total'] ? round(($r['total']-$r['belum'])/$r['total']*100) : 0 }}<span class="stat-unit">%</span>
            </div>
            <div class="text-[9.5px] uppercase tracking-wide text-stone-400 mt-1">terisi</div>
          </div>
        </div>
      </a>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
        <p class="text-[13px] text-stone-400">Belum ada inspeksi.</p>
        <p class="text-[12px] text-stone-300 mt-1">Buat <a href="{{ route('inspeksi.template.index') }}" class="text-cam-lime-deep font-bold hover:underline">jenis inspeksi</a> dulu, lalu jalankan inspeksinya.</p>
      </div>
    @endforelse
  </div>

  <div>{{ $inspections->links() }}</div>
</div>
@endsection
