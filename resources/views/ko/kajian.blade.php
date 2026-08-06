@extends('layouts.app')
@section('title','KO/SPIP — Kajian Teknis')

@section('content')
@php $K = \App\Support\Ko::class; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  @if($bolehUbah)
    <form method="POST" action="{{ route('ko.kajian.simpan') }}" class="bg-white rounded-2xl border border-stone-200 p-5 space-y-3">
      @csrf
      <h3 class="text-[13px] font-bold text-cam-ink">Tambah kajian teknis</h3>
      <div class="grid sm:grid-cols-3 gap-2">
        <select name="ko_object_id" required class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">— pilih objek —</option>
          @foreach($objek as $o)<option value="{{ $o->id }}">{{ $o->kode }} · {{ $o->nama }}</option>@endforeach
        </select>
        <input name="judul" required placeholder="Judul kajian" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] sm:col-span-2">
        <input name="pemicu" placeholder="Pemicu (Awal operasi, Modifikasi, Insiden…)" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <input type="date" name="tanggal" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <select name="ko_personnel_id" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">— pengkaji —</option>
          @foreach($tenaga as $t)<option value="{{ $t->id }}">{{ $t->nama }}</option>@endforeach
        </select>
        <select name="status" required class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
          @foreach($K::KAJIAN_STATUS as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
        </select>
        <input type="date" name="tgl_lapor" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
      </div>
      <textarea name="ringkasan" rows="2" placeholder="Ringkasan hasil kajian" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]"></textarea>
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2 text-[12.5px] font-bold hover:brightness-105 transition">Simpan kajian</button>
    </form>
  @endif

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
      <h3 class="text-[13px] font-bold text-cam-ink">Kajian Teknis</h3>
      <span class="text-[11px] text-stone-400 num">{{ $kajian->where('status','Dilaporkan')->count() }}/{{ $kajian->count() }} dilaporkan ke KaIT</span>
    </div>
    <div class="divide-y divide-stone-50">
      @forelse($kajian as $k)
        <div class="px-5 py-3.5 flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-[12.5px] font-semibold text-cam-ink">{{ $k->judul }}</div>
            <div class="text-[11px] text-stone-500 mt-0.5">
              <a href="{{ route('ko.show', $k->object) }}" class="num font-bold text-cam-lime-deep hover:underline">{{ $k->object?->kode }}</a>
              · {{ $k->pemicu ?: '—' }}
              · {{ $k->tanggal?->format('d M Y') ?: '—' }}
              · {{ $k->personnel?->nama ?: $k->oleh ?: 'pengkaji belum ditetapkan' }}
            </div>
            @if($k->ringkasan)<p class="text-[11.5px] text-stone-600 mt-1.5">{{ $k->ringkasan }}</p>@endif
          </div>
          <div class="flex items-center gap-2">
            <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-full {{ $k->status === 'Dilaporkan' ? 'bg-emerald-50 text-emerald-700' : ($k->status === 'Selesai' ? 'bg-sky-50 text-sky-700' : 'bg-amber-50 text-amber-700') }}">{{ $k->status }}</span>
            @if(auth()->user()->isAdmin())
              <form method="POST" action="{{ route('ko.kajian.hapus', $k) }}" onsubmit="return confirm('Hapus kajian ini?')">
                @csrf @method('DELETE')
                <button class="text-[11px] text-stone-400 hover:text-red-600">hapus</button>
              </form>
            @endif
          </div>
        </div>
      @empty
        <p class="px-5 py-10 text-center text-[12.5px] text-stone-400">Belum ada kajian teknis.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
