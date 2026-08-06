@extends('layouts.app')
@section('title','Profil & Strata')

@section('content')
<div class="max-w-3xl mx-auto space-y-5">
  @if(session('ok'))
    <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>
  @endif

  <form action="{{ route('tpkkp.profile.save') }}" method="POST" class="space-y-5">
    @csrf

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6 space-y-4">
      <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Profil Perusahaan</p>
      <div class="grid sm:grid-cols-2 gap-4">
        @foreach ([
          ['nama','Nama perusahaan'],['jenis','Jenis izin'],
          ['site','Site / lokasi'],['komoditas','Komoditas'],
          ['ktt','KTT'],['ketua','Ketua / POP'],['tahun','Tahun penilaian'],
        ] as [$f,$l])
          <div @class(['sm:col-span-2' => $f === 'nama'])>
            <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">{{ $l }}</label>
            <input name="profil[{{ $f }}]" value="{{ $profil[$f] ?? ($f === 'nama' ? $company->name : '') }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px] transition">
          </div>
        @endforeach
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
      <div class="flex items-center justify-between mb-4">
        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Strata Tenaga Kerja</p>
        <button type="button" onclick="tambahStrata()" class="text-[11.5px] font-bold text-cam-lime-deep hover:underline">+ Tambah baris</button>
      </div>

      <div id="strataBox" class="space-y-2">
        @forelse($strata as $s)
          <div class="grid grid-cols-[1fr_110px_auto] gap-2 items-center">
            <input name="strata_nama[]" value="{{ $s['nama'] ?? '' }}" placeholder="Nama strata / jabatan"
                   class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
            <input type="number" name="strata_jumlah[]" value="{{ $s['jumlah'] ?? 0 }}" min="0" placeholder="Jumlah"
                   class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] text-center">
            <button type="button" onclick="this.parentElement.remove()" class="text-[13px] text-red-400 hover:text-red-600 px-2">✕</button>
          </div>
        @empty
          <div class="grid grid-cols-[1fr_110px_auto] gap-2 items-center">
            <input name="strata_nama[]" placeholder="Nama strata / jabatan" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
            <input type="number" name="strata_jumlah[]" value="0" min="0" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] text-center">
            <button type="button" onclick="this.parentElement.remove()" class="text-[13px] text-red-400 hover:text-red-600 px-2">✕</button>
          </div>
        @endforelse
      </div>
      <p class="text-[11px] text-stone-400 mt-3">Baris kosong akan diabaikan saat disimpan.</p>
    </div>

    <button class="lime-gradient shadow-glow w-full rounded-xl text-white py-3 text-[13px] font-bold hover:brightness-105 transition">Simpan Profil &amp; Strata</button>
  </form>
</div>

@push('scripts')
<script>
function tambahStrata(){
  const box = document.getElementById('strataBox');
  const row = document.createElement('div');
  row.className = 'grid grid-cols-[1fr_110px_auto] gap-2 items-center';
  row.innerHTML = `
    <input name="strata_nama[]" placeholder="Nama strata / jabatan" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
    <input type="number" name="strata_jumlah[]" value="0" min="0" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] text-center">
    <button type="button" onclick="this.parentElement.remove()" class="text-[13px] text-red-400 hover:text-red-600 px-2">✕</button>`;
  box.appendChild(row);
}
</script>
@endpush
@endsection
