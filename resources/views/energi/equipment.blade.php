@extends('layouts.app')
@section('title','Equipment Energy Performance')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  <x-energi.kepala judul="Equipment Energy Performance"
      ket="Peringkat keborosan tiap unit. Yang dinilai adalah selisihnya terhadap rata-rata
           kelompoknya sendiri — mengurutkan liter mentah hanya akan selalu menempatkan alat
           bertenaga besar di puncak daftar, dan itu tidak memberi tahu apa-apa."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.equipment')">

    {{-- Saring kategori --}}
    <div class="flex flex-wrap gap-2 mt-6 pt-5 hairline border-b-0">
      @php $q = ['dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]; @endphp
      <a href="{{ route('energi.equipment', $q) }}"
         class="text-[11.5px] font-bold px-3.5 py-1.5 rounded-xl transition {{ $kategori ? 'bg-stone-100 text-stone-500 hover:bg-stone-200' : 'bg-cam-ink text-white' }}">
        Semua
      </a>
      @foreach(Energi::KATEGORI as $kode => $nama)
        <a href="{{ route('energi.equipment', $q + ['kategori'=>$kode]) }}"
           class="text-[11.5px] font-bold px-3.5 py-1.5 rounded-xl transition {{ $kategori === $kode ? 'bg-cam-ink text-white' : 'bg-stone-100 text-stone-500 hover:bg-stone-200' }}">
          {{ $nama }}
        </a>
      @endforeach
    </div>
  </x-energi.kepala>

  {{-- Ringkas per kategori --}}
  @if(count($perKategori))
    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4">
      @foreach($perKategori as $kode => $k)
        <x-kpi :label="$k['nama']" :nilai="number_format($k['l_hm'], 2)" satuan="L/HM"
               :ket="number_format($k['liter']).' L · '.number_format($k['hm'], 1).' jam operasi'"
               :warna="$kategori === $kode ? '#E2663A' : '#F57C00'" />
      @endforeach
    </div>
  @endif

  {{-- Daftar unit --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex items-baseline justify-between gap-3">
      <h3 class="font-display text-[16px] font-black text-cam-ink">
        Daftar Unit{{ $kategori ? ' — '.(Energi::KATEGORI[$kategori] ?? $kategori) : '' }}
      </h3>
      <span class="text-[11.5px] text-stone-400 shrink-0">{{ count($peringkat) }} unit beroperasi</span>
    </div>

    @if(count($peringkat))
      <div class="grid gap-3 sm:grid-cols-2 mt-5">
        @foreach($peringkat as $b)
          <a href="{{ route('energi.equipment.show', [$b['unit'],'dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
             class="kartu-lux rounded-2xl p-4 block card-hover">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-[13px] font-bold text-cam-ink truncate">{{ $b['unit']->kode }}</div>
                <div class="text-[11px] text-stone-500 truncate">{{ $b['unit']->nama }}</div>
              </div>
              <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg whitespace-nowrap"
                    style="background:{{ $b['status']['warna'] }}">{{ $b['status']['label'] }}</span>
            </div>

            <div class="grid grid-cols-3 gap-2 mt-4">
              @foreach ([
                ['L/HM', number_format($b['l_hm'], 2)],
                ['Liter', number_format($b['liter'])],
                ['HM', number_format($b['hm'], 1)],
              ] as $i => [$l, $v])
                <div>
                  <div class="num text-[14px] font-bold {{ $i === 0 ? '' : 'text-cam-ink' }}"
                       @if($i === 0) style="color:{{ $b['status']['warna'] }}" @endif>{{ $v }}</div>
                  <div class="text-[10px] text-stone-400 mt-0.5">{{ $l }}</div>
                </div>
              @endforeach
            </div>

            @if($b['acuan'] > 0)
              <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-700"
                     style="width:{{ max(4, min(100, $b['l_hm'] / max($b['acuan'] * 1.5, 1e-9) * 100)) }}%; background:{{ $b['status']['warna'] }}"></div>
              </div>
              <div class="text-[10px] text-stone-400 mt-1.5">
                Acuan {{ $b['unit']->labelKategori() }}: <span class="num">{{ number_format($b['acuan'], 2) }}</span> L/HM
              </div>
            @endif
          </a>
        @endforeach
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-6">
        Belum ada catatan bahan bakar pada rentang ini{{ $kategori ? ' untuk kategori tersebut' : '' }}.
      </p>
    @endif
  </section>

</div>
@endsection
