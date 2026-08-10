@extends('layouts.app')
@section('title',$standar['nama'])

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="font-display text-[19px] font-black text-cam-ink leading-tight">{{ $standar['nama'] }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ $standar['judul'] }}</p>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        <a href="{{ route('iso.cetak',$kode) }}" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">Matriks Cetak</a>
        <a href="{{ route('iso.index') }}" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">← Standar</a>
      </div>
    </div>

    <div class="flex flex-wrap items-end justify-between gap-3 mt-5 pt-5 hairline border-b-0">
      <div>
        <span class="num text-[26px] font-bold" style="color:{{ $warna }}">{{ $cakupan['tercakup'] }}</span>
        <span class="num text-[15px] text-stone-400">/{{ $cakupan['butir'] }}</span>
        <span class="text-[12px] text-stone-400 ml-1.5">klausul sudah punya dokumen</span>
      </div>
      @if(count($cakupan['celah']))
        <span class="text-[12.5px] font-bold text-cam-coral">{{ count($cakupan['celah']) }} klausul belum tercakup</span>
      @else
        <span class="text-[12.5px] font-bold text-cam-lime-deep">Seluruh klausul tercakup</span>
      @endif
    </div>
    <div class="mt-2.5 h-2 rounded-full bg-stone-100 overflow-hidden">
      <div class="h-full rounded-full transition-all duration-700" style="width: {{ $cakupan['rasio']*100 }}%; background:{{ $warna }}"></div>
    </div>
  </section>

  {{-- Klausul per bab --}}
  @foreach($perBab as $bab => $isi)
    <section class="kartu-lux rounded-2xl overflow-hidden">
      <div class="px-5 py-3.5 hairline flex items-center gap-3">
        <span class="shrink-0 num text-[11px] font-black text-white px-2.5 py-1 rounded-lg" style="background:{{ $warna }}">{{ $bab }}</span>
        <span class="text-[13.5px] font-bold text-cam-ink">{{ $isi['judul'] }}</span>
      </div>

      <ul class="divide-y divide-stone-100">
        @foreach($isi['klausul'] as $k)
          @php $dok = $peta[$k['no']] ?? collect(); @endphp
          <li class="px-5 py-3.5">
            <div class="flex items-start gap-3">
              <span class="shrink-0 num text-[11.5px] font-bold w-14 {{ $dok->count() ? 'text-cam-lime-deep' : 'text-stone-300' }}">{{ $k['no'] }}</span>
              <div class="min-w-0 flex-1">
                <div class="text-[12.5px] font-semibold text-cam-ink">{{ $k['judul'] }}</div>

                @if($dok->count())
                  <div class="flex flex-wrap gap-1.5 mt-2">
                    @foreach($dok as $d)
                      <a href="{{ route('dokumen.show',$d) }}"
                         class="text-[10.5px] font-semibold rounded-lg px-2 py-1 bg-cam-lime-soft text-cam-lime-deep hover:brightness-95 transition">
                        <span class="num">{{ $d->kode }}</span> · {{ \Illuminate\Support\Str::limit($d->judul, 44) }}
                      </a>
                    @endforeach
                  </div>
                @else
                  <div class="text-[11px] text-cam-coral font-semibold mt-1.5">Belum ada dokumen yang memenuhi klausul ini</div>
                @endif
              </div>
            </div>
          </li>
        @endforeach
      </ul>
    </section>
  @endforeach
</div>
@endsection
