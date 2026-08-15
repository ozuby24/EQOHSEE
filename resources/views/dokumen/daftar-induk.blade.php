@extends('layouts.cetak')
@section('title','Daftar Induk Dokumen')

@php
  // Dipenggal per lembar agar barisnya tidak terpotong saat dicetak dan
  // nomor halaman pada kop tetap cocok dengan halaman kertasnya.
  $per     = 18;
  $lembar  = $documents->count() ? $documents->chunk($per) : collect([collect()]);
  $DARI    = $lembar->count();
  $tgl     = fn ($v) => $v ? $v->translatedFormat('d M Y') : '—';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">

  @foreach($lembar as $i => $bagian)
    <x-lembar :dok="$dok" :halaman="$i + 1" :dari="$DARI" :akhir="$loop->last">

      @if($loop->first)
        <header class="text-center border-b border-stone-200 pb-4 mb-5">
          <h1 class="font-display text-[16px] font-black text-cam-ink leading-tight uppercase">Daftar Induk Dokumen Terkendali</h1>
          <p class="text-[11.5px] text-stone-500 mt-2">
            <span class="num font-bold text-cam-ink">{{ $documents->count() }}</span> dokumen terdaftar,
            diurutkan menurut tingkat pada piramida dokumen.
          </p>
        </header>
      @endif

      <div class="tabel-scroll">
        <table class="w-full text-[11px] min-w-[640px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
              <th class="py-1.5 px-2 font-semibold w-28">Nomor</th>
              <th class="py-1.5 px-2 font-semibold">Judul</th>
              <th class="py-1.5 px-2 font-semibold w-24">Jenis</th>
              <th class="py-1.5 px-2 font-semibold w-12 num">Rev</th>
              <th class="py-1.5 px-2 font-semibold w-24">Berlaku</th>
              <th class="py-1.5 px-2 font-semibold w-24">Tinjau</th>
              <th class="py-1.5 pl-2 font-semibold w-20">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @forelse($bagian as $d)
              <tr>
                <td class="py-2 pr-2 num text-stone-400">{{ $i * $per + $loop->iteration }}</td>
                <td class="py-2 px-2 num font-semibold text-cam-ink">{{ $d->kode }}</td>
                <td class="py-2 px-2 text-cam-ink">{{ $d->judul }}</td>
                <td class="py-2 px-2 text-stone-600">{{ $d->jenis }}</td>
                <td class="py-2 px-2 num text-stone-600">{{ $d->revisi }}</td>
                <td class="py-2 px-2 text-stone-600">{{ $tgl($d->tanggal_berlaku) }}</td>
                <td class="py-2 px-2 {{ $d->perluTinjau() ? 'text-amber-700 font-semibold' : 'text-stone-600' }}">{{ $tgl($d->tanggal_tinjau) }}</td>
                <td class="py-2 pl-2 font-semibold" style="color:{{ \App\Support\Dokumen::warna($d->status) }}">{{ ucfirst($d->status) }}</td>
              </tr>
            @empty
              <tr><td colspan="8" class="py-8 text-center text-stone-400 text-[12px]">Belum ada dokumen terdaftar.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($loop->last)
        <section class="pt-8 grid gap-8 sm:grid-cols-2 text-center text-[11.5px]">
          <div>
            <p class="text-stone-500">Pengendali Dokumen</p>
            <div class="h-14"></div>
            <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">&nbsp;</p>
          </div>
          <div>
            <p class="text-stone-500">Disetujui oleh</p>
            <div class="h-14"></div>
            <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">&nbsp;</p>
          </div>
        </section>
      @endif
    </x-lembar>
  @endforeach
</div>
@endsection
