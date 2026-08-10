@props(['dari', 'sampai', 'rute', 'gelap' => false])

{{-- Penyaring rentang tanggal, dipakai seluruh halaman Energy.
     Dibuat satu komponen agar tanggal yang dipilih tidak hilang saat
     berpindah halaman dan bentuknya tidak berbeda-beda. --}}
<form method="GET" action="{{ $rute }}" class="flex flex-wrap items-end gap-2.5">
  @foreach (['dari' => $dari, 'sampai' => $sampai] as $nama => $nilai)
    <div>
      <label class="block text-[10px] font-bold uppercase tracking-wide mb-1 {{ $gelap ? 'text-white/55' : 'text-stone-500' }}">
        {{ ucfirst($nama) }}
      </label>
      <input type="date" name="{{ $nama }}" value="{{ $nilai->format('Y-m-d') }}"
             class="ring-focus rounded-xl px-3 py-2 text-[12.5px] transition
                    {{ $gelap ? 'glass-panel text-white border-white/20 [color-scheme:dark]' : 'border border-stone-200' }}">
    </div>
  @endforeach

  <button class="rounded-xl px-4 py-2 text-[12.5px] font-bold transition hover:brightness-110
                 {{ $gelap ? 'bg-white text-cam-ink' : 'bg-cam-ink text-white' }}">Terapkan</button>
</form>
