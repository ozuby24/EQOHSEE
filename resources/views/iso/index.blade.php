@extends('layouts.app')
@section('title','Pemenuhan Klausul ISO')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink leading-tight">Pemenuhan Klausul Standar</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">
      Register dokumen menjawab dokumen apa saja yang dipunya. Halaman ini menjawab
      pertanyaan sebaliknya — yang justru ditanyakan auditor: klausul mana yang belum
      punya dokumen.
    </p>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4 mt-5 pt-5 hairline border-b-0">
      @foreach ([
        ['Standar diacu', count($standar)],
        ['Dokumen terdaftar', $dokumen],
        ['Sudah dipetakan', $dipetakan],
        ['Belum dipetakan', max(0, $dokumen - $dipetakan)],
      ] as $i => [$l,$v])
        <div>
          <div class="num text-[20px] font-bold {{ $i === 3 && $v > 0 ? 'text-cam-coral' : 'text-cam-lime-deep' }}">{{ $v }}</div>
          <div class="text-[11px] text-stone-400 mt-1">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- Standar --}}
  <div class="grid gap-4 md:grid-cols-2">
    @foreach($standar as $kode => $s)
      @php $c = $s['cakupan']; @endphp
      <a href="{{ route('iso.show',$kode) }}" class="kartu-lux rounded-2xl p-5 block hover:-translate-y-0.5 transition">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-[14px] font-bold text-cam-ink">{{ $s['nama'] }}</div>
            <div class="text-[12px] text-stone-500 mt-0.5">{{ $s['judul'] }}</div>
          </div>
          @if($s['aspek'])
            <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                  style="background:{{ $s['warna'] }}">{{ $s['aspek'] }}</span>
          @endif
        </div>

        <p class="text-[11.5px] text-stone-500 mt-2.5 leading-relaxed">{{ $s['ket'] }}</p>

        <div class="flex items-end justify-between gap-3 mt-4">
          <div>
            <span class="num text-[19px] font-bold" style="color:{{ $s['warna'] }}">{{ $c['tercakup'] }}</span>
            <span class="num text-[13px] text-stone-400">/{{ $c['butir'] }}</span>
            <span class="text-[11px] text-stone-400 ml-1">klausul tercakup</span>
          </div>
          @if(count($c['celah']))
            <span class="text-[11px] font-bold text-cam-coral shrink-0">{{ count($c['celah']) }} celah</span>
          @else
            <span class="text-[11px] font-bold text-cam-lime-deep shrink-0">Lengkap</span>
          @endif
        </div>

        <div class="mt-2.5 h-1.5 rounded-full bg-stone-100 overflow-hidden">
          <div class="h-full rounded-full transition-all duration-700"
               style="width: {{ $c['rasio']*100 }}%; background:{{ $s['warna'] }}"></div>
        </div>
      </a>
    @endforeach
  </div>

  @if($catatan)
    <p class="text-[11.5px] text-stone-400 leading-relaxed px-1">{{ $catatan }}</p>
  @endif
</div>
@endsection
