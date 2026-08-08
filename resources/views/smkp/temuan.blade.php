@extends('layouts.app')
@section('title','Temuan Audit SMKP '.$audit->tahun)

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-[15px] font-bold text-cam-ink">Temuan &amp; Tindakan Perbaikan</h2>
        <p class="text-[12px] text-stone-400 mt-1">{{ $audit->judul ?: 'Audit SMKP '.$audit->tahun }}</p>
      </div>
      <a href="{{ route('smkp.show',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline shrink-0">← Ringkasan</a>
    </div>
  </div>

  {{-- Ketidaksesuaian dari formulir yang belum diangkat --}}
  @if(count($usulan))
    <section class="bg-amber-50 rounded-2xl border border-amber-200 p-5">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 class="text-[13px] font-bold text-amber-800">{{ count($usulan) }} ketidaksesuaian belum jadi tindakan perbaikan</h3>
          <p class="text-[11.5px] text-amber-700/80 mt-1 leading-relaxed">
            Ditemukan pada formulir penilaian tetapi belum punya rencana tindakan.
          </p>
        </div>
        <form method="POST" action="{{ route('smkp.temuan.angkat',$audit) }}" class="shrink-0">
          @csrf
          <button class="rounded-xl bg-amber-600 text-white px-4 py-2.5 text-[12.5px] font-bold hover:bg-amber-700 transition">Angkat Semua</button>
        </form>
      </div>
      <ul class="mt-3 space-y-1">
        @foreach(array_slice($usulan,0,5) as $u)
          <li class="text-[11.5px] text-amber-800/90">
            <span class="num font-bold">{{ $u['kode'] }}</span> — {{ $u['label'] }}: {{ \Illuminate\Support\Str::limit($u['uraian'],90) }}
          </li>
        @endforeach
        @if(count($usulan) > 5)<li class="text-[11.5px] text-amber-700/70">…dan {{ count($usulan)-5 }} lainnya.</li>@endif
      </ul>
    </section>
  @endif

  {{-- Daftar tindakan perbaikan --}}
  <div class="space-y-3">
    @forelse($temuan as $t)
      @php
        $warna = $t->jenis === 'mayor' ? '#E5484D' : ($t->jenis === 'minor' ? '#F0921E' : '#9AA3AE');
      @endphp
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-stone-100 flex flex-wrap items-center gap-2">
          <span class="num text-[11px] font-bold text-stone-400">{{ $t->kode_kriteria }}</span>
          <span class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded" style="background:{{ $warna }}">{{ $t->jenis }}</span>
          <span class="text-[9.5px] font-bold uppercase tracking-wide px-2 py-0.5 rounded
                {{ $t->status === 'Closed' ? 'bg-cam-lime-soft text-cam-lime-deep' : ($t->status === 'In Progress' ? 'bg-amber-100 text-amber-700' : 'bg-red-50 text-red-600') }}">{{ $t->status }}</span>
          @if($t->terlambat())
            <span class="text-[9.5px] font-bold uppercase tracking-wide bg-red-100 text-red-700 px-2 py-0.5 rounded">Lewat target</span>
          @endif
          <form method="POST" action="{{ route('smkp.temuan.hapus',[$audit,$t]) }}" class="ml-auto"
                onsubmit="return confirm('Hapus temuan {{ $t->kode_kriteria }}?')">
            @csrf @method('DELETE')
            <button class="text-[11px] font-semibold text-stone-300 hover:text-red-500 transition">Hapus</button>
          </form>
        </div>

        <form method="POST" action="{{ route('smkp.temuan.simpan',[$audit,$t]) }}" class="p-5 space-y-3">
          @csrf @method('PUT')

          <p class="text-[13px] text-cam-ink leading-relaxed">{{ $t->uraian }}</p>

          <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Akar masalah</label>
            <textarea name="akar_masalah" rows="2"
                      class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">{{ old('akar_masalah',$t->akar_masalah) }}</textarea>
          </div>

          <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tindakan perbaikan</label>
            <textarea name="tindakan" rows="2"
                      class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">{{ old('tindakan',$t->tindakan) }}</textarea>
          </div>

          <div class="grid gap-2 sm:grid-cols-3">
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Penanggung jawab</label>
              <input name="penanggung_jawab" value="{{ old('penanggung_jawab',$t->penanggung_jawab) }}"
                     class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
            </div>
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Target selesai</label>
              <input type="date" name="target_selesai" value="{{ old('target_selesai',$t->target_selesai?->format('Y-m-d')) }}"
                     class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
            </div>
            <div>
              <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Status</label>
              <select name="status" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
                @foreach(['Open','In Progress','Closed'] as $s)
                  <option value="{{ $s }}" @selected(old('status',$t->status)===$s)>{{ $s }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div>
            <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Verifikasi keefektifan</label>
            <textarea name="verifikasi" rows="2"
                      class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">{{ old('verifikasi',$t->verifikasi) }}</textarea>
          </div>

          <button class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
        </form>
      </section>
    @empty
      <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-10 text-center">
        <p class="text-[13px] text-stone-400">Belum ada temuan yang diangkat menjadi tindakan perbaikan.</p>
      </div>
    @endforelse
  </div>
</div>
@endsection
