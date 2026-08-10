@extends('layouts.cetak')
@section('title','Matriks Pemenuhan '.$standar['nama'])

@php
  // Satu lembar per bab: bab bisa berisi belasan klausul dan tiap klausul
  // dapat memuat beberapa dokumen, jadi tingginya tidak dapat diperkirakan
  // dari jumlah babnya saja.
  $DARI = count($perBab);
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">

  @foreach($perBab as $bab => $isi)
    <x-lembar :dok="$dok" :halaman="$loop->iteration" :dari="$DARI" :akhir="$loop->last">

      @if($loop->first)
        <header class="text-center border-b border-stone-200 pb-4 mb-5">
          <h1 class="font-display text-[16px] font-black text-cam-ink leading-tight uppercase">Matriks Pemenuhan Klausul</h1>
          <h2 class="font-display text-[14px] font-bold text-cam-ink mt-0.5 uppercase">{{ $standar['nama'] }} — {{ $standar['judul'] }}</h2>
          <p class="text-[11.5px] text-stone-500 mt-2.5">
            <span class="num font-bold text-cam-ink">{{ $cakupan['tercakup'] }}</span> dari
            <span class="num font-bold text-cam-ink">{{ $cakupan['butir'] }}</span> klausul sudah memiliki dokumen terkendali
            @if(count($cakupan['celah']))
              · <span class="font-bold text-amber-700">{{ count($cakupan['celah']) }} belum tercakup</span>
            @endif
          </p>
        </header>
      @endif

      <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">Bab {{ $bab }} — {{ $isi['judul'] }}</h3>

      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[540px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold w-16 num">Klausul</th>
              <th class="py-1.5 px-2 font-semibold">Judul</th>
              <th class="py-1.5 pl-2 font-semibold">Dokumen Pemenuh</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($isi['klausul'] as $k)
              @php $d = $peta[$k['no']] ?? collect(); @endphp
              <tr>
                <td class="py-2 pr-2 num font-semibold text-cam-ink align-top">{{ $k['no'] }}</td>
                <td class="py-2 px-2 text-stone-600 align-top">{{ $k['judul'] }}</td>
                <td class="py-2 pl-2 align-top">
                  @if($d->count())
                    @foreach($d as $doc)
                      <div class="text-cam-ink"><span class="num font-semibold">{{ $doc->kode }}</span> — {{ $doc->judul }}</div>
                    @endforeach
                  @else
                    <span class="text-amber-700 font-semibold">Belum tercakup</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      @if($loop->last)
        <section class="pt-8 grid gap-8 sm:grid-cols-2 text-center text-[11.5px]">
          <div>
            <p class="text-stone-500">Disusun oleh</p>
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
