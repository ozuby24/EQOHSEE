@extends('layouts.app')
@section('title', $template->exists ? 'Kelola: '.$template->nama : 'Jenis Inspeksi Baru')

@section('content')
@php use App\Support\Hazard; @endphp
<div class="max-w-3xl mx-auto space-y-4">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif
  @if($errors->any())
    <div class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12.5px]">
      @foreach($errors->all() as $e)<div>• {{ $e }}</div>@endforeach
    </div>
  @endif

  {{-- Identitas jenis --}}
  <form action="{{ $template->exists ? route('inspeksi.template.update',$template) : route('inspeksi.template.store') }}"
        method="POST" class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
    @csrf
    @if($template->exists) @method('PUT') @endif
    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-stone-400">Identitas Jenis Inspeksi</p>

    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Nama jenis inspeksi</label>
      <input name="nama" value="{{ old('nama',$template->nama) }}" required placeholder="Inspeksi APAR, Inspeksi Unit, Inspeksi Housekeeping..."
             class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
    </div>
    <div class="grid sm:grid-cols-2 gap-3">
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Frekuensi</label>
        <select name="jenis" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
          <option value="">— pilih —</option>
          @foreach(Hazard::JENIS_INSPEKSI as $j)<option value="{{ $j }}" @selected(old('jenis',$template->jenis)===$j)>{{ $j }}</option>@endforeach
        </select>
      </div>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Kategori</label>
        <input name="kategori" value="{{ old('kategori',$template->kategori) }}" placeholder="Peralatan, Lingkungan, Kendaraan..."
               class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">
      </div>
    </div>
    <div>
      <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Deskripsi</label>
      <textarea name="deskripsi" rows="2" class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13.5px]">{{ old('deskripsi',$template->deskripsi) }}</textarea>
    </div>
    <label class="flex items-center gap-2.5 text-[13px] text-stone-600 cursor-pointer">
      <input type="hidden" name="is_active" value="0">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->exists ? $template->is_active : true))
             class="rounded border-stone-300 text-cam-lime-dark focus:ring-cam-lime/40">
      <span class="font-semibold">Aktif (bisa dipilih saat membuat inspeksi)</span>
    </label>

    <div class="flex gap-2.5 pt-1">
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
      <a href="{{ route('inspeksi.template.index') }}" class="text-[12.5px] font-semibold text-stone-400 hover:text-cam-ink self-center">Kembali</a>
    </div>
  </form>

  @if($template->exists)
    {{-- Parameter --}}
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <div class="flex items-center justify-between mb-3.5">
        <h3 class="text-[14px] font-bold text-cam-ink">Parameter Pemeriksaan</h3>
        <span class="text-[11.5px] text-stone-400"><span class="num font-semibold">{{ $template->items->count() }}</span> parameter</span>
      </div>

      @php $grup = $template->items->groupBy(fn($i) => $i->kelompok ?: 'Umum'); @endphp
      <div class="space-y-4">
        @forelse($grup as $namaGrup => $items)
          <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-cam-lime-deep mb-1.5">{{ $namaGrup }}</p>
            <div class="space-y-1.5">
              @foreach($items as $it)
                <div class="flex items-start justify-between gap-3 bg-stone-50 rounded-lg px-3.5 py-2.5">
                  <div class="min-w-0">
                    <div class="text-[12.5px] text-stone-700">{{ $it->order_index }}. {{ $it->uraian }}</div>
                    <div class="flex gap-2 mt-1">
                      @if($it->acuan)<span class="text-[10px] text-stone-400">Acuan: {{ $it->acuan }}</span>@endif
                      @if($it->risiko_default)<span class="text-[10px] font-bold text-white px-1.5 py-0.5 rounded" style="background: {{ Hazard::WARNA_RISIKO[$it->risiko_default] }}">{{ $it->risiko_default }}</span>@endif
                    </div>
                  </div>
                  <form action="{{ route('inspeksi.template.item.destroy', $it) }}" method="POST">
                    @csrf @method('DELETE')
                    <button class="text-[12px] text-red-400 hover:text-red-600">✕</button>
                  </form>
                </div>
              @endforeach
            </div>
          </div>
        @empty
          <p class="text-[12.5px] text-stone-400 text-center py-5">Belum ada parameter.</p>
        @endforelse
      </div>

      {{-- Tambah parameter --}}
      <form action="{{ route('inspeksi.template.item.store', $template) }}" method="POST" class="grid sm:grid-cols-6 gap-2 mt-4 pt-4 border-t border-stone-100">
        @csrf
        <input name="kelompok" placeholder="Kelompok" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="uraian" placeholder="Parameter yang diperiksa" required class="ring-focus sm:col-span-3 rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
        <input name="acuan" placeholder="Acuan/standar" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
        <select name="risiko_default" class="ring-focus rounded-lg border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">Risiko —</option>
          @foreach(Hazard::RISIKO as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
        </select>
        <button class="sm:col-span-6 lime-gradient rounded-lg text-white py-2 text-[12px] font-bold hover:brightness-105">+ Tambah Parameter</button>
      </form>
    </div>
  @endif
</div>
@endsection
