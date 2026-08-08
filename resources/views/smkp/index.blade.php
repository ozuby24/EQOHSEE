@extends('layouts.app')
@section('title','Audit SMKP')

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-[15px] font-bold text-cam-ink">Audit Sistem Manajemen Keselamatan Pertambangan</h2>
        <p class="text-[12px] text-stone-400 mt-1 leading-relaxed">{{ $meta['basis'] ?? '' }} — 7 elemen, {{ \App\Support\Smkp::jumlahButir() }} butir penilaian · {{ \App\Support\Smkp::totalNilai() }} poin.</p>
      </div>
      <a href="{{ route('smkp.create') }}" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition shrink-0">+ Periode Audit</a>
    </div>
  </div>

  <div class="space-y-2.5">
    @forelse($audits as $a)
      @php $r = $a->rekap(); @endphp
      <a href="{{ route('smkp.show', $a) }}"
         class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 transition">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="num text-[13px] font-bold text-cam-ink">{{ $a->tahun }}</span>
              <span class="text-[9.5px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                    {{ $a->status === 'selesai' ? 'bg-cam-lime-soft text-cam-lime-deep' : ($a->status === 'berjalan' ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-400') }}">
                {{ $a->status }}
              </span>
            </div>
            <div class="text-[13.5px] font-bold text-cam-ink mt-1 clamp-1">{{ $a->judul ?: 'Audit SMKP '.$a->tahun }}</div>
            <div class="text-[11px] text-stone-400 mt-0.5 clamp-1">
              {{ $a->company?->name ?? 'Semua perusahaan' }}
              @if($a->ketua_auditor) · Ketua: {{ $a->ketua_auditor }} @endif
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-stone-400">
              <span>{{ $r['dinilai'] }}/{{ $r['berlaku'] }} kriteria dinilai</span>
              @if($a->findings_count)
                <span class="{{ $a->findings_open_count ? 'text-amber-600 font-semibold' : '' }}">
                  {{ $a->findings_open_count }} dari {{ $a->findings_count }} temuan belum ditutup
                </span>
              @endif
            </div>
          </div>

          <div class="text-right shrink-0">
            <div class="stat leading-none" style="color:{{ $r["tingkat"]['warna'] }}">{{ number_format($r['skor'],1) }}</div>
            <div class="text-[10.5px] font-bold mt-1" style="color:{{ $r["tingkat"]['warna'] }}">{{ $r["tingkat"]['label'] }}</div>
          </div>
        </div>

        <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full transition-all" style="width: {{ min(100, $r['skor']) }}%; background: {{ $r["tingkat"]['warna'] }}"></div>
        </div>
      </a>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center">
        <p class="text-[13px] text-stone-400">Belum ada periode audit.
          <a href="{{ route('smkp.create') }}" class="text-cam-lime-deep font-bold hover:underline">Buat yang pertama</a>.</p>
      </div>
    @endforelse
  </div>

  {{ $audits->links() }}
</div>
@endsection
