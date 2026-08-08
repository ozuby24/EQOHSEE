@extends('layouts.app')
@section('title','Elemen '.$elemen['kode'].' — '.$elemen['nama'])

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Pindah elemen --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-3">
    <div class="flex flex-wrap gap-1.5">
      @foreach($semua as $e)
        <a href="{{ route('smkp.nilai',[$audit,$e['kode']]) }}"
           title="{{ $e['nama'] }}"
           class="px-3 py-1.5 rounded-full text-[11.5px] font-bold transition
                  {{ $e['kode'] === $elemen['kode'] ? 'lime-gradient text-white shadow-glow' : 'bg-white border border-stone-200 text-stone-500 hover:border-cam-lime' }}">
          {{ $e['kode'] }}
        </a>
      @endforeach
    </div>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <h2 class="text-[15px] font-bold text-cam-ink">{{ $elemen['kode'] }}. {{ $elemen['nama'] }}</h2>
        <p class="text-[11.5px] text-stone-400 mt-0.5">Bobot {{ $rekap['bobot'] }} · {{ $rekap['dinilai'] }}/{{ $rekap['berlaku'] }} kriteria dinilai</p>
      </div>
      <a href="{{ route('smkp.show',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline shrink-0">← Ringkasan</a>
    </div>
    <div class="mt-3 h-1.5 rounded-full bg-stone-100 overflow-hidden">
      <div class="h-full rounded-full lime-gradient transition-all" style="width: {{ $rekap['capaian']*100 }}%"></div>
    </div>
  </div>

  <form method="POST" action="{{ route('smkp.nilai.simpan',[$audit,$elemen['kode']]) }}" class="space-y-4">
    @csrf

    @foreach($elemen['sub'] as $sub)
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-stone-100 bg-stone-50/60">
          <h3 class="text-[13px] font-bold text-cam-ink">{{ $sub['kode'] }} — {{ $sub['nama'] }}</h3>
        </div>

        <div class="divide-y divide-stone-100">
          @foreach($sub['kriteria'] as $k)
            @php
              $nilai = $audit->nilai($k['kode']);
              $ket   = $audit->ket($k['kode']);
              $bukti = $audit->bukti($k['kode']);
            @endphp
            <div class="p-5">
              <div class="flex flex-wrap items-start gap-2 mb-3">
                <span class="num text-[11px] font-bold text-stone-400 shrink-0">{{ $k['kode'] }}</span>
                <p class="text-[13px] text-cam-ink leading-relaxed flex-1 min-w-0">{{ $k['uraian'] }}</p>
              </div>

              {{-- Kelas ditulis utuh (bukan dirangkai) supaya Tailwind memindainya. --}}
              <div class="flex flex-wrap gap-1.5 mb-3">
                @foreach($penilaian as $p)
                  @php
                    $aktif = match($p['kode']) {
                      'sesuai' => 'peer-checked:bg-[#4FA82E] peer-checked:border-[#4FA82E] peer-checked:text-white',
                      'minor'  => 'peer-checked:bg-[#F0921E] peer-checked:border-[#F0921E] peer-checked:text-white',
                      'mayor'  => 'peer-checked:bg-[#E5484D] peer-checked:border-[#E5484D] peer-checked:text-white',
                      default  => 'peer-checked:bg-[#9AA3AE] peer-checked:border-[#9AA3AE] peer-checked:text-white',
                    };
                  @endphp
                  <label class="cursor-pointer">
                    <input type="radio" name="k[{{ $k['kode'] }}][n]" value="{{ $p['kode'] }}"
                           class="peer sr-only" @checked($nilai === $p['kode'])>
                    <span class="block px-3 py-1.5 rounded-full text-[11.5px] font-bold border border-stone-200
                                 text-stone-500 hover:border-cam-lime transition {{ $aktif }}">{{ $p['label'] }}</span>
                  </label>
                @endforeach
                <label class="cursor-pointer">
                  <input type="radio" name="k[{{ $k['kode'] }}][n]" value="" class="peer sr-only" @checked($nilai === null)>
                  <span class="block px-3 py-1.5 rounded-full text-[11.5px] font-bold border border-dashed border-stone-200
                               text-stone-400 hover:border-stone-300 transition
                               peer-checked:bg-stone-100 peer-checked:text-stone-600">Belum dinilai</span>
                </label>
              </div>

              <div class="grid gap-2 sm:grid-cols-2">
                <input name="k[{{ $k['kode'] }}][bukti]" value="{{ $bukti }}" placeholder="Bukti / dokumen rujukan"
                       class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
                <input name="k[{{ $k['kode'] }}][ket]" value="{{ $ket }}" placeholder="Catatan auditor"
                       class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
              </div>
            </div>
          @endforeach
        </div>
      </section>
    @endforeach

    <div class="sticky bottom-4 flex flex-wrap gap-2">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold hover:brightness-105 transition">
        Simpan Elemen {{ $elemen['kode'] }}
      </button>
      <a href="{{ route('smkp.show',$audit) }}"
         class="rounded-xl bg-white border border-stone-200 px-5 py-3 text-[13px] font-bold text-stone-500 hover:bg-stone-50 transition">Selesai</a>
    </div>
  </form>
</div>
@endsection
