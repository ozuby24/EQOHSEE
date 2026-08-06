@extends('layouts.app')
@section('title','KO/SPIP — Tindak Lanjut')

@section('content')
@php $K = \App\Support\Ko::class; @endphp
<div class="max-w-6xl mx-auto space-y-5">
  @include('ko._flash')
  @include('ko._picker')

  <div class="bg-white rounded-2xl border border-stone-200 p-4 flex flex-wrap items-center gap-2">
    <form method="GET" class="flex items-center gap-2">
      @if(request('perusahaan'))<input type="hidden" name="perusahaan" value="{{ request('perusahaan') }}">@endif
      <select name="st" onchange="this.form.submit()" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
        <option value="">Semua status</option>
        @foreach($K::AKSI_STATUS as $v)<option value="{{ $v }}" @selected($st === $v)>{{ $v }}</option>@endforeach
      </select>
    </form>
    @if($bolehUbah)
      <form method="POST" action="{{ route('ko.tindak.tarik') }}" class="ml-auto">
        @csrf
        <button class="rounded-xl bg-cam-ink text-white px-4 py-2 text-[12.5px] font-bold hover:brightness-110 transition">↧ Tarik dari peringatan</button>
      </form>
    @endif
  </div>

  @if($bolehUbah)
    <form method="POST" action="{{ route('ko.tindak.simpan') }}" class="bg-white rounded-2xl border border-stone-200 p-5 space-y-3">
      @csrf
      <h3 class="text-[13px] font-bold text-cam-ink">Tambah tindak lanjut manual</h3>
      <div class="grid sm:grid-cols-3 gap-2">
        <select name="ko_object_id" required class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">— pilih objek —</option>
          @foreach($objek as $o)<option value="{{ $o->id }}">{{ $o->kode }} · {{ $o->nama }}</option>@endforeach
        </select>
        <input type="hidden" name="sumber" value="Manual">
        <select name="prioritas" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
          @foreach(['Tinggi','Sedang','Rendah'] as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
        </select>
        <input type="date" name="target_tgl" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
        <select name="pic_user_id" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]">
          <option value="">— PIC —</option>
          @foreach($user as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
        </select>
        <select name="status" class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] font-semibold">
          @foreach($K::AKSI_STATUS as $v)<option value="{{ $v }}">{{ $v }}</option>@endforeach
        </select>
      </div>
      <textarea name="uraian" rows="2" required placeholder="Uraian tindak lanjut" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px]"></textarea>
      <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2 text-[12.5px] font-bold hover:brightness-105 transition">Simpan</button>
    </form>
  @endif

  <div class="bg-white rounded-2xl border border-stone-200 overflow-hidden">
    <div class="px-5 py-3 border-b border-stone-100 flex items-center justify-between">
      <h3 class="text-[13px] font-bold text-cam-ink">Daftar Tindak Lanjut</h3>
      <span class="text-[11px] text-stone-400 num">{{ $aksi->whereIn('status',['Terbuka','Berjalan'])->count() }} terbuka · {{ $aksi->count() }} total</span>
    </div>
    <div class="divide-y divide-stone-50">
      @forelse($aksi as $a)
        <div class="px-5 py-3.5">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="text-[10.5px] font-bold px-2 py-0.5 rounded-md
                      {{ $a->prioritas === 'Tinggi' ? 'bg-red-50 text-red-700' : ($a->prioritas === 'Sedang' ? 'bg-amber-50 text-amber-700' : 'bg-stone-100 text-stone-500') }}">{{ $a->prioritas }}</span>
                <span class="text-[10.5px] px-2 py-0.5 rounded-md bg-stone-100 text-stone-600 font-semibold">{{ $a->sumber }}</span>
                <a href="{{ route('ko.show', $a->object) }}" class="num text-[11px] font-bold text-cam-lime-deep hover:underline">{{ $a->object?->kode }}</a>
              </div>
              <div class="text-[12.5px] text-cam-ink mt-1">{{ $a->uraian }}</div>
              <div class="text-[11px] text-stone-500 mt-0.5">
                target {{ $a->target_tgl?->format('d M Y') ?: '—' }} ·
                {{ $a->pic?->name ?: $a->pic_nama ?: 'PIC belum ditetapkan' }}
                @if($a->tgl_selesai) · selesai {{ $a->tgl_selesai->format('d M Y') }} @endif
              </div>
            </div>

            <div class="flex items-center gap-2">
              @if($bolehUbah)
                <form method="POST" action="{{ route('ko.tindak.simpan') }}" class="flex items-center gap-1.5">
                  @csrf
                  <input type="hidden" name="id" value="{{ $a->id }}">
                  <input type="hidden" name="ko_object_id" value="{{ $a->ko_object_id }}">
                  <input type="hidden" name="sumber" value="{{ $a->sumber }}">
                  <input type="hidden" name="uraian" value="{{ $a->uraian }}">
                  <input type="hidden" name="prioritas" value="{{ $a->prioritas }}">
                  <input type="hidden" name="target_tgl" value="{{ $a->target_tgl?->format('Y-m-d') }}">
                  <select name="status" class="ring-focus rounded-lg border border-stone-200 px-2 py-1 text-[11.5px] font-semibold">
                    @foreach($K::AKSI_STATUS as $v)<option value="{{ $v }}" @selected($a->status === $v)>{{ $v }}</option>@endforeach
                  </select>
                  <button class="rounded-lg bg-cam-ink text-white px-2.5 py-1 text-[11.5px] font-bold">Simpan</button>
                </form>
              @else
                <span class="text-[11.5px] font-semibold text-stone-500">{{ $a->status }}</span>
              @endif
              @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('ko.tindak.hapus', $a) }}" onsubmit="return confirm('Hapus tindak lanjut ini?')">
                  @csrf @method('DELETE')
                  <button class="text-[11px] text-stone-400 hover:text-red-600">hapus</button>
                </form>
              @endif
            </div>
          </div>
          @if($a->terlambat)
            <div class="mt-2 text-[11px] font-bold text-red-600">Melewati target penyelesaian.</div>
          @endif
        </div>
      @empty
        <p class="px-5 py-10 text-center text-[12.5px] text-stone-400">Belum ada tindak lanjut.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
