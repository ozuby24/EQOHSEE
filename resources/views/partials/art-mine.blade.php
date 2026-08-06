{{-- Ilustrasi tambang terbuka berundak — SVG mandiri, tajam di semua ukuran --}}
<svg viewBox="0 0 800 420" class="w-full h-full" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
  <defs>
    <linearGradient id="mSky" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"  stop-color="#1b1817"/>
      <stop offset="55%" stop-color="#232a1c"/>
      <stop offset="100%" stop-color="#2e3a1e"/>
    </linearGradient>
    <linearGradient id="mSun" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#a3e635" stop-opacity=".55"/>
      <stop offset="100%" stop-color="#65a30d" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="mTier" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#3f4a2a"/>
      <stop offset="100%" stop-color="#1f2415"/>
    </linearGradient>
    <linearGradient id="mHaze" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#a3e635" stop-opacity=".18"/>
      <stop offset="100%" stop-color="#a3e635" stop-opacity="0"/>
    </linearGradient>
  </defs>

  <rect width="800" height="420" fill="url(#mSky)"/>
  <circle cx="620" cy="118" r="150" fill="url(#mSun)"/>

  {{-- punggung bukit jauh --}}
  <path d="M0 196 L118 158 L214 186 L318 142 L432 182 L540 150 L660 184 L800 152 L800 420 L0 420 Z"
        fill="#1e2417" opacity=".85"/>

  {{-- undakan tambang terbuka --}}
  <g fill="url(#mTier)">
    <path d="M0 236 L156 236 L196 262 L800 262 L800 420 L0 420 Z"/>
    <path d="M62 262 L228 262 L268 292 L800 292 L800 420 L62 420 Z" opacity=".92"/>
    <path d="M148 292 L306 292 L346 324 L800 324 L800 420 L148 420 Z" opacity=".88"/>
    <path d="M246 324 L392 324 L432 358 L800 358 L800 420 L246 420 Z" opacity=".84"/>
  </g>

  {{-- garis tepi undakan (aksen lime tipis) --}}
  <g stroke="#84cc16" stroke-opacity=".30" stroke-width="1.6" fill="none">
    <path d="M0 236 L156 236 L196 262 L800 262"/>
    <path d="M62 262 L228 262 L268 292 L800 292"/>
    <path d="M148 292 L306 292 L346 324 L800 324"/>
  </g>

  {{-- jalan angkut --}}
  <path d="M92 420 L246 300 L268 300 L138 420 Z" fill="#4a5533" opacity=".55"/>

  {{-- ekskavator (siluet) --}}
  <g transform="translate(392,236) scale(.92)" fill="#0f120b" opacity=".9">
    <rect x="0" y="18" width="46" height="20" rx="4"/>
    <rect x="6" y="4"  width="26" height="16" rx="3"/>
    <path d="M32 10 L86 -12 L92 -4 L40 22 Z"/>
    <path d="M86 -12 L104 6 L92 16 L78 -2 Z"/>
    <rect x="-2" y="36" width="52" height="8" rx="4" fill="#0b0d08"/>
  </g>

  {{-- truk angkut besar --}}
  <g transform="translate(556,296)" fill="#0f120b" opacity=".92">
    <path d="M6 0 L74 0 L84 20 L0 20 Z"/>
    <rect x="0" y="20" width="86" height="14" rx="3"/>
    <rect x="-10" y="8" width="18" height="20" rx="3"/>
    <circle cx="16" cy="38" r="9" fill="#0b0d08"/><circle cx="64" cy="38" r="9" fill="#0b0d08"/>
    <circle cx="16" cy="38" r="3.4" fill="#3f4a2a"/><circle cx="64" cy="38" r="3.4" fill="#3f4a2a"/>
  </g>

  {{-- truk kecil di undakan atas --}}
  <g transform="translate(214,246) scale(.62)" fill="#0f120b" opacity=".8">
    <path d="M6 0 L70 0 L80 18 L0 18 Z"/><rect x="0" y="18" width="82" height="12" rx="3"/>
    <circle cx="16" cy="34" r="8"/><circle cx="62" cy="34" r="8"/>
  </g>

  {{-- kabut/debu --}}
  <rect y="300" width="800" height="120" fill="url(#mHaze)"/>
</svg>
