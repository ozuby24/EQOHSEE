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

  /* Warna aksen per kelas — sepadan dengan AKSEN di KartuCuaca.vue. */
  $eqAksen = [
      'cerah'              => '#FBBF24',
      'hujan_ringan'       => '#67E8F9',
      'hujan_sedang'       => '#38BDF8',
      'hujan_lebat'        => '#818CF8',
      'hujan_sangat_lebat' => '#F87171',
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
      @if($eqKondisi)
        {{-- Jam berdiri sendiri dari kartu cuaca: jam selalu ada,
             cuacanya hanya ada bila situsnya mencatat hujan. --}}
        <div class="eq-cuaca flex flex-col items-end rounded-xl px-3 py-2 leading-none">
          <b class="text-[15px] font-extrabold tabular-nums text-white">{{ $eqKondisi['waktu']['jam'] }}</b>
          <small class="mt-1 text-[9.5px] font-bold uppercase tracking-wider" style="color:rgba(255,255,255,.6)">
            {{ $eqKondisi['waktu']['zona'] }}
          </small>
        </div>
      @endif

      @if($eqKondisi && $eqKondisi['cuaca'])
        @php
          $c      = $eqKondisi['cuaca'];
          $aksen  = $eqAksen[$c['kunci']] ?? '#D6D3D1';
          $cerah  = $c['kunci'] === 'cerah';
        @endphp

        {{-- Angka, nama, dan kedudukan pada skala lima tingkat digambar
             sekaligus — lihat KartuCuaca.vue untuk alasannya. --}}
        <div class="eq-cuaca flex items-center gap-3 rounded-xl px-3 py-2"
             title="Curah hujan tercatat {{ $c['hujanMm'] }} mm — {{ $c['label'] }}">
          <svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            @if($cerah)
              <g class="eq-cuaca-surya" stroke="{{ $aksen }}" stroke-width="1.8" stroke-linecap="round">
                <circle cx="12" cy="12" r="4" fill="{{ $aksen }}" fill-opacity=".28"/>
                <path d="M12 3.2v2.1M12 18.7v2.1M4.8 4.8l1.5 1.5M17.7 17.7l1.5 1.5M3.2 12h2.1M18.7 12h2.1M4.8 19.2l1.5-1.5M17.7 6.3l1.5-1.5"/>
              </g>
            @else
              <path d="M7.4 15.6a3.9 3.9 0 0 1 .5-7.8 5.2 5.2 0 0 1 9.9 1.5 3.2 3.2 0 0 1-.7 6.3Z"
                    fill="{{ $aksen }}" fill-opacity=".22" stroke="{{ $aksen }}" stroke-width="1.6"
                    stroke-linejoin="round"/>
              <g stroke="{{ $aksen }}" stroke-width="1.9" stroke-linecap="round">
                @foreach([[7,'0s'],[12,'.35s'],[17,'.7s']] as [$x, $tunda])
                  <line class="eq-cuaca-tetes" x1="{{ $x }}" y1="17.6" x2="{{ $x - 1 }}" y2="20.4"
                        style="--eq-tunda:{{ $tunda }}"/>
                @endforeach
              </g>
            @endif
          </svg>

          <div class="min-w-0">
            <p class="flex items-baseline gap-1 leading-none">
              <b class="text-[19px] font-extrabold tabular-nums text-white">{{ $c['hujanMm'] }}</b>
              <small class="text-[10px] font-bold uppercase tracking-wider" style="color:rgba(255,255,255,.6)">mm</small>
            </p>

            <p class="mt-1 truncate text-[11px] font-bold leading-none" style="color:{{ $aksen }}">
              {{ $c['label'] }}
            </p>

            <div class="mt-1.5 flex items-center gap-2">
              <div class="flex gap-[3px]" role="img"
                   aria-label="Tingkat {{ $c['tingkat'] + 1 }} dari {{ $c['skala'] }} pada skala curah hujan">
                @for($i = 0; $i < $c['skala']; $i++)
                  <i class="block h-[3px] w-3.5 rounded-full {{ $i === $c['tingkat'] ? 'eq-cuaca-aktif' : '' }}"
                     style="background:{{ $i <= $c['tingkat'] ? $aksen : 'rgba(255,255,255,.22)' }}"></i>
                @endfor
              </div>

              @unless($c['hariIni'])
                @if($c['tanggal'])
                  <small class="text-[9.5px] font-semibold" style="color:rgba(255,255,255,.55)">{{ $c['tanggal'] }}</small>
                @endif
              @endunless
            </div>
          </div>
        </div>
      @endif
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
