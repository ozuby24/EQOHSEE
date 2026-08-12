@extends('layouts.app')
@section('title','Audit SMKP '.$audit->tahun)

@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif
  @if($errors->any())<div class="rounded-xl bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-[12.5px] font-medium">{{ $errors->first() }}</div>@endif

  {{-- Ringkasan skor --}}
  <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <div class="flex flex-wrap items-start justify-between gap-5">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="text-[16px] font-bold text-cam-ink">{{ $audit->judul ?: 'Audit SMKP '.$audit->tahun }}</h2>
          <span class="text-[9.5px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                {{ $audit->status === 'selesai' ? 'bg-cam-lime-soft text-cam-lime-deep' : ($audit->status === 'berjalan' ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-400') }}">{{ $audit->status }}</span>
        </div>
        <p class="text-[12px] text-stone-400 mt-1">
          {{ $audit->company?->name ?? 'Seluruh perusahaan' }}
          @if($audit->ketua_auditor) · Ketua auditor: {{ $audit->ketua_auditor }} @endif
          @if($audit->tanggal_mulai) · {{ $audit->tanggal_mulai->format('d M Y') }}@if($audit->tanggal_selesai) – {{ $audit->tanggal_selesai->format('d M Y') }}@endif @endif
        </p>
      </div>
      <div class="text-right shrink-0">
        <div class="stat stat-lg leading-none" style="color:{{ $rekap["tingkat"]['warna'] }}">{{ number_format($rekap['skor'],2) }}</div>
        <div class="text-[11px] font-bold mt-1" style="color:{{ $rekap["tingkat"]['warna'] }}">{{ $rekap["tingkat"]['label'] }}</div>
        <div class="text-[10.5px] text-stone-400 mt-0.5">dari 100</div>
      </div>
    </div>

    <div class="mt-4 h-2 rounded-full bg-stone-100 overflow-hidden">
      <div class="h-full rounded-full transition-all" style="width: {{ min(100,$rekap['skor']) }}%; background: {{ $rekap["tingkat"]['warna'] }}"></div>
    </div>

    @php $t = \App\Support\Smkp::hitungTemuan($audit->hasil ?? []); @endphp
    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4 mt-5 pt-5 border-t border-stone-100">
      @foreach ([
        ['Kriteria dinilai', $rekap['dinilai'].'/'.$rekap['berlaku'], '#0F1720'],
        ['Ketidaksesuaian mayor', $t['mayor'], '#E5484D'],
        ['Ketidaksesuaian minor', $t['minor'], '#F0921E'],
        ['Temuan belum ditutup', $temuan->where('status','<>','Closed')->count(), '#F57C00'],
      ] as [$l,$v,$c])
        <div>
          <div class="stat stat-sm leading-none" style="color:{{ $c }}">{{ $v }}</div>
          <div class="text-[11px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>

    <div class="flex flex-wrap gap-2 mt-5">
      <a href="{{ route('smkp.temuan',$audit) }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Temuan &amp; Tindakan</a>
      <a href="{{ route('smkp.laporan',$audit) }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Laporan</a>
      <a href="{{ route('smkp.edit',$audit) }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Ubah</a>
    </div>
  </section>

  {{-- ══════════ Alur audit: empat babak, masing-masing langkahnya sendiri ══════════ --}}
  <div class="flex flex-wrap items-end justify-between gap-3 pt-1">
    <div>
      <h3 class="font-display text-[19px] font-black text-cam-ink leading-tight">Alur Audit</h3>
      <p class="text-[12px] text-stone-500 mt-0.5">
        Sedang berjalan: <span class="font-bold text-cam-lime-deep">{{ \App\Support\SmkpTahap::labelTahap($audit->tahap ?? 1) }}</span>
      </p>
    </div>
    @if(($audit->tahap ?? 1) < \App\Support\SmkpTahap::PELAPORAN)
      <form method="POST" action="{{ route('smkp.tahap',$audit) }}" class="shrink-0">
        @csrf
        <input type="hidden" name="tahap" value="{{ ($audit->tahap ?? 1) + 1 }}">
        <button class="rounded-xl border border-cam-sand-dark/60 bg-cam-sand/40 px-4 py-2.5 text-[12.5px] font-bold text-cam-ink hover:bg-cam-sand transition">
          Lanjut ke {{ \App\Support\SmkpTahap::labelTahap(($audit->tahap ?? 1) + 1) }} →
        </button>
      </form>
    @endif
  </div>

  @foreach($alur as $babak)
    @php
      // Kemajuan babak dihitung dari langkah kerjanya saja; berkas cetak
      // selalu tersedia sehingga menghitungnya akan menaikkan angka semu.
      $kerja   = array_filter($babak['langkah'], fn($l) => $l['jenis'] === 'kerja');
      $selesai = count(array_filter($kerja, fn($l) => $status[$l['kunci']]['selesai'] ?? false));
      $rasio   = count($kerja) ? $selesai / count($kerja) : 0;
    @endphp

    <section class="kartu-lux rounded-2xl overflow-hidden">
      {{-- Kepala babak --}}
      <div class="px-5 py-4 hairline flex flex-wrap items-center gap-3">
        <span class="shrink-0 text-[10px] font-black uppercase tracking-[0.14em] text-white px-2.5 py-1 rounded-lg"
              style="background:{{ $babak['warna'] }}">{{ $babak['nomor'] }}</span>
        <div class="min-w-0 flex-1">
          <h4 class="text-[14.5px] font-bold text-cam-ink leading-tight">{{ $babak['judul'] }}</h4>
          <p class="text-[11.5px] text-stone-500 mt-0.5 leading-relaxed">{{ $babak['ket'] }}</p>
        </div>
        <div class="shrink-0 text-right">
          <div class="num text-[13px] font-bold" style="color:{{ $babak['warna'] }}">{{ $selesai }}/{{ count($kerja) }}</div>
          <div class="text-[10px] text-stone-400">langkah</div>
        </div>
      </div>

      <div class="h-1 bg-stone-100">
        <div class="h-full transition-all duration-700" style="width: {{ $rasio*100 }}%; background:{{ $babak['warna'] }}"></div>
      </div>

      {{-- Langkah --}}
      <ol class="divide-y divide-stone-100">
        @foreach($babak['langkah'] as $l)
          @php
            $s    = $status[$l['kunci']] ?? ['selesai'=>false,'ket'=>''];
            $cetak= $l['jenis'] === 'cetak';
            $url  = isset($l['arg']) ? route($l['rute'], [$audit, $l['arg']]) : route($l['rute'], $audit);
          @endphp
          <li>
            <a href="{{ $url }}" class="flex items-start gap-3.5 px-5 py-3.5 hover:bg-cam-sand/25 transition group">
              {{-- Penanda langkah --}}
              <span class="shrink-0 mt-0.5 w-6 h-6 rounded-full grid place-items-center text-[10.5px] font-bold
                    {{ $s['selesai'] ? 'text-white' : 'text-stone-400 bg-stone-100' }}"
                    @if($s['selesai']) style="background:{{ $babak['warna'] }}" @endif>
                @if($s['selesai'])✓@else{{ $loop->iteration }}@endif
              </span>

              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-[12.5px] font-bold text-cam-ink">{{ $l['judul'] }}</span>
                  @if($cetak)
                    <span class="text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded bg-cam-sand text-cam-ink/70">Cetak</span>
                  @endif
                </div>
                <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">{{ $l['ket'] }}</p>
                @if($s['ket'])
                  <p class="text-[11px] mt-1.5 font-semibold {{ $s['selesai'] ? 'text-cam-lime-deep' : 'text-stone-400' }}">{{ $s['ket'] }}</p>
                @endif
              </div>

              <span class="shrink-0 self-center text-stone-300 group-hover:text-cam-coral transition text-[15px] leading-none">→</span>
            </a>
          </li>
        @endforeach
      </ol>
    </section>
  @endforeach

  {{-- Skor per elemen --}}
  <div>
    <h3 class="text-[15px] font-bold text-cam-ink mb-3">Tujuh Elemen</h3>
    <div class="space-y-2.5">
      @foreach($elemen as $e)
        @php $r = $rekap['elemen'][$e['kode']]; @endphp
        <a href="{{ route('smkp.nilai',[$audit,$e['kode']]) }}"
           class="block bg-white rounded-2xl shadow-card border border-stone-100 p-5 hover:border-cam-lime/40 transition">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
              <div class="text-[13.5px] font-bold text-cam-ink">{{ $e['kode'] }}. {{ $e['nama'] }}</div>
              <div class="text-[11px] text-stone-400 mt-0.5">
                bobot {{ $r['bobot'] }} · {{ $r['dinilai'] }}/{{ $r['berlaku'] }} butir dinilai
                @if($r['total'] > $r['berlaku']) · {{ $r['total'] - $r['berlaku'] }} tidak berlaku @endif
              </div>
            </div>
            <div class="text-right shrink-0">
              <div class="num text-[15px] font-bold text-cam-ink">{{ number_format($r['skor'],2) }}</div>
              <div class="text-[10.5px] text-stone-400">{{ number_format($r['capaian']*100,0) }}% capaian</div>
            </div>
          </div>
          <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full lime-gradient transition-all" style="width: {{ $r['capaian']*100 }}%"></div>
          </div>
        </a>
      @endforeach
    </div>
  </div>
</div>
@endsection
