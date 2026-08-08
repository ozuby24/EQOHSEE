@extends('layouts.app')
@section('title','Daftar Hadir '.$judul.' — Audit SMKP '.$audit->tahun)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <div class="print:hidden flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('smkp.rapat',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Rapat</a>
    <button onclick="window.print()"
            class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Cetak</button>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-7 space-y-6">

    <header class="text-center border-b border-stone-100 pb-5">
      <h1 class="font-display text-[19px] font-black text-cam-ink leading-tight">Daftar Hadir {{ $judul }}</h1>
      <h2 class="font-display text-[14px] font-bold text-cam-ink mt-0.5">Audit Internal Sistem Manajemen Keselamatan Pertambangan</h2>
      <p class="text-[13px] font-bold text-cam-ink mt-3">{{ $audit->company?->name ?? 'Seluruh Perusahaan' }}</p>
      <p class="text-[12px] text-stone-500">
        Periode Audit {{ $audit->tahun }}
        @if($audit->tanggal_mulai)
          · {{ $audit->tanggal_mulai->translatedFormat('d F Y') }}@if($audit->tanggal_selesai) &ndash; {{ $audit->tanggal_selesai->translatedFormat('d F Y') }}@endif
        @endif
      </p>
    </header>

    <div class="tabel-scroll">
      <table class="w-full text-[12px] min-w-[560px]">
        <thead>
          <tr class="border-b border-stone-200 text-left text-stone-400">
            <th class="py-2 pr-3 font-semibold w-8 num">No</th>
            <th class="py-2 px-3 font-semibold">Nama</th>
            <th class="py-2 px-3 font-semibold w-44">Jabatan</th>
            <th class="py-2 px-3 font-semibold w-44">Perusahaan</th>
            <th class="py-2 pl-3 font-semibold w-40">Tanda Tangan</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
          @forelse($hadir as $h)
            <tr>
              <td class="py-3 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
              <td class="py-3 px-3 font-semibold text-cam-ink">{{ $h->nama }}</td>
              <td class="py-3 px-3 text-stone-600">{{ $h->jabatan }}</td>
              <td class="py-3 px-3 text-stone-600">{{ $h->perusahaan }}</td>
              <td class="py-3 pl-3"><div class="h-8 border-b border-dashed border-stone-300"></div></td>
            </tr>
          @empty
            <tr><td colspan="5" class="py-8 text-center text-stone-400 text-[12.5px]">Belum ada peserta tercatat.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <section class="pt-6 text-center text-[12px]">
      <p class="text-stone-500">Ketua Tim Audit</p>
      <div class="h-16"></div>
      <p class="font-bold text-cam-ink border-t border-stone-300 pt-1.5 inline-block px-12">{!! $audit->ketua_auditor ?: '&nbsp;' !!}</p>
    </section>
  </div>
</div>
@endsection
