@extends('layouts.app')
@section('title','PTPKKP — Penilaian')

@section('content')
@php
  $T       = \App\Support\Tpkkp::class;
  $M       = $T::methods();
  $target  = $T::target();
  $berEnt  = count($entitas) > 0;
  $bisa    = auth()->user()->isAdmin();
@endphp

<div class="max-w-6xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  {{-- ── Pilih metode ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 p-4">
    <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-stone-400 mb-2.5">Metode Pengukuran</div>
    <div class="flex flex-wrap gap-2">
      @foreach($M as $k => $m)
        <a href="{{ route('tpkkp.assess', ['m' => $k]) }}"
           class="text-[12px] font-semibold px-3 py-1.5 rounded-lg border transition
                  {{ $k === $metodeAktif ? 'bg-cam-ink text-white border-cam-ink' : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400' }}">
          {{ $k }} <span class="font-normal opacity-70">· {{ $m['name'] }}</span>
        </a>
      @endforeach
    </div>

    <p class="text-[11.5px] text-stone-500 mt-3">
      {{ $metodeInfo['name'] }} —
      @if($berEnt)
        dinilai per <b>{{ strtolower($metodeInfo['entityLabel']) }}</b> ({{ count($entitas) }} entitas).
        Skor metode adalah <b>rerata</b> entitas yang terisi.
      @else
        dinilai satu nilai untuk seluruh organisasi.
      @endif
    </p>
  </div>

  {{-- ── Pilih parameter ── --}}
  <div class="bg-white rounded-2xl border border-stone-200 p-4">
    <div class="text-[10px] font-bold uppercase tracking-[0.18em] text-stone-400 mb-2.5">Parameter yang memakai metode {{ $metodeAktif }}</div>
    <div class="flex flex-wrap gap-1.5">
      @foreach($daftarParam as $p)
        <a href="{{ route('tpkkp.assess', ['m' => $metodeAktif, 'p' => $p['code']]) }}"
           class="text-[11.5px] px-2.5 py-1 rounded-lg border transition
                  {{ $p['code'] === $paramAktif ? 'bg-cam-lime-soft border-cam-lime/40 text-cam-lime-deep font-bold' : 'bg-white text-stone-600 border-stone-200 hover:border-stone-400' }}">
          <span class="num font-semibold">{{ $p['code'] }}</span>
          <span class="opacity-60">({{ $p['n'] }})</span>
        </a>
      @endforeach
    </div>
  </div>

  @if(!$paramAktif || count($items) === 0)
    <div class="bg-white rounded-2xl border border-stone-200 px-5 py-8 text-center text-[12.5px] text-stone-400">
      Tidak ada item pada kombinasi ini.
    </div>
  @else
    @php $par = $T::paramByCode($paramAktif); @endphp

    <form method="POST" action="{{ route('tpkkp.assess.save', ['tahun' => $a->tahun]) }}">
      @csrf
      <input type="hidden" name="metode" value="{{ $metodeAktif }}">

      <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
        <div class="px-5 py-3.5 border-b border-stone-100 bg-stone-50">
          <h3 class="text-[13px] font-bold text-cam-ink">{{ $par['code'] }} · {{ $par['name'] }}</h3>
          <p class="text-[11px] text-stone-500 mt-0.5 num">
            bobot {{ number_format($par['weight'], 2) }} ·
            target {{ number_format($T::paramTargets()[$par['code']] ?? 0, 2) }} ·
            {{ count($items) }} item memakai metode {{ $metodeAktif }}
          </p>
        </div>

        <div class="divide-y divide-stone-100">
          @foreach($items as $it)
            @php
              $rub = $T::rubrikFor($metodeAktif, $it['code']);
              $tgt = $target[$it['code']][$metodeAktif] ?? null;
              $c   = $T::itemCalc($a->scores ?? [], $it);
            @endphp

            <div class="px-5 py-4">
              <div class="flex flex-wrap items-start justify-between gap-2 mb-3">
                <div class="pr-3">
                  <span class="num text-[11px] font-bold text-stone-400">{{ $it['code'] }}</span>
                  <div class="text-[12.5px] font-semibold text-cam-ink leading-snug">{{ $it['name'] }}</div>
                  <div class="text-[10.5px] text-stone-400 mt-1">
                    metode: {{ implode(' · ', $it['methods']) }} · maks {{ $it['max'] }}
                  </div>
                </div>
                <div class="text-right whitespace-nowrap">
                  @include('tpkkp._badge', ['cat' => $c['category']])
                  <div class="num text-[11px] text-stone-400 mt-1">{{ $c['filled'] }}/{{ $c['total'] }} metode</div>
                </div>
              </div>

              {{-- kolom nilai --}}
              <div class="flex flex-wrap gap-2">
                @if($berEnt)
                  @foreach($entitas as $ent)
                    @php $v = $a->cell($metodeAktif, $it['code'], $ent); @endphp
                    <label class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5">
                      <span class="text-[11px] text-stone-500 whitespace-nowrap">{{ $ent }}</span>
                      <select name="n[{{ $it['code'] }}][{{ $ent }}]" @disabled(!$bisa)
                              class="ring-focus rounded-md border border-stone-200 bg-white px-2 py-1 text-[12px] font-semibold num">
                        <option value="">—</option>
                        @for($i = 1; $i <= 5; $i++)
                          <option value="{{ $i }}" @selected((int) $v === $i)>{{ $i }}</option>
                        @endfor
                      </select>
                    </label>
                  @endforeach
                @else
                  @php $v = $a->cell($metodeAktif, $it['code']); @endphp
                  <label class="flex items-center gap-2 rounded-lg border border-stone-200 bg-stone-50 px-2.5 py-1.5">
                    <span class="text-[11px] text-stone-500">Nilai</span>
                    <select name="n[{{ $it['code'] }}][_]" @disabled(!$bisa)
                            class="ring-focus rounded-md border border-stone-200 bg-white px-2 py-1 text-[12px] font-semibold num">
                      <option value="">—</option>
                      @for($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected((int) $v === $i)>{{ $i }}</option>
                      @endfor
                    </select>
                  </label>
                @endif
              </div>

              <input type="text" name="ket[{{ $it['code'] }}]" value="{{ $a->ket($metodeAktif, $it['code']) }}"
                     placeholder="Keterangan / bukti (opsional)" @disabled(!$bisa)
                     class="ring-focus w-full mt-2.5 rounded-lg border border-stone-200 bg-white px-3 py-2 text-[12px]">

              @if($tgt)
                <details class="mt-2">
                  <summary class="text-[11px] font-semibold text-stone-500 cursor-pointer">Target sampel / dokumen</summary>
                  <pre class="text-[11px] text-stone-600 whitespace-pre-wrap mt-1.5 bg-stone-50 rounded-lg p-3 border border-stone-100">{{ trim($tgt) }}</pre>
                </details>
              @endif

              @if($rub)
                <details class="mt-2">
                  <summary class="text-[11px] font-semibold text-cam-lime-deep cursor-pointer">Rubrik 5 tingkat</summary>
                  <div class="mt-1.5 space-y-1">
                    @foreach($rub['l'] as $i => $teks)
                      <div class="flex gap-2.5 text-[11.5px] leading-snug">
                        <span class="flex-none w-5 h-5 rounded-md text-white text-[10px] font-bold grid place-items-center"
                              style="background: {{ $T::levelHex($i + 1) }}">{{ $i + 1 }}</span>
                        <span class="text-stone-600">{{ $teks }}</span>
                      </div>
                    @endforeach
                  </div>
                </details>
              @endif
            </div>
          @endforeach
        </div>

        @if($bisa)
          <div class="px-5 py-4 border-t border-stone-100 bg-stone-50 flex items-center justify-between gap-3">
            <p class="text-[11px] text-stone-500">Metode yang dibiarkan kosong dihitung nol pada capaian item.</p>
            <button class="lime-gradient shadow-glow rounded-xl text-white px-6 py-2.5 text-[13px] font-bold hover:brightness-105 transition">
              Simpan nilai {{ $metodeAktif }}
            </button>
          </div>
        @endif
      </div>
    </form>
  @endif
</div>
@endsection
