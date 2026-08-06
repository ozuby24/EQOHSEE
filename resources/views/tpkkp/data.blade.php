@extends('layouts.app')
@section('title','PTPKKP — Data')

@section('content')
@php $bisa = auth()->user()->isAdmin(); @endphp
<div class="max-w-4xl mx-auto space-y-5">
  @include('tpkkp._flash')
  @include('tpkkp._picker')

  <div class="grid sm:grid-cols-4 gap-3">
    @foreach([['Sel terisi', $hasil['filledCells'].' / '.$hasil['totalCells']],
              ['Kelengkapan', number_format($hasil['completeness'] * 100, 1).'%'],
              ['Program', count($a->programs ?? [])],
              ['Kegiatan jadwal', count($a->jadwal ?? [])]] as $s)
      <div class="bg-white rounded-2xl border border-stone-200 px-4 py-3.5">
        <div class="stat text-[19px] leading-none">{{ $s[1] }}</div>
        <div class="text-[10px] uppercase tracking-wider text-stone-400 font-bold mt-1.5">{{ $s[0] }}</div>
      </div>
    @endforeach
  </div>

  <div class="bg-white rounded-2xl border border-stone-200 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-1">Ekspor</h3>
    <p class="text-[11.5px] text-stone-500 mb-3">
      Unduh seluruh isi penilaian periode {{ $a->tahun }} sebagai JSON — nilai, roster, profil, program, jadwal, sampling.
    </p>
    <a href="{{ route('tpkkp.data.ekspor', ['tahun' => $a->tahun]) }}"
       class="inline-block rounded-xl bg-cam-ink text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition">
      ↓ Unduh JSON periode {{ $a->tahun }}
    </a>
  </div>

  @if($bisa)
    <div class="bg-white rounded-2xl border border-stone-200 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-1">Impor</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Tempel JSON hasil ekspor. Isi periode {{ $a->tahun }} akan <b>ditimpa</b>.
        Hanya kunci yang dikenali yang dipakai.
      </p>
      <form method="POST" action="{{ route('tpkkp.data.impor', ['tahun' => $a->tahun]) }}"
            onsubmit="return confirm('Timpa seluruh isi penilaian periode {{ $a->tahun }}?')">
        @csrf
        <textarea name="json" rows="7" required placeholder="JSON hasil ekspor — tempel utuh di sini"
                  class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[11.5px] font-mono"></textarea>
        <button class="mt-3 rounded-xl bg-cam-ink text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition">
          Impor & timpa
        </button>
      </form>
    </div>

    <div class="bg-white rounded-2xl border border-red-200 p-5">
      <h3 class="text-[13px] font-bold text-red-700 mb-1">Kosongkan nilai</h3>
      <p class="text-[11.5px] text-stone-500 mb-3">
        Menghapus seluruh nilai penilaian periode {{ $a->tahun }}. Roster, profil, program, dan jadwal tetap.
        Salinan otomatis disimpan ke <span class="font-mono">storage/app/</span> sebelum dihapus.
      </p>
      <form method="POST" action="{{ route('tpkkp.data.reset', ['tahun' => $a->tahun]) }}"
            onsubmit="return confirm('Hapus SEMUA nilai periode {{ $a->tahun }}? Tindakan ini tidak bisa dibatalkan dari layar ini.')">
        @csrf
        <button class="rounded-xl border border-red-300 text-red-700 px-5 py-2.5 text-[12.5px] font-bold hover:bg-red-50 transition">
          Kosongkan nilai periode {{ $a->tahun }}
        </button>
      </form>
    </div>
  @endif
</div>
@endsection
