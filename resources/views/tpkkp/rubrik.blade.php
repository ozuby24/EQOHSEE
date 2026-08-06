@extends('layouts.app')
@section('title','PTPKKP — Rubrik')

@section('content')
@php $T = \App\Support\Tpkkp::class; @endphp
<div class="max-w-5xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <form method="GET" class="bg-white rounded-2xl border border-stone-200 p-4">
    <input type="hidden" name="tahun" value="{{ $a->tahun }}">
    <input type="hidden" name="p" value="{{ $paramAktif }}">
    <div class="flex flex-wrap items-center gap-2">
      <input name="q" value="{{ $q }}" placeholder="Cari kode atau item dalam parameter ini…"
             class="ring-focus rounded-xl border border-stone-200 px-3.5 py-2 text-[12.5px] w-72">
      <button class="rounded-xl bg-cam-ink text-white px-4 py-2 text-[12.5px] font-bold">Cari</button>
      <span class="text-[11px] text-stone-400 ml-auto">Rubrik acuan Kepdirjen · 5 tingkat per item</span>
    </div>
  </form>

  <div class="bg-white rounded-2xl border border-stone-200 p-4">
    <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-stone-400 mb-2.5">Parameter</div>
    <div class="flex flex-wrap gap-1.5">
      @foreach($daftarParam as $p)
        <a href="{{ route('tpkkp.rubrik', ['p' => $p['code'], 'q' => $q]) }}"
           class="text-[11.5px] px-2.5 py-1 rounded-lg border transition
                  {{ $p['code'] === $paramAktif ? 'bg-cam-lime-soft border-cam-lime/40 text-cam-lime-deep font-bold' : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400' }}">
          <span class="num font-semibold">{{ $p['code'] }}</span>
          <span class="opacity-60">({{ $p['n'] }})</span>
        </a>
      @endforeach
    </div>
  </div>

  @forelse($items as $row)
    @php $it = $row['item']; @endphp
    <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100 bg-stone-50">
        <span class="num text-[11px] font-bold text-stone-400">{{ $it['code'] }}</span>
        <div class="text-[12.5px] font-semibold text-cam-ink leading-snug">{{ $it['name'] }}</div>
        <div class="text-[10.5px] text-stone-400 mt-1">metode: {{ implode(' · ', $it['methods']) }} · maks {{ $it['max'] }}</div>
      </div>

      @if($row['acuan'])
        <div class="px-5 py-4">
          <div class="text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-2">Rubrik acuan</div>
          <div class="space-y-1.5">
            @foreach($row['acuan']['l'] as $i => $teks)
              <div class="flex gap-2.5 text-[11.5px] leading-snug">
                <span class="flex-none w-5 h-5 rounded-md text-white text-[10px] font-bold grid place-items-center"
                      style="background: {{ $T::levelHex($i + 1) }}">{{ $i + 1 }}</span>
                <span class="text-stone-600">{{ $teks }}</span>
              </div>
            @endforeach
          </div>
        </div>
      @endif

      @foreach($row['rubrik'] as $m => $rub)
        @if(!$row['acuan'] || $rub !== $row['acuan'])
          <div class="px-5 py-4 border-t border-stone-100">
            <div class="text-[10px] font-bold uppercase tracking-wider text-cam-lime-deep mb-2">Rubrik metode {{ $m }}</div>
            <div class="space-y-1.5">
              @foreach($rub['l'] as $i => $teks)
                <div class="flex gap-2.5 text-[11.5px] leading-snug">
                  <span class="flex-none w-5 h-5 rounded-md text-white text-[10px] font-bold grid place-items-center"
                        style="background: {{ $T::levelHex($i + 1) }}">{{ $i + 1 }}</span>
                  <span class="text-stone-600">{{ $teks }}</span>
                </div>
              @endforeach
            </div>
          </div>
        @endif
      @endforeach

      @php $tg = $target[$it['code']] ?? null; @endphp
      @if($tg)
        <div class="px-5 py-4 border-t border-stone-100 bg-stone-50">
          <div class="text-[10px] font-bold uppercase tracking-wider text-stone-400 mb-1.5">Target sampel / dokumen</div>
          @foreach($tg as $m => $teks)
            <div class="text-[11.5px] text-stone-600 mb-1"><b class="num">{{ $m }}</b> — {{ trim($teks) }}</div>
          @endforeach
        </div>
      @endif
    </div>
  @empty
    <div class="bg-white rounded-2xl border border-stone-200 px-5 py-10 text-center text-[12.5px] text-stone-400">
      Tidak ada item yang cocok.
    </div>
  @endforelse
</div>
@endsection
