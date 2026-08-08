@extends('layouts.app')
@section('title','Laporan Audit SMKP '.$audit->tahun)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <div class="print:hidden flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('smkp.show',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Ringkasan</a>
    <button onclick="window.print()"
            class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Cetak</button>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-7 space-y-6">

    {{-- Kepala laporan --}}
    <header class="text-center border-b border-stone-100 pb-5">
      <h1 class="font-display text-[22px] font-black text-cam-ink leading-tight">Laporan Audit SMKP Minerba</h1>
      <p class="text-[12px] text-stone-400 mt-1.5">{{ $meta['basis'] ?? '' }}</p>
      <p class="text-[13.5px] font-bold text-cam-ink mt-3">{{ $audit->company?->name ?? 'Seluruh Perusahaan' }}</p>
      <p class="text-[12px] text-stone-500">
        Periode {{ $audit->tahun }}
        @if($audit->tanggal_mulai)
          · {{ $audit->tanggal_mulai->format('d M Y') }}@if($audit->tanggal_selesai) – {{ $audit->tanggal_selesai->format('d M Y') }}@endif
        @endif
      </p>
      @if($audit->ketua_auditor)
        <p class="text-[12px] text-stone-500">Ketua auditor: {{ $audit->ketua_auditor }}</p>
      @endif
    </header>

    {{-- Hasil akhir --}}
    <section>
      <h2 class="text-[14px] font-bold text-cam-ink mb-3">Hasil Penilaian</h2>
      <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-stone-100 p-5">
        <div>
          <div class="text-[11px] text-stone-400">Skor akhir</div>
          <div class="stat stat-lg leading-none mt-1" style="color:{{ $rekap["tingkat"]['warna'] }}">{{ number_format($rekap['skor'],2) }}</div>
        </div>
        <div class="text-right">
          <div class="text-[11px] text-stone-400">Predikat</div>
          <div class="text-[17px] font-black mt-1" style="color:{{ $rekap["tingkat"]['warna'] }}">{{ $rekap["tingkat"]['label'] }}</div>
        </div>
      </div>
    </section>

    {{-- Rekap per elemen --}}
    <section>
      <h2 class="text-[14px] font-bold text-cam-ink mb-3">Rekapitulasi per Elemen</h2>
      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[560px]">
          <thead>
            <tr class="border-b border-stone-200 text-left text-stone-400">
              <th class="py-2 pr-3 font-semibold">Elemen</th>
              <th class="py-2 px-3 font-semibold num">Bobot</th>
              <th class="py-2 px-3 font-semibold num">Dinilai</th>
              <th class="py-2 px-3 font-semibold num">Capaian</th>
              <th class="py-2 pl-3 font-semibold num">Skor</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($elemen as $e)
              @php $r = $rekap['elemen'][$e['kode']]; @endphp
              <tr>
                <td class="py-2.5 pr-3 text-cam-ink">{{ $e['kode'] }}. {{ $e['nama'] }}</td>
                <td class="py-2.5 px-3 num text-stone-500">{{ $r['bobot'] }}</td>
                <td class="py-2.5 px-3 num text-stone-500">{{ $r['dinilai'] }}/{{ $r['berlaku'] }}</td>
                <td class="py-2.5 px-3 num text-stone-500">{{ number_format($r['capaian']*100,1) }}%</td>
                <td class="py-2.5 pl-3 num font-bold text-cam-ink">{{ number_format($r['skor'],2) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-stone-200 font-bold text-cam-ink">
              <td class="py-2.5 pr-3">Total</td>
              <td class="py-2.5 px-3 num">{{ $rekap['bobotTerpakai'] }}</td>
              <td class="py-2.5 px-3 num">{{ $rekap['dinilai'] }}/{{ $rekap['berlaku'] }}</td>
              {{-- Capaian keseluruhan sudah berbobot, jadi angkanya sama dengan nilai akhir. --}}
              <td class="py-2.5 px-3 num">{{ number_format($rekap['skor'],1) }}%</td>
              <td class="py-2.5 pl-3 num">{{ number_format($rekap['skor'],2) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    {{-- Daftar temuan --}}
    <section>
      <h2 class="text-[14px] font-bold text-cam-ink mb-3">Daftar Temuan ({{ $temuan->count() }})</h2>
      @forelse($temuan as $t)
        @php $warna = $t->jenis === 'mayor' ? '#E5484D' : ($t->jenis === 'minor' ? '#F0921E' : '#9AA3AE'); @endphp
        <div class="border border-stone-100 rounded-xl p-4 mb-2">
          <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="num text-[11px] font-bold text-stone-400">{{ $t->kode_kriteria }}</span>
            <span class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded" style="background:{{ $warna }}">{{ $t->jenis }}</span>
            <span class="text-[9.5px] font-bold uppercase tracking-wide px-2 py-0.5 rounded
                  {{ $t->status === 'Closed' ? 'bg-cam-lime-soft text-cam-lime-deep' : 'bg-stone-100 text-stone-500' }}">{{ $t->status }}</span>
          </div>
          <p class="text-[12.5px] text-cam-ink leading-relaxed">{{ $t->uraian }}</p>
          @if($t->akar_masalah)<p class="text-[11.5px] text-stone-500 mt-1.5"><span class="font-bold">Akar masalah:</span> {{ $t->akar_masalah }}</p>@endif
          @if($t->tindakan)<p class="text-[11.5px] text-stone-500 mt-1"><span class="font-bold">Tindakan:</span> {{ $t->tindakan }}</p>@endif
          @if($t->penanggung_jawab || $t->target_selesai)
            <p class="text-[11px] text-stone-400 mt-1.5">
              {{ $t->penanggung_jawab }}@if($t->target_selesai) · target {{ $t->target_selesai->format('d M Y') }}@endif
            </p>
          @endif
        </div>
      @empty
        <p class="text-[12.5px] text-stone-400">Tidak ada temuan tercatat.</p>
      @endforelse
    </section>

    {{-- Tanda tangan --}}
    <section class="pt-6 grid gap-8 sm:grid-cols-2 text-center text-[12px]">
      <div>
        <p class="text-stone-500">Ketua Auditor</p>
        <div class="h-16"></div>
        <p class="font-bold text-cam-ink border-t border-stone-300 pt-1.5">{{ $audit->ketua_auditor ?: '&nbsp;' }}</p>
      </div>
      <div>
        <p class="text-stone-500">Kepala Teknik Tambang</p>
        <div class="h-16"></div>
        <p class="font-bold text-cam-ink border-t border-stone-300 pt-1.5">&nbsp;</p>
      </div>
    </section>
  </div>
</div>
@endsection
