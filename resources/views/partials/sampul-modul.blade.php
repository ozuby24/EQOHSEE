{{--
  Sampul halaman awal modul, sisi Blade.

  Sepadan dengan resources/js/Components/SampulModul.vue dan membaca
  sumber yang sama (App\Support\SampulModul dan KondisiSitus), supaya
  halaman Blade dan halaman Vue tidak memajang sambutan yang berbeda
  untuk modul yang sama.

  Aturan "kapan muncul" TIDAK ditulis ulang di sini: keduanya bertanya
  pada Menu::ruteAwal() yang sama. Aturan yang ditulis dua kali akan
  berselisih, dan yang terlihat adalah sampul yang muncul di satu
  tampilan tetapi hilang di tampilan lain untuk halaman yang sama.
--}}
@php
  $eqKunciModul = \App\Support\Menu::modulAktif();
  $eqModul      = \App\Support\Menu::modul($eqKunciModul);
  $eqRuteAwal   = \App\Support\Menu::ruteAwal($eqModul);

  $eqSampul = request()->route()?->getName() === $eqRuteAwal
      ? \App\Support\SampulModul::untuk($eqKunciModul)
      : null;

  $eqKondisi = $eqSampul ? \App\Support\KondisiSitus::untuk(auth()->user()) : null;

  $eqWarnaCuaca = [
      'cerah'              => 'background:rgba(251,191,36,.9);color:#451a03',
      'hujan_ringan'       => 'background:rgba(125,211,252,.9);color:#082f49',
      'hujan_sedang'       => 'background:rgba(14,165,233,.9);color:#fff',
      'hujan_lebat'        => 'background:rgba(37,99,235,.9);color:#fff',
      'hujan_sangat_lebat' => 'background:rgba(220,38,38,.9);color:#fff',
  ];
@endphp

@if($eqSampul)
<section class="relative overflow-hidden rounded-2xl border border-stone-200/60 shadow-card mb-5">
  <picture>
    @if($eqSampul['webp'])
      <source srcset="{{ $eqSampul['webp'] }}" type="image/webp">
    @endif
    <img src="{{ $eqSampul['gambar'] }}" alt="{{ $eqSampul['keterangan'] }}"
         class="h-[190px] w-full object-cover sm:h-[230px]"
         width="1600" height="900" decoding="async">
  </picture>

  <div class="absolute inset-0" style="background:linear-gradient(to top,rgba(0,0,0,.8),rgba(0,0,0,.35),rgba(0,0,0,.1))"></div>

  <div class="absolute inset-0 flex flex-col justify-between p-4 sm:p-5">
    <div class="flex flex-wrap items-start justify-end gap-2">
      <div class="flex flex-wrap items-center gap-2">
        @if($eqKondisi && $eqKondisi['cuaca'])
          @php $c = $eqKondisi['cuaca']; @endphp
          <span class="flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold"
                style="{{ $eqWarnaCuaca[$c['kunci']] ?? 'background:rgba(120,113,108,.9);color:#fff' }};backdrop-filter:blur(4px)"
                title="Curah hujan tercatat {{ $c['hujanMm'] }} mm">
            {{ $c['label'] }}
            <small style="font-weight:600;opacity:.8">
              {{ $c['hujanMm'] }} mm@unless($c['hariIni']) @if($c['tanggal']) · {{ $c['tanggal'] }} @endif @endunless
            </small>
          </span>
        @endif

        @if($eqKondisi)
          <span class="rounded-full px-3 py-1 text-[11px] font-bold text-white"
                style="background:rgba(255,255,255,.15);backdrop-filter:blur(4px)">
            {{ $eqKondisi['waktu']['jam'] }} {{ $eqKondisi['waktu']['zona'] }}
          </span>
        @endif
      </div>
    </div>

    <div>
      {{-- Nama modul, bukan judul halaman: judulnya sudah ada di bilah
           atas dan di kepala halaman. Lihat SampulModul.vue. --}}
      <p class="text-[10px] font-bold uppercase tracking-[0.18em]" style="color:rgba(255,255,255,.7)">Modul</p>
      <h2 class="text-lg sm:text-2xl font-extrabold tracking-tight text-white drop-shadow">
        {{ $eqModul['label'] ?? '' }}
      </h2>

      @if($eqKondisi && $eqKondisi['lokasi'])
        @php $l = $eqKondisi['lokasi']; @endphp
        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]" style="color:rgba(255,255,255,.85)">
          <span class="flex items-center gap-1.5">
            <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>
            </svg>
            <b>{{ $l['nama'] ?? $l['perusahaan'] }}</b>
          </span>
          @if($l['koordinat'])
            <span class="font-mono text-[10.5px]" style="color:rgba(255,255,255,.7)">{{ $l['koordinat'] }}</span>
          @endif
          <span style="color:rgba(255,255,255,.6)">{{ $eqKondisi['waktu']['tanggal'] }}</span>
        </div>
      @endif
    </div>
  </div>

  <span class="absolute bottom-1.5 right-2.5 text-[9px]" style="color:rgba(255,255,255,.45)">
    {{ $eqSampul['keterangan'] }}
  </span>
</section>
@endif
