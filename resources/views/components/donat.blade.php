@props([
  'data',                  // array<array{nama:string,nilai:float,warna:string}>
  'tinggi' => 210,
  'tengah' => null,
  'tengahKet' => null,
  'desimal' => 0,
])

{{-- Donat komposisi, digambar dengan stroke-dasharray pada satu lingkaran.

     Lebih ringan daripada menghitung busur satu per satu, dan tetap satu
     bentuk SVG tanpa JavaScript. Angka di tengahnya sengaja diisi: donat
     tanpa angka memaksa orang menaksir sudut, padahal yang ingin diketahui
     hampir selalu jumlahnya. --}}
@php
  $total = array_sum(array_column($data, 'nilai'));
  $r = 42; $keliling = 2 * M_PI * $r;
  $mulai = 0.0;
@endphp

@if($total > 0)
  <div class="flex items-center justify-center" style="height:{{ $tinggi }}px">
    <svg viewBox="0 0 120 120" class="h-full" style="max-width:100%">
      <g transform="rotate(-90 60 60)">
        <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke="#EFEBE2" stroke-width="16"/>
        @foreach($data as $d)
          @php
            $porsi = $d['nilai'] / $total;
            $panjang = $porsi * $keliling;
            $offset = -$mulai * $keliling;
            $mulai += $porsi;
          @endphp
          @if($d['nilai'] > 0)
            <circle cx="60" cy="60" r="{{ $r }}" fill="none" stroke="{{ $d['warna'] }}" stroke-width="16"
                    stroke-dasharray="{{ round($panjang, 2) }} {{ round($keliling - $panjang, 2) }}"
                    stroke-dashoffset="{{ round($offset, 2) }}">
              <title>{{ $d['nama'] }}: {{ number_format($d['nilai'], $desimal) }} ({{ number_format($porsi * 100, 1) }}%)</title>
            </circle>
          @endif
        @endforeach
      </g>
      <text x="60" y="58" text-anchor="middle" class="num"
            style="font-size:17px;font-weight:800;fill:#22312F">{{ $tengah ?? number_format($total, $desimal) }}</text>
      @if($tengahKet)
        <text x="60" y="72" text-anchor="middle" style="font-size:7px;fill:#8A8578">{{ $tengahKet }}</text>
      @endif
    </svg>
  </div>

  <div class="flex flex-wrap gap-x-5 gap-y-1.5 mt-3 text-[11.5px] text-stone-500 justify-center">
    @foreach($data as $d)
      @if($d['nilai'] > 0)
        <span class="inline-flex items-center gap-2">
          <i class="w-2.5 h-2.5 rounded-sm" style="background:{{ $d['warna'] }}"></i>
          {{ $d['nama'] }} <span class="num font-semibold text-cam-ink">{{ number_format($d['nilai'], $desimal) }}</span>
        </span>
      @endif
    @endforeach
  </div>
@else
  <div class="grid place-items-center text-[12px] text-stone-400" style="height:{{ $tinggi }}px">
    Belum ada data.
  </div>
@endif
