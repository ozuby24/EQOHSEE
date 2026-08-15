@extends('layouts.cetak')
@section('title','Daftar Hadir '.$judul.' — Audit SMKP '.$audit->tahun)

@php
  // Daftar peserta dipenggal per lembar agar tabelnya tidak terpotong di
  // tengah baris saat dicetak, dan nomor halaman pada kop tetap benar.
  $per     = 16;
  $lembar  = $hadir->count() ? $hadir->chunk($per) : collect([collect()]);
  $DARI    = $lembar->count();
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">


  @foreach($lembar as $i => $bagian)
    <x-lembar :dok="$dok" :halaman="$i + 1" :dari="$DARI" :akhir="$loop->last">

      @if($loop->first)
        <header class="text-center border-b border-stone-200 pb-4 mb-5">
          <h1 class="font-display text-[16px] font-black text-cam-ink leading-tight uppercase">Daftar Hadir {{ $judul }}</h1>
          <h2 class="font-display text-[13px] font-bold text-cam-ink mt-0.5 uppercase">Audit Internal Sistem Manajemen Keselamatan Pertambangan</h2>
          <p class="text-[12px] font-bold text-cam-ink mt-2.5">{{ $audit->company?->name ?? 'Seluruh Perusahaan' }}</p>
          <p class="text-[11.5px] text-stone-500">
            Periode Audit {{ $audit->tahun }}
            @if($audit->tanggal_mulai)
              · {{ $audit->tanggal_mulai->translatedFormat('d F Y') }}@if($audit->tanggal_selesai) &ndash; {{ $audit->tanggal_selesai->translatedFormat('d F Y') }}@endif
            @endif
          </p>
        </header>
      @endif

      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[520px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
              <th class="py-1.5 px-2 font-semibold">Nama</th>
              <th class="py-1.5 px-2 font-semibold w-40">Jabatan</th>
              <th class="py-1.5 px-2 font-semibold w-40">Perusahaan</th>
              <th class="py-1.5 pl-2 font-semibold w-36">Tanda Tangan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @forelse($bagian as $h)
              <tr>
                <td class="py-2.5 pr-2 num text-stone-400">{{ $i * $per + $loop->iteration }}</td>
                <td class="py-2.5 px-2 font-semibold text-cam-ink">{{ $h->nama }}</td>
                <td class="py-2.5 px-2 text-stone-600">{{ $h->jabatan }}</td>
                <td class="py-2.5 px-2 text-stone-600">{{ $h->perusahaan }}</td>
                <td class="py-2.5 pl-2"><div class="h-7 border-b border-dashed border-stone-300"></div></td>
              </tr>
            @empty
              <tr><td colspan="5" class="py-8 text-center text-stone-400 text-[12px]">Belum ada peserta tercatat.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($loop->last)
        <section class="pt-6 text-center text-[11.5px]">
          <p class="text-stone-500">Ketua Tim Audit</p>
          <div class="h-14"></div>
          <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5 inline-block px-12">{!! $audit->ketua_auditor ?: '&nbsp;' !!}</p>
        </section>
      @endif
    </x-lembar>
  @endforeach
</div>
@endsection
