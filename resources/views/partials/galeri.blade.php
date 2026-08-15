{{--
  Galeri lapangan.

  Digeser mendatar dengan scroll-snap, bukan dengan carousel bertimer:
  galeri yang berpindah sendiri selalu berpindah tepat ketika orang mulai
  membaca kartunya. Tombol panah menggeser satu kartu; jari dan roda tetap
  bekerja seperti biasa.

  Kartu tanpa berkas foto tidak dikosongkan — ia memakai adegan pilarnya,
  jadi bagian ini sudah utuh sebelum satu pun foto ditaruh.
--}}
@php
  use App\Support\{Media, Pillars};
  $butir = Media::galeriTerisi();
@endphp

<div x-data="{
       geser(arah) {
         const t = this.$refs.rel;
         t.scrollBy({ left: arah * (t.firstElementChild?.offsetWidth + 16 || 300), behavior: 'smooth' });
       },
       tonton(url, judul) { this.video = url; this.judul = judul },
       video: null, judul: '',
     }">

  <div class="flex flex-wrap items-end justify-between gap-3">
    <div>
      <h3 class="font-display text-[19px] md:text-[22px] font-black text-white">Video &amp; Galeri Lapangan</h3>
      <p class="text-[12.5px] text-white/45 mt-1.5 leading-relaxed">
        Bagaimana EQOHSEE dipakai di lokasi tambang — dari inspeksi harian sampai pemantauan energi.
      </p>
    </div>

    <div class="flex gap-2 shrink-0">
      @foreach ([['-1','M15 5l-7 7 7 7'], ['1','M9 5l7 7-7 7']] as [$arah, $jalur])
        <button type="button" @click="geser({{ $arah }})"
                class="w-9 h-9 rounded-xl glass grid place-items-center text-white/70 hover:text-white hover:bg-white/15 transition"
                aria-label="{{ $arah === '1' ? 'Geser ke kanan' : 'Geser ke kiri' }}">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $jalur }}"/>
          </svg>
        </button>
      @endforeach
    </div>
  </div>

  <div x-ref="rel"
       class="flex gap-4 mt-5 overflow-x-auto snap-x snap-mandatory scrollbar-halus tepi-larut pb-2 -mx-1 px-1">
    @foreach($butir as $g)
      @php
        $gambar = Media::url($g['gambar']);
        $video  = Media::url($g['video'] ?? null);
        $p      = Pillars::get($g['aspek']);
      @endphp

      <figure class="snap-start shrink-0 w-[248px] sm:w-[272px] rounded-2xl overflow-hidden kaca-gelap group">
        <div class="relative aspect-video overflow-hidden bg-cam-ink">
          @if($gambar)
            <img src="{{ $gambar }}" alt="{{ $g['judul'] }}" loading="lazy"
                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-[1.06]">
          @else
            {{-- Belum ada foto. Bidang berwarna aspeknya, bukan adegan
                 tiruan: enam kartu dengan ilustrasi yang mirip satu sama
                 lain hanya terbaca sebagai galeri yang rusak. Ini terbaca
                 sebagai tempat foto yang memang belum diisi. --}}
            <div class="absolute inset-0 grid place-items-center"
                 style="background:linear-gradient(135deg,{{ $p['warna'] }}3D 0%,{{ $p['deep'] }}66 55%,#1B242200 100%)">
              <span class="absolute inset-0 grid-tech opacity-30"></span>
              <svg class="relative w-9 h-9 text-white/25" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 16.5 8.2 11l4 4 2.6-2.6L21 18M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1Zm4.6 4.6a1.3 1.3 0 11-2.6 0 1.3 1.3 0 012.6 0Z"/>
              </svg>
            </div>
          @endif

          <div class="absolute inset-0 bg-gradient-to-t from-cam-black/75 via-transparent to-transparent"></div>

          @if($video)
            <button type="button" @click="tonton('{{ $video }}', @js($g['judul']))"
                    class="absolute inset-0 grid place-items-center"
                    aria-label="Putar video {{ $g['judul'] }}">
              <span class="w-11 h-11 rounded-full glass-panel grid place-items-center text-white
                           transition group-hover:scale-110 group-hover:bg-white/25">
                <svg class="w-4 h-4 ml-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
              </span>
            </button>
          @endif

          {{-- Nama aspek yang panjang dipendekkan, bukan dipotong di tengah:
               "OCCUPATIONAL…" tidak memberi tahu apa pun. --}}
          <span class="absolute left-3 top-3 text-[9px] font-bold uppercase tracking-wider px-2 py-1 rounded-lg text-white"
                style="background:{{ $p['warna'] }}CC">{{ \Illuminate\Support\Str::of($p['nama'])->replace('Occupational Health', 'Occ. Health')->replace('Konservasi Minerba', 'Konservasi') }}</span>
        </div>

        <figcaption class="p-4">
          <div class="text-[12.5px] font-bold text-white">{{ $g['judul'] }}</div>
          <p class="text-[11px] text-white/45 mt-1 leading-relaxed">{{ $g['ket'] }}</p>
        </figcaption>
      </figure>
    @endforeach
  </div>

  {{-- Pemutar video. Dipindahkan ke <body> karena bagian di sekelilingnya
       membuat konteks penumpukannya sendiri — selubung gelap yang tinggal
       di dalamnya tidak akan pernah menutupi seluruh layar. --}}
  <template x-teleport="body">
  <div x-show="video" x-cloak @keydown.escape.window="video = null"
       x-transition:enter="transition duration-300 ease-out"
       x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
       x-transition:leave="transition duration-200 ease-in"
       x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
       class="fixed inset-0 z-50 grid place-items-center bg-black/85 backdrop-blur-sm p-5"
       @click.self="video = null" role="dialog" aria-modal="true">
    <div class="w-full max-w-3xl">
      <div class="flex items-center justify-between gap-3 mb-3">
        <span class="text-[13px] font-bold text-white" x-text="judul"></span>
        <button type="button" @click="video = null"
                class="w-8 h-8 rounded-lg glass grid place-items-center text-white/70 hover:text-white transition"
                aria-label="Tutup">
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/>
          </svg>
        </button>
      </div>
      <video x-bind:src="video" class="w-full rounded-2xl shadow-2xl" controls autoplay playsinline
             @loadeddata="$el.play().catch(() => {})"></video>
    </div>
  </div>
  </template>
</div>
