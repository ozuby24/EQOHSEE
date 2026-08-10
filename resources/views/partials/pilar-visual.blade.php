{{--
  Adegan motion graphics per pilar.

  Menggantikan blok isometrik sebelumnya. Kedalamannya dibangun dengan cara
  yang dipakai fotografi lanskap tambang: perspektif atmosferik — punggungan
  yang makin jauh makin pucat dan makin biru — ditambah kabut tipis, cahaya
  samping, dan bayangan yang jatuh searah. Itulah yang membuatnya terbaca
  nyata, bukan penambahan sisi kubus.

  Tetap SVG inline, bukan berkas gambar: warnanya ikut palet pilar dari
  registry, tajam pada kepadatan layar berapa pun, dan tetap tampil ketika
  jaringan site tambang terputus.

  Parameter: $slug (kunci pilar), $p (data pilar dari Pillars::all()).
--}}
@php
  $u = $slug;                                   // awalan id agar gradien tidak bentrok antar kartu
  $deep = $p['deep']; $base = $p['warna']; $light = $p['light'];
@endphp

<svg viewBox="0 0 240 200" fill="none" xmlns="http://www.w3.org/2000/svg"
     class="w-full h-auto adegan" role="img" aria-label="Ilustrasi pilar {{ $p['nama'] }}">
  <defs>
    {{-- Langit: terang di dekat sumber cahaya, meredup ke atas --}}
    <linearGradient id="{{ $u }}-langit" x1="0" y1="1" x2="0" y2="0">
      <stop offset="0"   stop-color="{{ $light }}" stop-opacity=".38"/>
      <stop offset=".55" stop-color="{{ $base }}"  stop-opacity=".16"/>
      <stop offset="1"   stop-color="{{ $deep }}"  stop-opacity=".05"/>
    </linearGradient>

    {{-- Tiga tingkat kepekatan punggungan; makin jauh makin pucat --}}
    <linearGradient id="{{ $u }}-jauh" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{{ $base }}" stop-opacity=".22"/>
      <stop offset="1" stop-color="{{ $base }}" stop-opacity=".10"/>
    </linearGradient>
    <linearGradient id="{{ $u }}-tengah" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{{ $base }}" stop-opacity=".55"/>
      <stop offset="1" stop-color="{{ $deep }}" stop-opacity=".38"/>
    </linearGradient>
    {{-- Bidang terdekat dibuat lebih terang daripada punggungan di belakangnya.
         Di lapangan, tanah terdekat memang paling banyak menerima cahaya —
         dan itu pula yang membuat alat berat di atasnya terbaca sebagai
         siluet, bukan lenyap ke dalam latar. --}}
    <linearGradient id="{{ $u }}-dekat" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="{{ $base }}" stop-opacity=".92"/>
      <stop offset="1" stop-color="{{ $deep }}"/>
    </linearGradient>

    {{-- Cahaya matahari rendah dari kiri atas --}}
    <radialGradient id="{{ $u }}-surya" cx=".26" cy=".18" r=".55">
      <stop offset="0"   stop-color="#FFF6E8" stop-opacity=".85"/>
      <stop offset=".38" stop-color="{{ $light }}" stop-opacity=".28"/>
      <stop offset="1"   stop-color="{{ $light }}" stop-opacity="0"/>
    </radialGradient>

    {{-- Kabut lembah: pita putih tipis yang memisahkan lapisan kedalaman --}}
    <linearGradient id="{{ $u }}-kabut" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#FFFFFF" stop-opacity="0"/>
      <stop offset=".5" stop-color="#FFFFFF" stop-opacity=".40"/>
      <stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/>
    </linearGradient>

    <linearGradient id="{{ $u }}-logam" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0"   stop-color="#FFFFFF" stop-opacity=".85"/>
      <stop offset=".45" stop-color="{{ $light }}"/>
      <stop offset="1"   stop-color="{{ $deep }}"/>
    </linearGradient>

    <filter id="{{ $u }}-lembut" x="-30%" y="-30%" width="160%" height="160%">
      <feGaussianBlur stdDeviation="3"/>
    </filter>
    <filter id="{{ $u }}-jatuh" x="-40%" y="-40%" width="180%" height="180%">
      <feDropShadow dx="1.5" dy="4" stdDeviation="3" flood-color="{{ $deep }}" flood-opacity=".38"/>
    </filter>

    <clipPath id="{{ $u }}-bingkai"><rect x="0" y="0" width="240" height="200" rx="16"/></clipPath>
  </defs>

  <g clip-path="url(#{{ $u }}-bingkai)">

    {{-- ── Langit dan matahari ── --}}
    <rect x="0" y="0" width="240" height="200" fill="url(#{{ $u }}-langit)"/>
    <rect x="0" y="0" width="240" height="200" fill="url(#{{ $u }}-surya)"/>
    <circle cx="62" cy="36" r="13" fill="#FFF6E8" opacity=".55" filter="url(#{{ $u }}-lembut)"/>
    <circle cx="62" cy="36" r="6"  fill="#FFFDF7" opacity=".9"/>

    {{-- awan tipis melintas sangat lambat --}}
    <g opacity=".5" class="hanyut-lambat">
      <ellipse cx="150" cy="34" rx="34" ry="5" fill="#FFFFFF" opacity=".42"/>
      <ellipse cx="176" cy="40" rx="22" ry="3.5" fill="#FFFFFF" opacity=".30"/>
    </g>

    {{-- ── Punggungan terjauh ── --}}
    <path d="M0 96 L26 76 L46 88 L72 62 L96 84 L120 70 L146 90 L172 68 L198 86 L222 74 L240 88 L240 200 L0 200 Z"
          fill="url(#{{ $u }}-jauh)"/>

    {{-- kabut lembah --}}
    <rect x="0" y="90" width="240" height="16" fill="url(#{{ $u }}-kabut)" class="kabut"/>

    {{-- ── Punggungan tengah: dinding tambang berjenjang ── --}}
    <path d="M0 118 L38 104 L74 116 L108 100 L148 114 L186 102 L222 116 L240 108 L240 200 L0 200 Z"
          fill="url(#{{ $u }}-tengah)"/>
    {{-- garis jenjang (bench) tipis, penanda tambang terbuka --}}
    <g stroke="#FFFFFF" stroke-opacity=".16" stroke-width="1">
      <path d="M8 126 L232 120"/><path d="M14 136 L228 131"/>
    </g>

    {{-- ── Bidang terdekat ── --}}
    <path d="M0 150 L52 140 L104 150 L158 138 L210 150 L240 144 L240 200 L0 200 Z"
          fill="url(#{{ $u }}-dekat)"/>
    <path d="M0 158 L52 148 L104 158 L158 146 L210 158 L240 152"
          stroke="#FFFFFF" stroke-opacity=".14" stroke-width="1.2" fill="none"/>

    {{-- ══════════════════ Subjek per pilar ══════════════════ --}}
    @switch($slug)

      {{-- ENERGI — menara transmisi dengan aliran daya berdenyut --}}
      @case('energy')
        <g filter="url(#{{ $u }}-jatuh)">
          @foreach ([[64,150],[122,146],[180,150]] as $i => [$x,$y])
            <g transform="translate({{ $x }} {{ $y }})">
              <path d="M-9 0 L-4 -34 L4 -34 L9 0" stroke="url(#{{ $u }}-logam)" stroke-width="2.4" fill="none" stroke-linecap="round"/>
              <path d="M-7 -12 L7 -12 M-5.5 -22 L5.5 -22" stroke="{{ $light }}" stroke-width="1.6" opacity=".85"/>
              <path d="M-11 -30 L11 -30" stroke="url(#{{ $u }}-logam)" stroke-width="2.2" stroke-linecap="round"/>
              <path d="M-6 -34 L6 -34 M0 -34 L0 -40" stroke="{{ $light }}" stroke-width="1.6" stroke-linecap="round"/>
            </g>
          @endforeach
        </g>
        {{-- kabel menggantung; denyut berjalan di atasnya --}}
        <path id="{{ $u }}-kabel" d="M64 114 Q93 126 122 110 Q151 122 180 116" stroke="{{ $deep }}" stroke-opacity=".55" stroke-width="1.4" fill="none"/>
        <path d="M64 114 Q93 126 122 110 Q151 122 180 116" stroke="{{ $light }}" stroke-width="2"
              fill="none" stroke-linecap="round" stroke-dasharray="5 92" class="alir-daya"/>
        @break

      {{-- MUTU — ban berjalan dengan material dan garis pindai pemeriksaan --}}
      @case('quality')
        <g filter="url(#{{ $u }}-jatuh)">
          <path d="M40 152 L184 128" stroke="url(#{{ $u }}-logam)" stroke-width="6" stroke-linecap="round"/>
          <path d="M40 152 L184 128" stroke="{{ $deep }}" stroke-opacity=".45" stroke-width="1" stroke-linecap="round"/>
          @foreach ([[46,151],[110,140],[178,129]] as [$cx,$cy])
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="7" fill="#0E1B1A" fill-opacity=".92"/>
            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3.4" fill="{{ $light }}" opacity=".9"/>
          @endforeach
          <path d="M46 158 L46 176 M178 136 L178 176" stroke="#0E1B1A" stroke-opacity=".9" stroke-width="2.8" stroke-linecap="round"/>
        </g>
        {{-- bongkahan material bergerak sepanjang ban --}}
        <g class="jalan-ban">
          @foreach ([0,1,2,3] as $i)
            <path d="M0 0 l4.5 -3 l4.5 3 l-4.5 2.4 Z" fill="{{ $light }}" opacity=".95"
                  transform="translate({{ 48 + $i*36 }} {{ 148 - $i*6 }})"/>
          @endforeach
        </g>
        {{-- garis pindai memeriksa mutu --}}
        <rect x="86" y="104" width="2" height="44" fill="{{ $light }}" opacity=".75" class="pindai"/>
        @break

      {{-- KESEHATAN KERJA — pemantauan tanda vital pekerja --}}
      @case('occhealth')
        <g filter="url(#{{ $u }}-jatuh)">
          {{-- siluet pekerja berhelm --}}
          <path d="M112 150 L112 128 Q112 120 120 120 Q128 120 128 128 L128 150 Z" fill="#0E1B1A" fill-opacity=".9" stroke="{{ $light }}" stroke-opacity=".6" stroke-width="1.4"/>
          <circle cx="120" cy="112" r="8" fill="#0E1B1A" fill-opacity=".9"/>
          <path d="M110 108 Q120 99 130 108 Z" fill="{{ $light }}"/>
          <path d="M108 108 L132 108" stroke="{{ $light }}" stroke-width="2" stroke-linecap="round"/>
        </g>
        {{-- panel monitor dengan garis detak berjalan --}}
        <g transform="translate(28 96)">
          <rect x="0" y="0" width="64" height="38" rx="5" fill="#0E1B1A" opacity=".9" stroke="{{ $light }}" stroke-opacity=".35"/>
          <path d="M6 22 L16 22 L20 12 L26 30 L32 19 L38 19 L42 24 L58 24"
                stroke="{{ $light }}" stroke-width="1.8" fill="none" stroke-linecap="round" stroke-linejoin="round"
                stroke-dasharray="70" class="detak"/>
        </g>
        <g transform="translate(150 100)">
          <rect x="0" y="0" width="58" height="30" rx="5" fill="#0E1B1A" opacity=".85" stroke="{{ $light }}" stroke-opacity=".35"/>
          @foreach ([0,1,2,3,4] as $i)
            <rect x="{{ 7 + $i*10 }}" y="{{ 20 - $i*2.6 }}" width="5" height="{{ 4 + $i*2.6 }}" rx="1.4"
                  fill="{{ $light }}" opacity=".9" class="batang" style="--tunda: {{ $i*0.16 }}s"/>
          @endforeach
        </g>
        @break

      {{-- HIGIENE INDUSTRI — pengukuran pajanan di tempat kerja --}}
      @case('hygiene')
        <g filter="url(#{{ $u }}-jatuh)">
          {{-- alat ukur pajanan pribadi yang dipasang di pekerja --}}
          <path d="M96 152 L96 126 Q96 118 104 118 Q112 118 112 126 L112 152 Z" fill="#0E1B1A" fill-opacity=".9"
                stroke="{{ $light }}" stroke-opacity=".55" stroke-width="1.3"/>
          <circle cx="104" cy="110" r="7.5" fill="#0E1B1A" fill-opacity=".9"/>
          <path d="M95 106 q9 -8 18 0 Z" fill="{{ $light }}"/>
          {{-- pompa sampel di sabuk, selang naik ke zona pernapasan --}}
          <rect x="110" y="132" width="12" height="15" rx="2.5" fill="{{ $base }}"/>
          <path d="M116 132 q10 -14 -2 -22" stroke="{{ $light }}" stroke-width="1.8" fill="none" stroke-linecap="round"/>
          <circle cx="114" cy="110" r="3" fill="{{ $light }}"/>
        </g>
        {{-- gelombang bising menyebar dari sumber di kanan --}}
        <g transform="translate(186 128)">
          <path d="M0 -6 L0 6" stroke="#0E1B1A" stroke-width="3" stroke-linecap="round"/>
          @foreach ([0,1,2] as $i)
            <path d="M-4 -12 q-12 12 0 24" fill="none" stroke="{{ $light }}" stroke-width="1.6"
                  stroke-linecap="round" class="gelombang" style="--tunda: {{ $i*0.5 }}s"
                  transform="scale({{ 1 + $i*0.55 }})"/>
          @endforeach
        </g>
        {{-- panel hasil ukur terhadap nilai ambang batas --}}
        <g transform="translate(28 100)">
          <rect x="0" y="0" width="58" height="34" rx="5" fill="#0E1B1A" opacity=".9" stroke="{{ $light }}" stroke-opacity=".35"/>
          <path d="M6 26 L52 26" stroke="{{ $light }}" stroke-opacity=".3" stroke-width="1"/>
          {{-- garis NAB; batang yang melewatinya diberi warna peringatan --}}
          <path d="M6 13 L52 13" stroke="#FF7F50" stroke-opacity=".8" stroke-width="1.2" stroke-dasharray="3 3"/>
          @foreach ([[9,18,'l'],[19,10,'x'],[29,21,'l'],[39,15,'l'],[49,8,'x']] as $i => [$x,$t,$j])
            <rect x="{{ $x }}" y="{{ 26 - $t }}" width="6" height="{{ $t }}" rx="1.4"
                  fill="{{ $j === 'x' ? '#FF7F50' : $light }}" opacity=".92"
                  class="batang" style="--tunda: {{ $i*0.18 }}s"/>
          @endforeach
        </g>
        @break

      {{-- KESELAMATAN — truk angkut melintas jalan hauling dengan lampu suar --}}
      @case('safety')
        <g class="melaju">
          <g filter="url(#{{ $u }}-jatuh)">
            {{-- bak muatan --}}
            <path d="M46 142 L98 142 L106 118 L56 118 Z" fill="#0E1B1A" fill-opacity=".92"/>
            <path d="M46 142 L98 142 L106 118 L56 118 Z" fill="none" stroke="{{ $light }}" stroke-opacity=".75" stroke-width="1.6" stroke-linejoin="round"/>
            <path d="M53 124 L102 124" stroke="{{ $light }}" stroke-opacity=".45" stroke-width="1.4"/>
            {{-- muatan batubara --}}
            <path d="M58 118 q10 -6 18 -1 q9 -6 18 1 Z" fill="{{ $light }}" opacity=".5"/>
            {{-- kabin --}}
            <path d="M98 142 L122 142 L122 124 L112 124 L107 132 L98 132 Z" fill="url(#{{ $u }}-logam)"/>
            <rect x="110" y="125" width="10" height="6.5" rx="1.5" fill="#0E1B1A" opacity=".72"/>
            {{-- roda --}}
            <circle cx="64" cy="147" r="9" fill="#0E1B1A"/><circle cx="64" cy="147" r="3.6" fill="{{ $light }}" opacity=".9"/>
            <circle cx="110" cy="147" r="9" fill="#0E1B1A"/><circle cx="110" cy="147" r="3.6" fill="{{ $light }}" opacity=".9"/>
            {{-- lampu suar berkedip di atas kabin --}}
            <circle cx="117" cy="120" r="3.4" fill="#FFC46B" class="suar"/>
          </g>
          {{-- debu terangkat di belakang roda --}}
          <ellipse cx="52" cy="152" rx="18" ry="4.5" fill="#FFFFFF" opacity=".2" class="debu"/>
        </g>
        {{-- rambu batas kecepatan di tepi jalan --}}
        <g transform="translate(196 118)">
          <path d="M0 0 L0 30" stroke="#0E1B1A" stroke-opacity=".9" stroke-width="2.4" stroke-linecap="round"/>
          <circle cx="0" cy="-5" r="8" fill="#FFFFFF" opacity=".92"/>
          <circle cx="0" cy="-5" r="8" fill="none" stroke="#E2663A" stroke-width="2.4"/>
        </g>
        @break

      {{-- LINGKUNGAN — reklamasi: kolam pengendap beriak dan tanaman tumbuh --}}
      @case('environment')
        {{-- kolam pengendap --}}
        <ellipse cx="120" cy="164" rx="76" ry="18" fill="{{ $deep }}" opacity=".55"/>
        <ellipse cx="120" cy="162" rx="70" ry="14" fill="{{ $light }}" opacity=".35"/>
        @foreach ([0,1,2] as $i)
          <ellipse cx="120" cy="162" rx="20" ry="4" fill="none" stroke="#FFFFFF" stroke-opacity=".5"
                   stroke-width="1" class="riak" style="--tunda: {{ $i*1.3 }}s"/>
        @endforeach
        {{-- pepohonan reklamasi, tumbuh bergiliran --}}
        @foreach ([[46,150,1],[74,144,.86],[168,146,.92],[196,151,1.05]] as $i => [$x,$y,$s])
          <g transform="translate({{ $x }} {{ $y }}) scale({{ $s }})" class="tumbuh" style="--tunda: {{ $i*0.4 }}s">
            <path d="M0 0 L0 -14" stroke="#0E1B1A" stroke-opacity=".85" stroke-width="2.2" stroke-linecap="round"/>
            <path d="M0 -12 q-11 -3 -9 -13 q10 1 9 13 Z" fill="{{ $base }}"/>
            <path d="M0 -12 q11 -3 9 -13 q-10 1 -9 13 Z" fill="{{ $light }}"/>
            <path d="M0 -18 q-7 -6 -1 -13 q7 6 1 13 Z" fill="{{ $base }}" opacity=".9"/>
          </g>
        @endforeach
        {{-- burung jauh, penanda kehidupan kembali --}}
        <g opacity=".45" class="hanyut-lambat">
          <path d="M150 58 q4 -3 8 0 M162 62 q3.4 -2.6 6.8 0" stroke="{{ $deep }}" stroke-width="1.2" fill="none" stroke-linecap="round"/>
        </g>
        @break

      {{-- REKAYASA — lengan ekskavator bergerak di atas kisi survei --}}
      @case('engineering')
        {{-- kisi survei --}}
        <g stroke="{{ $light }}" stroke-opacity=".30" stroke-width=".8">
          @foreach ([0,1,2,3,4] as $i)
            <path d="M{{ 40 + $i*40 }} 176 L{{ 76 + $i*22 }} 128"/>
          @endforeach
          <path d="M30 168 L214 168"/><path d="M52 152 L196 152"/><path d="M68 138 L180 138"/>
        </g>
        <g filter="url(#{{ $u }}-jatuh)">
          {{-- badan --}}
          <path d="M62 152 L110 152 L110 136 L96 136 L92 128 L66 128 Z" fill="#0E1B1A" fill-opacity=".92" stroke="{{ $light }}" stroke-opacity=".65" stroke-width="1.5" stroke-linejoin="round"/>
          <rect x="70" y="132" width="16" height="9" rx="2" fill="{{ $light }}" opacity=".75"/>
          <path d="M58 154 L114 154 L110 162 L62 162 Z" fill="#0E1B1A" opacity=".92"/>
          <path d="M64 158 L108 158" stroke="{{ $light }}" stroke-opacity=".45" stroke-width="1.4"/>
          {{-- lengan yang berayun --}}
          <g class="ayun-lengan" style="transform-origin: 106px 136px">
            <path d="M106 136 L146 116" stroke="url(#{{ $u }}-logam)" stroke-width="5" stroke-linecap="round"/>
            <path d="M146 116 L164 134" stroke="url(#{{ $u }}-logam)" stroke-width="4.2" stroke-linecap="round"/>
            <path d="M160 132 q10 4 8 13 q-11 2 -14 -8 Z" fill="{{ $base }}"/>
          </g>
        </g>
        @break

      {{-- KONSERVASI MINERBA — timbunan berjenjang kadar dan pengeboran inti --}}
      @case('konservasi')
        {{-- timbunan dipisah menurut kadar; yang rendah tetap disimpan --}}
        <g filter="url(#{{ $u }}-jatuh)">
          <path d="M28 156 q34 -34 68 0 Z" fill="#0E1B1A" fill-opacity=".92"/>
          <path d="M40 148 q22 -20 44 0" stroke="{{ $light }}" stroke-opacity=".5" stroke-width="1.4" fill="none"/>
          <path d="M52 140 q10 -9 20 0"  stroke="{{ $light }}" stroke-opacity=".35" stroke-width="1.2" fill="none"/>

          <path d="M104 156 q26 -25 52 0 Z" fill="{{ $deep }}"/>
          <path d="M114 149 q16 -14 32 0" stroke="{{ $light }}" stroke-opacity=".45" stroke-width="1.3" fill="none"/>

          <path d="M164 156 q18 -17 36 0 Z" fill="{{ $base }}" opacity=".75"/>
        </g>
        {{-- menara bor mengambil contoh inti --}}
        <g transform="translate(178 96)">
          <path d="M-10 34 L0 0 L10 34" stroke="url(#{{ $u }}-logam)" stroke-width="2.2" fill="none" stroke-linecap="round"/>
          <path d="M-6 20 L6 20 M-8 28 L8 28" stroke="{{ $light }}" stroke-width="1.3" opacity=".8"/>
          <path d="M0 4 L0 40" stroke="{{ $light }}" stroke-width="1.8" stroke-linecap="round" class="bor"/>
        </g>
        {{-- neraca cadangan: bilah terisi yang menyusut ke kanan --}}
        <g transform="translate(28 104)">
          <rect x="0" y="0" width="60" height="30" rx="5" fill="#0E1B1A" opacity=".88" stroke="{{ $light }}" stroke-opacity=".32"/>
          @foreach ([[7,44],[7,32],[7,20]] as $i => [$x,$w])
            <rect x="7" y="{{ 7 + $i*8 }}" width="46" height="4" rx="2" fill="{{ $light }}" opacity=".18"/>
            <rect x="7" y="{{ 7 + $i*8 }}" width="{{ $w }}" height="4" rx="2" fill="{{ $light }}" opacity=".85"
                  class="bilah" style="--tunda: {{ $i*0.3 }}s"/>
          @endforeach
        </g>
        @break

    @endswitch

    {{-- Butir debu halus melayang di seluruh adegan — perekat suasana --}}
    <g class="debu-halus">
      @foreach ([[38,128,.9],[92,112,.6],[148,126,.8],[196,110,.55],[122,148,.7],[64,164,.5]] as $i => [$x,$y,$o])
        <circle cx="{{ $x }}" cy="{{ $y }}" r="1.2" fill="#FFFFFF" opacity="{{ $o * 0.5 }}"
                class="butir" style="--tunda: {{ $i*0.9 }}s"/>
      @endforeach
    </g>

    {{-- Vignet: menggelapkan tepi supaya pusat adegan maju --}}
    <rect x="0" y="0" width="240" height="200" fill="none" stroke="{{ $deep }}" stroke-opacity=".08" stroke-width="14" rx="16"/>
  </g>
</svg>
