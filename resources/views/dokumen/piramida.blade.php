@extends('layouts.app')
@section('title','Piramida Dokumen')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink leading-tight">Piramida Dokumen</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">
      Register mendaftar dokumen secara mendatar; piramida menunjukkan bentuk sistemnya.
      Tingkat yang kosong justru paling berguna dilihat — sistem tanpa Prosedur, misalnya,
      terbaca seketika.
    </p>
    <p class="text-[11.5px] text-stone-400 mt-3">
      <span class="num font-bold text-cam-ink">{{ $total }}</span> dokumen terdaftar pada enam tingkat.
    </p>
  </section>

  {{-- Tingkat digambar melebar ke bawah: makin ke dasar makin banyak
       dokumennya, sesuai bentuk piramida mutu yang sebenarnya. --}}
  <div class="space-y-2.5">
    @foreach($tingkat as $t)
      @php
        // Lebar mengikuti urutan tingkat, bukan jumlah dokumen — bentuknya
        // harus tetap terbaca sebagai piramida walau isinya belum merata.
        $lebar = 46 + ($t['urutan'] - 1) * 10.8;
      @endphp
      <div class="mx-auto transition-all" style="max-width: {{ $lebar }}%">
        <a href="{{ route('dokumen.index', ['jenis' => $t['jenis']]) }}"
           class="kartu-lux rounded-2xl px-5 py-4 block hover:-translate-y-0.5 transition">
          <div class="flex flex-wrap items-center gap-3">
            <span class="shrink-0 num text-[11px] font-black text-white w-6 h-6 rounded-lg grid place-items-center bg-cam-lime">{{ $t['urutan'] }}</span>
            <span class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">{{ $t['jenis'] }}</span>
            <span class="shrink-0 text-right">
              <span class="num text-[17px] font-bold {{ $t['total'] ? 'text-cam-ink' : 'text-cam-coral' }}">{{ $t['total'] }}</span>
              <span class="text-[10.5px] text-stone-400 ml-1">dokumen</span>
            </span>
          </div>

          <p class="text-[11.5px] text-stone-500 mt-2 leading-relaxed">{{ $t['ket'] }}</p>

          @if($t['total'])
            <div class="flex flex-wrap gap-2 mt-2.5">
              <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded bg-cam-lime-soft text-cam-lime-deep">{{ $t['berlaku'] }} berlaku</span>
              @if($t['draft'])
                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded bg-stone-100 text-stone-500">{{ $t['draft'] }} draft</span>
              @endif
            </div>
          @else
            <div class="text-[11px] font-semibold text-cam-coral mt-2.5">Tingkat ini masih kosong</div>
          @endif
        </a>
      </div>
    @endforeach
  </div>
</div>
@endsection
