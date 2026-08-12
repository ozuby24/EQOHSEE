{{--
  Panorama tambang terbuka untuk latar hero.

  Kedalaman dibangun seperti pada foto lanskap tambang sungguhan: empat
  lapis punggungan yang makin jauh makin pucat dan makin kehilangan
  kontras, pita kabut yang memisahkan tiap lapis, cahaya matahari rendah
  dengan berkas yang menembus debu, dan bayangan yang jatuh searah.

  Gerak mengikuti jarak — truk yang jauh melintas lebih lambat daripada
  yang dekat. Itulah yang membuat bidangnya terbaca sebagai ruang, bukan
  sebagai tumpukan bentuk.

  SVG mandiri tanpa aset luar, jadi tetap tampil penuh saat jaringan site
  tambang terputus.
--}}
{{-- Titik jangkar dapat diatur. Pada bidang yang lebih tinggi daripada
     panoramanya, menjangkarkan ke bawah menahan alat berat tetap di pita
     bawah dan menyerahkan bidang atas kepada langit — persis cara sebuah
     foto hero disusun. Menjangkar ke tengah malah menaruh alat berat
     tepat di belakang teksnya. --}}
<svg viewBox="0 0 1600 720" class="w-full h-full" preserveAspectRatio="{{ $jangkar ?? 'xMidYMid' }} slice" aria-hidden="true">
  <defs>
    {{-- Langit fajar: charcoal di puncak, menghangat ke arah matahari --}}
    <linearGradient id="hSky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0"   stop-color="#141C1B"/>
      <stop offset=".42" stop-color="#1B2422"/>
      <stop offset=".74" stop-color="#22403C"/>
      <stop offset="1"   stop-color="#DC6E00"/>
    </linearGradient>

    <radialGradient id="hSun" cx=".68" cy=".62" r=".52">
      <stop offset="0"   stop-color="#FFF1DA" stop-opacity="1"/>
      <stop offset=".16" stop-color="#FFD3A2" stop-opacity=".68"/>
      <stop offset=".42" stop-color="#FF9A63" stop-opacity=".30"/>
      <stop offset="1"   stop-color="#FF7F50" stop-opacity="0"/>
    </radialGradient>

    {{-- Cuci hangat mendatar: sisi matahari benar-benar lebih hangat, bukan
         sekadar lebih terang. Perbedaan suhu warna inilah yang dibaca mata
         sebagai cahaya matahari rendah. --}}
    <linearGradient id="hHangat" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0"   stop-color="#0B1413" stop-opacity=".55"/>
      <stop offset=".38" stop-color="#0B1413" stop-opacity=".12"/>
      <stop offset=".72" stop-color="#FFB57A" stop-opacity=".14"/>
      <stop offset="1"   stop-color="#FF8F5C" stop-opacity=".20"/>
    </linearGradient>

    {{-- Empat tingkat kepekatan punggungan --}}
    <linearGradient id="hR1" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#FF9800" stop-opacity=".20"/>
      <stop offset="1" stop-color="#FF9800" stop-opacity=".08"/>
    </linearGradient>
    <linearGradient id="hR2" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#25867C" stop-opacity=".70"/>
      <stop offset="1" stop-color="#17605A" stop-opacity=".52"/>
    </linearGradient>
    <linearGradient id="hR3" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#17514C"/>
      <stop offset="1" stop-color="#103A37"/>
    </linearGradient>
    <linearGradient id="hR4" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#101A19"/>
      <stop offset="1" stop-color="#0A100F"/>
    </linearGradient>

    {{-- Jalan hauling: pasir yang memantulkan cahaya matahari --}}
    <linearGradient id="hJalan" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0"   stop-color="#F5E6CA" stop-opacity=".10"/>
      <stop offset=".55" stop-color="#F5E6CA" stop-opacity=".30"/>
      <stop offset="1"   stop-color="#F5E6CA" stop-opacity=".08"/>
    </linearGradient>

    <linearGradient id="hKabut" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0"  stop-color="#BFE6DF" stop-opacity="0"/>
      <stop offset=".5" stop-color="#BFE6DF" stop-opacity=".30"/>
      <stop offset="1"  stop-color="#BFE6DF" stop-opacity="0"/>
    </linearGradient>

    <linearGradient id="hBerkas" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#FFE0B8" stop-opacity=".22"/>
      <stop offset="1" stop-color="#FFE0B8" stop-opacity="0"/>
    </linearGradient>

    <filter id="hBlur" x="-25%" y="-25%" width="150%" height="150%">
      <feGaussianBlur stdDeviation="16"/>
    </filter>
    <filter id="hBlurKecil" x="-40%" y="-40%" width="180%" height="180%">
      <feGaussianBlur stdDeviation="4"/>
    </filter>
  </defs>

  {{-- ── Langit dan matahari rendah ── --}}
  <rect width="1600" height="720" fill="url(#hSky)"/>
  <rect width="1600" height="720" fill="url(#hSun)"/>
  <circle cx="1088" cy="446" r="74" fill="#FFE3BE" opacity=".55" filter="url(#hBlur)"/>
  <circle cx="1088" cy="446" r="27" fill="#FFF6E6" opacity=".95"/>
  {{-- Pantulan matahari di kabut tepat di bawahnya --}}
  <ellipse cx="1088" cy="470" rx="150" ry="10" fill="#FFD9AE" opacity=".22" filter="url(#hBlurKecil)"/>

  {{-- Berkas cahaya menembus debu; miring mengikuti arah matahari --}}
  <g class="berkas-cahaya" opacity=".55">
    <path d="M1088 446 L910 0 L1010 0 Z"  fill="url(#hBerkas)"/>
    <path d="M1088 446 L1112 0 L1216 0 Z" fill="url(#hBerkas)"/>
    <path d="M1088 446 L1330 0 L1408 0 Z" fill="url(#hBerkas)"/>
  </g>

  {{-- Awan tipis, hanyut sangat lambat --}}
  <g class="awan-hero" opacity=".4">
    <ellipse cx="330" cy="150" rx="180" ry="15" fill="#BFE6DF" opacity=".22"/>
    <ellipse cx="470" cy="182" rx="120" ry="10" fill="#BFE6DF" opacity=".15"/>
    <ellipse cx="1240" cy="140" rx="150" ry="12" fill="#FFE0B8" opacity=".16"/>
  </g>

  {{-- ── Lapis 1: punggungan terjauh ── --}}
  <path d="M0 372 L150 322 L268 358 L392 296 L520 348 L648 306 L792 356 L920 312 L1064 352
           L1200 308 L1330 350 L1460 318 L1600 356 L1600 720 L0 720 Z" fill="url(#hR1)"/>
  <rect x="0" y="352" width="1600" height="44" fill="url(#hKabut)" class="kabut-hero"/>

  {{-- ── Lapis 2: dinding tambang berjenjang ── --}}
  <path d="M0 434 L190 400 L360 430 L540 392 L720 428 L900 396 L1080 430 L1270 398 L1450 432 L1600 408
           L1600 720 L0 720 Z" fill="url(#hR2)"/>
  <g stroke="#F5E6CA" stroke-opacity=".10" stroke-width="2" fill="none">
    <path d="M40 452 L1570 428"/><path d="M70 472 L1550 450"/><path d="M110 492 L1520 472"/>
  </g>
  <rect x="0" y="424" width="1600" height="34" fill="url(#hKabut)" class="kabut-hero" style="--tunda:5s"/>

  {{-- ── Lapis 3: pit utama dengan jalan hauling ── --}}
  <path d="M0 520 L210 486 L420 522 L640 484 L860 520 L1080 486 L1300 522 L1600 490
           L1600 720 L0 720 Z" fill="url(#hR3)"/>

  {{-- jalan hauling melintasi jenjang --}}
  <path d="M-40 556 L1640 520" stroke="url(#hJalan)" stroke-width="17" fill="none"/>
  <path d="M-40 556 L1640 520" stroke="#F5E6CA" stroke-opacity=".14" stroke-width="1.5" fill="none"/>

  {{-- Konvoi truk di jalan jauh: kecil, pelan, kontras rendah --}}
  <g class="konvoi-jauh" opacity=".8">
    @foreach ([0, 300, 640] as $i => $dx)
      <g transform="translate({{ $dx }} {{ -$i * 6 }})">
        <path d="M120 548 L164 547 L170 531 L126 532 Z" fill="#0A100F" fill-opacity=".95"/>
        <path d="M164 547 L186 546 L186 534 L177 534 L172 540 L164 540 Z" fill="#1E5F58"/>
        <circle cx="134" cy="551" r="5" fill="#0A100F"/><circle cx="172" cy="550" r="5" fill="#0A100F"/>
        <circle cx="184" cy="531" r="2.6" fill="#FFC46B" class="suar-hero" style="--tunda:{{ $i*0.4 }}s"/>
      </g>
    @endforeach
  </g>

  {{-- Instalasi pengolahan di kejauhan --}}
  <g opacity=".85" transform="translate(1280 430)">
    <path d="M0 92 L0 22 L16 22 L16 92 Z" fill="#0A100F" fill-opacity=".92"/>
    <path d="M34 92 L34 6 L48 6 L48 92 Z" fill="#0A100F" fill-opacity=".92"/>
    <path d="M16 30 L34 18" stroke="#FF9800" stroke-opacity=".7" stroke-width="3"/>
    <path d="M60 92 L60 44 L124 30 L124 92 Z" fill="#0A100F" fill-opacity=".9"/>
    {{-- konveyor miring ke tumpukan --}}
    <path d="M124 44 L206 78" stroke="#1E5F58" stroke-width="7" stroke-linecap="round"/>
    <path d="M124 44 L206 78" stroke="#FF9800" stroke-opacity=".5" stroke-width="2" stroke-linecap="round"/>
    <path d="M188 92 q22 -26 44 0 Z" fill="#0A100F" fill-opacity=".9"/>
    <circle cx="42" cy="2" r="3" fill="#FF7F50" class="suar-hero"/>
  </g>

  {{-- ── Lapis 4: tepi pit terdekat, gelap sebagai bingkai ── --}}
  <path d="M0 604 L240 570 L470 606 L700 566 L940 606 L1180 570 L1420 606 L1600 578
           L1600 720 L0 720 Z" fill="url(#hR4)"/>
  <path d="M0 620 L240 586 L470 622 L700 582 L940 622 L1180 586 L1420 622 L1600 594"
        stroke="#F5E6CA" stroke-opacity=".07" stroke-width="2" fill="none"/>

  {{-- Ekskavator memuat di tepi terdekat --}}
  <g transform="translate(214 546)">
    <path d="M0 56 L96 56 L96 22 L64 22 L54 4 L8 4 Z" fill="#0A100F" fill-opacity=".96"/>
    <path d="M0 56 L96 56 L96 22 L64 22 L54 4 L8 4 Z" fill="none" stroke="#FF9800" stroke-opacity=".55" stroke-width="2"/>
    <rect x="14" y="14" width="30" height="17" rx="3" fill="#FF9800" opacity=".5"/>
    <path d="M-8 62 L104 62 L96 78 L0 78 Z" fill="#0A100F" fill-opacity=".96"/>
    <path d="M4 70 L92 70" stroke="#FF9800" stroke-opacity=".35" stroke-width="3"/>
    <g class="lengan-hero" style="transform-origin: 92px 24px">
      <path d="M92 24 L182 -20" stroke="#1E5F58" stroke-width="10" stroke-linecap="round"/>
      <path d="M182 -20 L222 20"  stroke="#1E5F58" stroke-width="8"  stroke-linecap="round"/>
      <path d="M215 16 q24 8 20 30 q-26 4 -32 -18 Z" fill="#123F3B" stroke="#FF9800" stroke-opacity=".5" stroke-width="1.5"/>
    </g>
    <circle cx="60" cy="0" r="3.4" fill="#FF7F50" class="suar-hero" style="--tunda:.5s"/>
  </g>

  {{-- Truk terdekat: paling besar, paling cepat, kontras paling tinggi --}}
  <g class="truk-dekat">
    <g transform="translate(0 0)">
      <path d="M760 606 L884 604 L900 556 L780 558 Z" fill="#080D0D"/>
      <path d="M760 606 L884 604 L900 556 L780 558 Z" fill="none" stroke="#FF9800" stroke-opacity=".7" stroke-width="2.5" stroke-linejoin="round"/>
      <path d="M786 570 L894 568" stroke="#FF9800" stroke-opacity=".38" stroke-width="3"/>
      {{-- muatan --}}
      <path d="M784 558 q26 -14 46 -3 q22 -14 48 2 Z" fill="#FF9800" opacity=".38"/>
      {{-- kabin --}}
      <path d="M884 604 L942 603 L942 562 L916 562 L902 580 L884 580 Z" fill="#14504B"/>
      <rect x="912" y="566" width="24" height="15" rx="3" fill="#080D0D" opacity=".8"/>
      {{-- roda --}}
      <circle cx="800" cy="612" r="20" fill="#080D0D"/><circle cx="800" cy="612" r="8" fill="#FF9800" opacity=".75"/>
      <circle cx="906" cy="612" r="20" fill="#080D0D"/><circle cx="906" cy="612" r="8" fill="#FF9800" opacity=".75"/>
      {{-- lampu suar di atas kabin --}}
      <circle cx="930" cy="556" r="5" fill="#FFC46B" class="suar-hero"/>
      {{-- sorot lampu depan menyapu jalan --}}
      <path d="M942 590 L1030 578 L1030 600 L942 600 Z" fill="#FFE9C9" opacity=".10" filter="url(#hBlurKecil)"/>
    </g>
    {{-- debu terangkat di belakang roda --}}
    <ellipse cx="778" cy="618" rx="44" ry="11" fill="#F5E6CA" opacity=".16" class="debu-hero"/>
    <ellipse cx="742" cy="614" rx="30" ry="8"  fill="#F5E6CA" opacity=".10" class="debu-hero" style="--tunda:.7s"/>
  </g>

  {{-- Rambu batas kecepatan di tepi jalan --}}
  <g transform="translate(1150 540)">
    <path d="M0 0 L0 62" stroke="#080D0D" stroke-width="5" stroke-linecap="round"/>
    <circle cx="0" cy="-12" r="17" fill="#F3EFE6" opacity=".9"/>
    <circle cx="0" cy="-12" r="17" fill="none" stroke="#E2663A" stroke-width="5"/>
  </g>

  {{-- Burung jauh — penanda skala dan kehidupan --}}
  <g class="burung-hero" opacity=".4" stroke="#BFE6DF" stroke-width="2.5" fill="none" stroke-linecap="round">
    <path d="M560 214 q11 -8 22 0"/><path d="M596 232 q9 -7 18 0"/><path d="M624 208 q8 -6 16 0"/>
  </g>

  {{-- Cuci hangat menyatukan seluruh lapisan di bawah satu cahaya --}}
  <rect width="1600" height="720" fill="url(#hHangat)" style="mix-blend-mode:soft-light"/>

  {{-- Butir debu melayang di seluruh bidang --}}
  <g class="debu-udara">
    @foreach ([[180,470,.5],[420,520,.35],[700,468,.45],[980,510,.3],[1240,486,.4],[1440,520,.28],[300,560,.35],[1080,566,.3]] as $i => [$x,$y,$o])
      <circle cx="{{ $x }}" cy="{{ $y }}" r="3" fill="#F5E6CA" opacity="{{ $o }}"
              class="butir-hero" style="--tunda: {{ $i*1.1 }}s"/>
    @endforeach
  </g>
</svg>
