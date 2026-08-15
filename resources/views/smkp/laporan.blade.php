@extends('layouts.cetak')
@section('title','Laporan Audit SMKP '.$audit->tahun)

@php
  // Temuan dipenggal per lembar; jumlah halamannya karena itu ikut jumlah
  // temuan, bukan tetap. Nomor pada kop dihitung dari pemenggalan ini.
  $per    = 6;
  $bagian = $temuan->count() ? $temuan->chunk($per) : collect([collect()]);
  $DARI   = 1 + $bagian->count();
  $h2     = 'text-[12.5px] font-bold text-cam-ink mb-2';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">


  {{-- ══════════ Lembar 1 — identitas dan rekapitulasi nilai ══════════ --}}
  <x-lembar :dok="$dok" :halaman="1" :dari="$DARI">

    <header class="text-center border-b border-stone-200 pb-4 mb-5">
      <h1 class="font-display text-[17px] font-black text-cam-ink leading-tight uppercase">Laporan Audit Internal</h1>
      <h2 class="font-display text-[13px] font-bold text-cam-ink mt-0.5 uppercase">Penerapan Sistem Manajemen Keselamatan Pertambangan Mineral dan Batubara</h2>
      <p class="text-[10.5px] text-stone-400 mt-1.5">{{ $meta['basis'] ?? 'Kepdirjen Minerba Nomor 185.K/37.04/DJB/2019 — Lampiran II' }}</p>
    </header>

    <section class="mb-5">
      <h3 class="{{ $h2 }}">Data Auditi</h3>
      <table class="w-full text-[12px]">
        <tbody class="divide-y divide-stone-100">
          @foreach ([
            ['Nama perusahaan auditi', $audit->company?->name ?? 'Seluruh Perusahaan'],
            ['Jenis perizinan',        $audit->company?->izin_type ?: '—'],
            ['Jenis komoditas',        $audit->company?->commodity ?: '—'],
            ['Alamat perusahaan',      $audit->company?->address ?: ($audit->company?->location ?: '—')],
            ['Periode audit',          $audit->tanggal_mulai
                                        ? $audit->tanggal_mulai->translatedFormat('d F Y').($audit->tanggal_selesai ? ' – '.$audit->tanggal_selesai->translatedFormat('d F Y') : '')
                                        : 'Tahun '.$audit->tahun],
            ['Ketua tim audit',        $audit->ketua_auditor ?: '—'],
          ] as [$k,$v])
            <tr>
              <td class="py-1.5 pr-3 text-stone-500 w-56 align-top">{{ $k }}</td>
              <td class="py-1.5 text-cam-ink whitespace-pre-line">{{ $v }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>

    <section class="mb-5">
      <h3 class="{{ $h2 }}">Hasil Penilaian</h3>
      <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-stone-200 p-4">
        <div>
          <div class="text-[10.5px] text-stone-400">Nilai akhir</div>
          <div class="stat stat-lg leading-none mt-1" style="color:{{ $rekap['tingkat']['warna'] }}">{{ number_format($rekap['skor'],2) }}</div>
        </div>
        <div class="text-right">
          <div class="text-[10.5px] text-stone-400">Tingkat penerapan</div>
          <div class="text-[16px] font-black mt-1" style="color:{{ $rekap['tingkat']['warna'] }}">{{ $rekap['tingkat']['label'] }}</div>
        </div>
      </div>
    </section>

    <section>
      <h3 class="{{ $h2 }}">Rekapitulasi per Elemen</h3>
      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[520px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold">Elemen</th>
              <th class="py-1.5 px-2 font-semibold num w-16">Bobot</th>
              <th class="py-1.5 px-2 font-semibold num w-24">Dinilai</th>
              <th class="py-1.5 px-2 font-semibold num w-20">Capaian</th>
              <th class="py-1.5 pl-2 font-semibold num w-16">Nilai</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($elemen as $e)
              @php $r = $rekap['elemen'][$e['kode']]; @endphp
              <tr>
                <td class="py-1.5 pr-2 text-cam-ink">{{ $e['kode'] }}. {{ $e['nama'] }}</td>
                <td class="py-1.5 px-2 num text-stone-500">{{ $r['bobot'] }}</td>
                <td class="py-1.5 px-2 num text-stone-500">{{ $r['dinilai'] }}/{{ $r['berlaku'] }}</td>
                <td class="py-1.5 px-2 num text-stone-500">{{ number_format($r['capaian']*100,1) }}%</td>
                <td class="py-1.5 pl-2 num font-bold text-cam-ink">{{ number_format($r['skor'],2) }}</td>
              </tr>
            @endforeach
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-stone-300 font-bold text-cam-ink">
              <td class="py-1.5 pr-2">Total</td>
              <td class="py-1.5 px-2 num">{{ $rekap['bobotTerpakai'] }}</td>
              <td class="py-1.5 px-2 num">{{ $rekap['dinilai'] }}/{{ $rekap['berlaku'] }}</td>
              {{-- Capaian keseluruhan sudah berbobot, jadi angkanya sama dengan nilai akhir. --}}
              <td class="py-1.5 px-2 num">{{ number_format($rekap['skor'],1) }}%</td>
              <td class="py-1.5 pl-2 num">{{ number_format($rekap['skor'],2) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>
  </x-lembar>

  {{-- ══════════ Lembar temuan ══════════ --}}
  @foreach($bagian as $i => $kelompok)
    <x-lembar :dok="$dok" :halaman="$i + 2" :dari="$DARI" :akhir="$loop->last">

      @if($loop->first)
        <h3 class="{{ $h2 }}">Daftar Temuan ({{ $temuan->count() }})</h3>
      @else
        <h3 class="{{ $h2 }}">Daftar Temuan (lanjutan)</h3>
      @endif

      @forelse($kelompok as $t)
        @php $warna = $t->jenis === 'mayor' ? '#E5484D' : ($t->jenis === 'minor' ? '#F0921E' : '#9AA3AE'); @endphp
        <div class="border border-stone-200 rounded-xl p-3.5 mb-2">
          <div class="flex flex-wrap items-center gap-2 mb-1.5">
            <span class="num text-[10.5px] font-bold text-stone-400">{{ $t->kode_kriteria }}</span>
            <span class="text-[9px] font-bold uppercase tracking-wide text-white px-1.5 py-0.5 rounded" style="background:{{ $warna }}">{{ $t->jenis }}</span>
            <span class="text-[9px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded
                  {{ $t->status === 'Closed' ? 'bg-cam-lime-soft text-cam-lime-deep' : 'bg-stone-100 text-stone-500' }}">{{ $t->status }}</span>
          </div>
          <p class="text-[12px] text-cam-ink leading-relaxed">{{ $t->uraian }}</p>
          @if($t->akar_masalah)<p class="text-[11px] text-stone-500 mt-1"><span class="font-bold">Akar masalah:</span> {{ $t->akar_masalah }}</p>@endif
          @if($t->tindakan)<p class="text-[11px] text-stone-500 mt-0.5"><span class="font-bold">Tindakan:</span> {{ $t->tindakan }}</p>@endif
          @if($t->penanggung_jawab || $t->target_selesai)
            <p class="text-[10.5px] text-stone-400 mt-1">
              {{ $t->penanggung_jawab }}@if($t->target_selesai) · target {{ $t->target_selesai->translatedFormat('d F Y') }}@endif
            </p>
          @endif
        </div>
      @empty
        <p class="text-[12px] text-stone-400">Tidak ada temuan tercatat.</p>
      @endforelse

      @if($loop->last)
        <section class="pt-8 grid gap-8 sm:grid-cols-2 text-center text-[11.5px]">
          <div>
            <p class="text-stone-500">Ketua Tim Audit</p>
            <div class="h-14"></div>
            <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">{!! $audit->ketua_auditor ?: '&nbsp;' !!}</p>
          </div>
          <div>
            <p class="text-stone-500">Kepala Teknik Tambang</p>
            <div class="h-14"></div>
            <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">{!! $audit->rencana['pengesahan']['ktt']['nama'] ?? ($audit->company?->ktt ?: '&nbsp;') !!}</p>
          </div>
        </section>
      @endif
    </x-lembar>
  @endforeach
</div>
@endsection
