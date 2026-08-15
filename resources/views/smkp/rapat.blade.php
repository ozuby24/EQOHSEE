@extends('layouts.app')
@section('title','Rapat Audit SMKP '.$audit->tahun)

@php
  $inp = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-[15px] font-bold text-cam-ink">Rapat Pembukaan &amp; Penutupan</h2>
        <p class="text-[12px] text-stone-400 mt-1 leading-relaxed">
          Daftar hadir kedua rapat menjadi lampiran laporan audit.
        </p>
      </div>
      <a href="{{ route('smkp.show',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline shrink-0">← Ringkasan</a>
    </div>

    @unless($siap)
      <p class="text-[11.5px] text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-3.5 py-2.5 mt-4 leading-relaxed">
        Tahap I belum tuntas. Peserta tetap dapat dicatat, tetapi lengkapi dulu
        <a href="{{ route('smkp.tahap1',$audit) }}" class="font-bold underline">kecukupan dokumentasi</a> dan
        <a href="{{ route('smkp.rencana',$audit) }}" class="font-bold underline">Rencana Audit</a>.
      </p>
    @endunless
  </div>

  @foreach($rapat as $key => $judul)
    @php $daftar = $hadir[$key] ?? collect(); @endphp
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-[13px] font-bold text-cam-ink">{{ $judul }} <span class="text-stone-400 font-semibold">· {{ $daftar->count() }} peserta</span></h3>
        <a href="{{ route('smkp.hadir.cetak',[$audit,$key]) }}"
           class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition shrink-0">Daftar Hadir</a>
      </div>

      @if($daftar->count())
        <div class="tabel-scroll px-5 pt-4">
          <table class="w-full text-[12px] min-w-[480px]">
            <thead>
              <tr class="border-b border-stone-200 text-left text-stone-400">
                <th class="py-2 pr-3 font-semibold w-8 num">No</th>
                <th class="py-2 px-3 font-semibold">Nama</th>
                <th class="py-2 px-3 font-semibold w-44">Jabatan</th>
                <th class="py-2 px-3 font-semibold w-44">Perusahaan</th>
                <th class="py-2 pl-3 w-8"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($daftar as $h)
                <tr>
                  <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-2 px-3 font-semibold text-cam-ink">{{ $h->nama }}</td>
                  <td class="py-2 px-3 text-stone-600">{{ $h->jabatan }}</td>
                  <td class="py-2 px-3 text-stone-600">{{ $h->perusahaan }}</td>
                  <td class="py-2 pl-3 text-center">
                    <form method="POST" action="{{ route('smkp.rapat.hapus',[$audit,$h]) }}" onsubmit="return confirm('Hapus {{ $h->nama }}?')">
                      @csrf @method('DELETE')
                      <button class="text-stone-300 hover:text-red-500 transition text-[15px] leading-none">&times;</button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif

      <form method="POST" action="{{ route('smkp.rapat.simpan',$audit) }}" class="p-5 grid gap-2.5 sm:grid-cols-4">
        @csrf
        <input type="hidden" name="rapat" value="{{ $key }}">
        <input name="nama" placeholder="Nama" required class="{{ $inp }}">
        <input name="jabatan" placeholder="Jabatan" class="{{ $inp }}">
        <input name="perusahaan" placeholder="Perusahaan" class="{{ $inp }}">
        <button class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Tambah</button>
      </form>
    </section>
  @endforeach
</div>
@endsection
