@props([
  'label',                 // array<string>
  'seri',                  // array<array{nama:string,data:array<float>,warna:string}>
  'tumpuk' => false,
  'tinggi' => 240,
  'desimal' => 0,
  'satuan' => null,
])

{{-- Grafik batang, digambar sebagai SVG dari data.

     Sama alasannya dengan x-garis: satu berkas JavaScript tambahan tidak
     sepadan untuk beberapa persegi panjang, dan halaman ini harus tetap
     terbaca di jaringan site tambang. Skalanya ditulis di tepi kiri —
     batang tanpa angka acuan hanya memperlihatkan mana yang lebih tinggi,
     dan itu belum cukup untuk mengambil keputusan. --}}
@php
  $n = count($label);
  $puncak = 0.0;
  foreach ($label as $i => $_) {
      $kolom = $tumpuk
          ? array_sum(array_map(fn ($s) => (float) ($s['data'][$i] ?? 0), $seri))
          : max(array_map(fn ($s) => (float) ($s['data'][$i] ?? 0), $seri));
      $puncak = max($puncak, $kolom);
  }

  // Sumbu berhenti di angka bulat, bukan di angka data.
  $langkah = 1.0;
  if ($puncak > 0) {
      $kasar   = $puncak / 4;
      $pangkat = 10 ** floor(log10(max($kasar, 1e-9)));
      foreach ([1, 2, 2.5, 5, 10] as $m) {
          if ($m * $pangkat >= $kasar) { $langkah = $m * $pangkat; break; }
      }
  }
  $atas = $puncak > 0 ? ceil($puncak / $langkah) * $langkah : 1.0;

  $L = 600; $T = $tinggi; $padKiri = 8; $padBawah = 22; $padAtas = 6;
  $h = $T - $padBawah - $padAtas;
  $slot = ($L - $padKiri) / max(1, $n);
  $lebarGrup = min($slot * 0.62, 54);
  $lebarBatang = $tumpuk ? $lebarGrup : $lebarGrup / max(1, count($seri));
  $tulis = fn ($v) => number_format($v, $desimal).($satuan ? ' '.$satuan : '');
@endphp

@if($n)
  <div class="flex gap-3">
    <div class="shrink-0 flex flex-col justify-between text-[10px] num text-stone-400 text-right"
         style="height:{{ $T - $padBawah }}px">
      <span>{{ $tulis($atas) }}</span>
      <span>{{ $tulis($atas / 2) }}</span>
      <span>0</span>
    </div>

    <div class="flex-1 min-w-0">
      <svg viewBox="0 0 {{ $L }} {{ $T }}" preserveAspectRatio="none" class="w-full block" style="height:{{ $T }}px">
        @foreach ([0, $h / 2] as $y)
          <line x1="{{ $padKiri }}" y1="{{ $padAtas + $y }}" x2="{{ $L }}" y2="{{ $padAtas + $y }}"
                stroke="#E7E2D8" stroke-width="1" vector-effect="non-scaling-stroke"/>
        @endforeach
        <line x1="{{ $padKiri }}" y1="{{ $padAtas + $h }}" x2="{{ $L }}" y2="{{ $padAtas + $h }}"
              stroke="#D8D2C6" stroke-width="1" vector-effect="non-scaling-stroke"/>

        @foreach($label as $i => $l)
          @php $kiri = $padKiri + $slot * $i + ($slot - $lebarGrup) / 2; $dasar = $padAtas + $h; @endphp
          @foreach($seri as $si => $s)
            @php
              $v  = (float) ($s['data'][$i] ?? 0);
              $tb = $atas > 0 ? ($v / $atas) * $h : 0;
              $x  = $tumpuk ? $kiri : $kiri + $lebarBatang * $si;
              $y  = $tumpuk ? $dasar - $tb : $dasar - $tb;
              if ($tumpuk) $dasar -= $tb;
            @endphp
            <rect x="{{ round($x + 1, 1) }}" y="{{ round($y, 1) }}"
                  width="{{ round(max(1, $lebarBatang - 2), 1) }}" height="{{ round(max(0, $tb), 1) }}"
                  rx="2" fill="{{ $s['warna'] }}">
              <title>{{ $l }} · {{ $s['nama'] }}: {{ $tulis($v) }}</title>
            </rect>
          @endforeach
        @endforeach
      </svg>

      <div class="flex text-[10px] text-stone-400 -mt-4">
        @foreach($label as $l)
          <span class="flex-1 text-center truncate px-0.5">{{ $l }}</span>
        @endforeach
      </div>
    </div>
  </div>

  <div class="flex flex-wrap gap-x-5 gap-y-1.5 mt-4 text-[11.5px] text-stone-500">
    @foreach($seri as $s)
      <span class="inline-flex items-center gap-2">
        <i class="w-2.5 h-2.5 rounded-sm" style="background:{{ $s['warna'] }}"></i>{{ $s['nama'] }}
      </span>
    @endforeach
  </div>
@else
  <div class="grid place-items-center text-[12px] text-stone-400" style="height:{{ $tinggi }}px">
    Belum ada data pada rentang ini.
  </div>
@endif
