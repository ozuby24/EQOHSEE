@extends('layouts.app')
@section('title', $d->kode)

@section('content')
@php use App\Support\Dokumen; @endphp
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Kepala --}}
  <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <span class="num text-[12px] font-bold text-stone-500">{{ $d->kode }}</span>
          <span class="text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-0.5 rounded"
                style="background:{{ Dokumen::warna($d->status) }}">{{ $d->status }}</span>
          <span class="text-[9.5px] font-bold uppercase tracking-wide bg-stone-100 text-stone-500 px-2 py-0.5 rounded">{{ $d->jenis }}</span>
          <span class="text-[9.5px] font-bold uppercase tracking-wide bg-cam-lime-soft text-cam-lime-deep px-2 py-0.5 rounded">{{ $d->labelRevisi() }}</span>
        </div>
        <h2 class="text-[17px] font-bold text-cam-ink mt-2 leading-snug">{{ $d->judul }}</h2>
        @if($d->ringkasan)<p class="text-[12.5px] text-stone-500 mt-2 leading-relaxed">{{ $d->ringkasan }}</p>@endif
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        @if($d->berkas)
          <a href="{{ route('dokumen.unduh',$d) }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Unduh</a>
        @endif
        <a href="{{ route('dokumen.edit',$d) }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Ubah</a>
      </div>
    </div>

    @if($d->perluTinjau())
      <div class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-[12.5px] text-amber-800">
        Dokumen ini sudah melewati jatuh tempo peninjauan ({{ $d->tanggal_tinjau->format('d M Y') }}).
      </div>
    @elseif($d->segeraTinjau())
      <div class="mt-4 rounded-xl bg-amber-50/70 border border-amber-100 px-4 py-3 text-[12.5px] text-amber-700">
        Jatuh tempo peninjauan {{ $d->tanggal_tinjau->format('d M Y') }}.
      </div>
    @endif

    <div class="grid gap-4 grid-cols-2 sm:grid-cols-4 mt-5 pt-5 border-t border-stone-100 text-[12px]">
      @foreach ([
        ['Departemen', $d->departemen ?: '—'],
        ['Perusahaan', $d->company?->name ?: 'Semua'],
        ['Klasifikasi', $d->klasifikasi ?: '—'],
        ['Disetujui', $d->disetujui_oleh ?: '—'],
        ['Terbit', $d->tanggal_terbit?->format('d M Y') ?: '—'],
        ['Berlaku', $d->tanggal_berlaku?->format('d M Y') ?: '—'],
        ['Tinjau', $d->tanggal_tinjau?->format('d M Y') ?: '—'],
        ['Acuan', $d->acuan ?: '—'],
      ] as [$l,$v])
        <div>
          <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400">{{ $l }}</div>
          <div class="text-cam-ink mt-1 break-words">{{ $v }}</div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- Terbitkan revisi --}}
  <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-6">
    <h3 class="text-[14px] font-bold text-cam-ink mb-1">Terbitkan Revisi Baru</h3>
    <p class="text-[11.5px] text-stone-400 mb-4">Nomor revisi naik menjadi Rev. {{ str_pad((string)($d->revisi + 1), 2, '0', STR_PAD_LEFT) }} dan status menjadi berlaku. Berkas revisi lama tetap tersimpan di riwayat.</p>

    <form method="POST" action="{{ route('dokumen.revisi',$d) }}" enctype="multipart/form-data" class="space-y-3">
      @csrf
      <div>
        <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Ringkasan perubahan <span class="text-red-500">*</span></label>
        <textarea name="ringkasan_perubahan" rows="2" required
                  class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition"></textarea>
      </div>
      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Tanggal terbit</label>
          <input type="date" name="tanggal" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
        </div>
        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Jatuh tempo tinjau</label>
          <input type="date" name="tanggal_tinjau" class="ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition">
        </div>
        <div>
          <label class="block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Berkas baru</label>
          <input type="file" name="berkas" class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[11.5px] transition file:mr-2 file:rounded file:border-0 file:bg-cam-lime-soft file:px-2 file:py-1 file:text-[11px] file:font-bold file:text-cam-lime-deep">
        </div>
      </div>
      <button class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Terbitkan Revisi</button>
    </form>
  </section>

  {{-- Riwayat revisi --}}
  <section class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-stone-100">
      <h3 class="text-[14px] font-bold text-cam-ink">Riwayat Revisi</h3>
    </div>
    @if($d->revisions->count())
      <div class="divide-y divide-stone-100">
        @foreach($d->revisions as $r)
          <div class="px-5 py-4">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-[10px] font-bold uppercase tracking-wide bg-stone-100 text-stone-600 px-2 py-0.5 rounded">{{ $r->labelRevisi() }}</span>
              @if($r->tanggal)<span class="text-[11px] text-stone-400">{{ $r->tanggal->format('d M Y') }}</span>@endif
              @if($r->oleh)<span class="text-[11px] text-stone-400">· {{ $r->oleh }}</span>@endif
            </div>
            @if($r->ringkasan_perubahan)
              <p class="text-[12.5px] text-cam-ink mt-1.5 leading-relaxed">{{ $r->ringkasan_perubahan }}</p>
            @endif
          </div>
        @endforeach
      </div>
    @else
      <p class="px-5 py-6 text-[12.5px] text-stone-400">Belum ada riwayat revisi.</p>
    @endif
  </section>

  <div class="flex flex-wrap gap-2">
    <a href="{{ route('dokumen.index') }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Register dokumen</a>
    @can('admin')
      <form method="POST" action="{{ route('dokumen.destroy',$d) }}" class="ml-auto"
            onsubmit="return confirm('Hapus dokumen {{ $d->kode }} beserta riwayatnya?')">
        @csrf @method('DELETE')
        <button class="text-[12px] font-semibold text-stone-300 hover:text-red-500 transition">Hapus dokumen</button>
      </form>
    @endcan
  </div>
</div>
@endsection
