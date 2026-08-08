{{--
  Visual isometrik per pilar.

  Dibuat sebagai SVG inline berlapis — bukan berkas gambar — karena tiga alasan:
  warnanya ikut palet pilar dari registry, tajam di layar kepadatan berapa pun,
  dan tetap tampil saat jaringan site tambang terputus.

  Parameter: $slug (kunci pilar), $p (data pilar dari Pillars::all()).
--}}
@php
  $u = $slug;                                  // dipakai sebagai awalan id gradien
  $deep = $p['deep']; $base = $p['warna']; $light = $p['light'];
@endphp

<svg viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg"
     class="w-full h-auto" role="img" aria-label="Ilustrasi pilar {{ $p['nama'] }}">
  <defs>
    <linearGradient id="{{ $u }}-atas" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="{{ $light }}"/><stop offset="1" stop-color="{{ $base }}"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-kiri" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{{ $base }}"/><stop offset="1" stop-color="{{ $deep }}"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-kanan" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{{ $deep }}"/><stop offset="1" stop-color="{{ $deep }}" stop-opacity=".72"/>
    </linearGradient>
    <radialGradient id="{{ $u }}-cahaya" cx=".5" cy=".38" r=".62">
      <stop offset="0" stop-color="{{ $light }}" stop-opacity=".30"/>
      <stop offset="1" stop-color="{{ $light }}" stop-opacity="0"/>
    </radialGradient>
    <filter id="{{ $u }}-bayang" x="-40%" y="-40%" width="180%" height="180%">
      <feDropShadow dx="0" dy="7" stdDeviation="9" flood-color="{{ $deep }}" flood-opacity=".34"/>
    </filter>
  </defs>

  {{-- pendar latar --}}
  <ellipse cx="120" cy="100" rx="96" ry="76" fill="url(#{{ $u }}-cahaya)"/>

  {{-- alas isometrik: sama untuk semua pilar agar keluarganya terbaca satu bahasa --}}
  <g opacity=".16">
    <path d="M120 148 L204 104 L120 60 L36 104 Z" fill="{{ $base }}"/>
  </g>
  <path d="M120 140 L196 100 L120 60 L44 100 Z" fill="{{ $deep }}" opacity=".38"/>

  <g filter="url(#{{ $u }}-bayang)">
  @switch($slug)

    {{-- ENERGY — tiang transmisi & aliran daya --}}
    @case('energy')
      <path d="M120 96 L162 72 L162 112 L120 136 Z" fill="url(#{{ $u }}-kanan)"/>
      <path d="M120 96 L78 72 L78 112 L120 136 Z"  fill="url(#{{ $u }}-kiri)"/>
      <path d="M120 96 L78 72 L120 48 L162 72 Z"   fill="url(#{{ $u }}-atas)"/>
      {{-- kilat --}}
      <path d="M124 62 L108 92 L119 92 L114 118 L134 86 L122 86 Z"
            fill="#fff" opacity=".95" class="denyut"/>
      {{-- busur aliran --}}
      <path d="M64 118 Q120 86 176 118" stroke="{{ $light }}" stroke-width="2.4"
            stroke-linecap="round" opacity=".75" stroke-dasharray="5 8">
        <animate attributeName="stroke-dashoffset" from="26" to="0" dur="1.6s" repeatCount="indefinite"/>
      </path>
      @break

    {{-- QUALITY — lapisan terukur & tanda periksa --}}
    @case('quality')
      <path d="M120 108 L166 82 L166 100 L120 126 Z" fill="url(#{{ $u }}-kanan)"/>
      <path d="M120 108 L74 82 L74 100 L120 126 Z"   fill="url(#{{ $u }}-kiri)"/>
      <path d="M120 108 L74 82 L120 56 L166 82 Z"    fill="url(#{{ $u }}-atas)"/>
      <g class="apung-2">
        <path d="M120 84 L156 63 L156 73 L120 94 Z" fill="{{ $deep }}" opacity=".85"/>
        <path d="M120 84 L84 63 L84 73 L120 94 Z"   fill="{{ $base }}" opacity=".85"/>
        <path d="M120 84 L84 63 L120 42 L156 63 Z"  fill="{{ $light }}"/>
        <path d="M108 62 L117 70 L134 54" stroke="#fff" stroke-width="3.4"
              stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </g>
      @break

    {{-- OCCUPATIONAL HEALTH — perisai dengan denyut nadi --}}
    @case('occhealth')
      <path d="M120 52 L160 68 V102 C160 122 142 136 120 144 C98 136 80 122 80 102 V68 Z"
            fill="url(#{{ $u }}-atas)"/>
      <path d="M120 52 L160 68 V102 C160 122 142 136 120 144 Z"
            fill="url(#{{ $u }}-kanan)" opacity=".62"/>
      <path d="M92 100 H108 L114 86 L124 114 L130 100 H148"
            stroke="#fff" stroke-width="3.6" stroke-linecap="round"
            stroke-linejoin="round" fill="none"/>
      <circle cx="120" cy="52" r="5" fill="#fff" class="denyut"/>
      @break

    {{-- SAFETY — helm keselamatan --}}
    @case('safety')
      <path d="M76 122 C76 96 96 74 120 74 C144 74 164 96 164 122 Z" fill="url(#{{ $u }}-atas)"/>
      <path d="M120 74 C144 74 164 96 164 122 H120 Z" fill="url(#{{ $u }}-kanan)" opacity=".55"/>
      <rect x="66" y="120" width="108" height="13" rx="6.5" fill="{{ $deep }}"/>
      <path d="M120 74 V122" stroke="#fff" stroke-width="2.6" opacity=".45"/>
      <path d="M100 80 C108 96 108 108 104 122" stroke="#fff" stroke-width="2" opacity=".3" fill="none"/>
      <path d="M140 80 C132 96 132 108 136 122" stroke="#fff" stroke-width="2" opacity=".3" fill="none"/>
      <circle cx="120" cy="60" r="6" fill="{{ $light }}" class="denyut"/>
      @break

    {{-- ENVIRONMENT — bentang lahan & tunas --}}
    @case('environment')
      <path d="M120 132 L188 94 L120 56 L52 94 Z" fill="url(#{{ $u }}-atas)"/>
      <path d="M120 132 L188 94 L188 104 L120 142 Z" fill="url(#{{ $u }}-kanan)"/>
      <path d="M120 132 L52 94 L52 104 L120 142 Z"   fill="url(#{{ $u }}-kiri)"/>
      <g class="apung">
        <path d="M120 104 V64" stroke="{{ $deep }}" stroke-width="3.4" stroke-linecap="round"/>
        <path d="M120 82 C102 82 94 70 94 60 C112 60 120 70 120 82 Z" fill="{{ $light }}"/>
        <path d="M120 92 C138 92 146 80 146 70 C128 70 120 80 120 92 Z" fill="#fff" opacity=".9"/>
      </g>
      @break

    {{-- ENGINEERING — roda gigi bertaut --}}
    @default
      <g class="putar-lambat" style="transform-origin:106px 92px">
        <circle cx="106" cy="92" r="30" fill="url(#{{ $u }}-atas)"/>
        <circle cx="106" cy="92" r="13" fill="{{ $deep }}"/>
        @for ($i = 0; $i < 8; $i++)
          <rect x="101" y="52" width="10" height="13" rx="2.5" fill="{{ $base }}"
                transform="rotate({{ $i * 45 }} 106 92)"/>
        @endfor
      </g>
      <g class="putar-lambat" style="transform-origin:160px 120px; animation-direction:reverse">
        <circle cx="160" cy="120" r="20" fill="url(#{{ $u }}-kiri)"/>
        <circle cx="160" cy="120" r="8.5" fill="{{ $deep }}"/>
        @for ($i = 0; $i < 6; $i++)
          <rect x="156" y="94" width="8" height="10" rx="2" fill="{{ $light }}"
                transform="rotate({{ $i * 60 }} 160 120)"/>
        @endfor
      </g>
  @endswitch
  </g>
</svg>
