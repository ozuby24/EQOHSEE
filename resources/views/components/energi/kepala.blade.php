@props(['judul', 'ket' => null, 'dari' => null, 'sampai' => null, 'rute' => null])

{{-- Kepala halaman Energy: judul, penjelasan singkat, dan penyaring rentang
     bila halamannya memang bergantung pada periode. --}}
<section class="kartu-lux rounded-2xl p-6">
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
      <h2 class="font-display text-[20px] font-black text-cam-ink leading-tight">{{ $judul }}</h2>
      @if($ket)<p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-2xl">{{ $ket }}</p>@endif
    </div>

    @if($rute && $dari && $sampai)
      <x-rentang :dari="$dari" :sampai="$sampai" :rute="$rute" />
    @endif
  </div>

  {{ $slot }}
</section>
