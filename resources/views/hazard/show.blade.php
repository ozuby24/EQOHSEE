@extends('layouts.app')
@section('title', $r->kode)

@section('content')
@php use App\Support\Hazard; @endphp
<div class="max-w-4xl mx-auto space-y-4">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  {{-- Kepala --}}
  <div class="brand-gradient rounded-2xl p-6 text-white shadow-card relative overflow-hidden">
    <div class="absolute -right-20 -top-20 w-56 h-56 rounded-full bg-cam-lime/20 blur-3xl"></div>
    <div class="relative">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-bold glass px-2.5 py-1 rounded num">{{ $r->kode }}</span>
        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full text-white" style="background: {{ Hazard::WARNA_RISIKO[$r->risiko] ?? '#a8a29e' }}">Risiko {{ $r->risiko }}</span>
        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full text-white" style="background: {{ Hazard::WARNA_STATUS[$r->status] ?? '#a8a29e' }}">{{ $r->status }}</span>
      </div>
      <p class="text-[15px] font-semibold mt-3.5 leading-relaxed">{{ $r->deskripsi }}</p>
      <div class="text-[12px] text-white/50 mt-3">
        📍 {{ $r->lokasi ?: '—' }} · {{ optional($r->tanggal)->format('d M Y') }} {{ $r->waktu ? '· '.substr($r->waktu,0,5) : '' }}
      </div>
    </div>
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    {{-- Rincian --}}
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Rincian</h3>
      @foreach ([['Bentuk unsafe action', $r->unsafe_action_list], ['Bentuk unsafe condition', $r->unsafe_condition_list]] as [$judul,$daftar])
        @if(count($daftar))
          <div class="mb-3.5">
            <div class="text-[10px] font-bold uppercase tracking-[0.12em] text-cam-lime-deep mb-1.5">{{ $judul }}</div>
            <div class="flex flex-wrap gap-1.5">
              @foreach($daftar as $b)
                <span class="text-[11px] font-semibold bg-cam-lime-soft text-cam-lime-deep px-2.5 py-1 rounded-lg">{{ $b }}</span>
              @endforeach
            </div>
          </div>
        @endif
      @endforeach

      <div class="space-y-2">
        <div class="flex items-start justify-between gap-4 border-b border-stone-50 pb-2">
          <span class="text-[11.5px] text-stone-400 shrink-0">Perusahaan terlapor</span>
          <span class="text-[12.5px] font-bold text-cam-ink text-right">{{ $r->company?->name ?: ($r->terlapor ?: '— belum diisi —') }}</span>
        </div>
        @foreach ([
          ['Pelapor',      $r->pelapor_nama],
          ['NRP',          $r->pelapor_nrp],
          ['Jabatan',      $r->pelapor_jabatan],
          ['Golongan KPI', $r->golongan()],
          ['Departemen',   $r->pelapor_departemen],
          ['Perusahaan pelapor', $r->pelapor_perusahaan],
          ['Unit/bagian terlapor', $r->terlapor],
          ['Kategori',     $r->kategori],
          ['Hirarki',      $r->hirarki],
          ['Rekomendasi',  $r->rekomendasi],
        ] as [$l,$v])
          @if($v)
            <div class="flex items-start justify-between gap-4 border-b border-stone-50 pb-2 last:border-0">
              <span class="text-[11.5px] text-stone-400 shrink-0">{{ $l }}</span>
              <span class="text-[12.5px] font-medium text-cam-ink text-right">{{ $v }}</span>
            </div>
          @endif
        @endforeach
      </div>
    </div>

    {{-- Foto --}}
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Foto</h3>
      <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400 mb-2">Temuan</div>
      @if($r->foto && count($r->foto))
        <div class="grid grid-cols-3 gap-2">
          @foreach($r->foto as $f)
            <a href="{{ asset('storage/'.$f) }}" target="_blank"><img src="{{ asset('storage/'.$f) }}" class="w-full h-24 object-cover rounded-lg hover:opacity-90 transition"></a>
          @endforeach
        </div>
      @else<p class="text-[12px] text-stone-300">Tidak ada foto.</p>@endif

      @if($r->foto_tindaklanjut && count($r->foto_tindaklanjut))
        <div class="text-[10px] font-bold uppercase tracking-wide text-cam-lime-deep mt-4 mb-2">Tindak lanjut</div>
        <div class="grid grid-cols-3 gap-2">
          @foreach($r->foto_tindaklanjut as $f)
            <a href="{{ asset('storage/'.$f) }}" target="_blank"><img src="{{ asset('storage/'.$f) }}" class="w-full h-24 object-cover rounded-lg hover:opacity-90 transition"></a>
          @endforeach
        </div>
      @endif
    </div>
  </div>

  {{-- Tindak lanjut --}}
  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <h3 class="text-[13px] font-bold text-cam-ink mb-3.5">Tindak Lanjut</h3>
    @if($r->closed_at)
      <div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 px-4 py-3 mb-3.5">
        <div class="text-[12px] font-bold text-cam-lime-deep">Ditutup {{ $r->closed_at->format('d M Y · H:i') }} oleh {{ $r->closer?->name ?: '—' }}</div>
        @if($r->catatan_penutupan)<p class="text-[12.5px] text-stone-600 mt-1 leading-relaxed">{{ $r->catatan_penutupan }}</p>@endif
      </div>
    @endif

    <form action="{{ route('hazard.follow', $r) }}" method="POST" enctype="multipart/form-data" class="space-y-3">
      @csrf
      <div class="grid sm:grid-cols-3 gap-3">
        @foreach(Hazard::STATUS as $s)
          <label class="cursor-pointer">
            <input type="radio" name="status" value="{{ $s }}" class="peer sr-only" @checked($r->status === $s)>
            <span class="block text-center rounded-xl border border-stone-200 bg-white px-3 py-2.5 text-[12.5px] font-bold text-stone-500
                         peer-checked:bg-lime-grad peer-checked:text-white peer-checked:border-transparent hover:border-cam-lime transition">{{ $s }}</span>
          </label>
        @endforeach
      </div>
      <textarea name="catatan_penutupan" rows="3" placeholder="Catatan tindak lanjut / penutupan..."
                class="ring-focus w-full rounded-xl border border-stone-200 px-4 py-2.5 text-[13px] leading-relaxed">{{ $r->catatan_penutupan }}</textarea>
      <div>
        <label class="block text-[11.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Foto tindak lanjut</label>
        <input type="file" name="foto_tindaklanjut[]" accept="image/*" multiple class="text-[12.5px] text-stone-500">
      </div>
      <div class="flex flex-wrap gap-2.5">
        <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Simpan Tindak Lanjut</button>
        <a href="{{ route('hazard.index') }}" class="rounded-xl border border-stone-200 px-5 py-2.5 text-[12.5px] font-bold text-stone-600 hover:bg-stone-50 transition">Kembali</a>
      </div>
    </form>

    {{-- Teruskan ke PIC --}}
    @php
      $pesanWa = "*Hazard Report {$r->kode}*\n\n"
        ."Risiko: {$r->risiko} · Status: {$r->status}\n"
        ."Lokasi: ".($r->lokasi ?: '-')."\n"
        ."Tanggal: ".optional($r->tanggal)->format('d/m/Y')."\n"
        ."Ditujukan kepada: ".($r->company?->name ?: ($r->terlapor ?: '-'))."\n\n"
        ."Temuan:\n{$r->deskripsi}\n"
        .(count($r->unsafe_action_list)    ? "\nUnsafe Action: ".implode(', ', $r->unsafe_action_list) : '')
        .(count($r->unsafe_condition_list) ? "\nUnsafe Condition: ".implode(', ', $r->unsafe_condition_list) : '')
        .($r->rekomendasi ? "\n\nRekomendasi (".($r->hirarki ?: '-')."):\n{$r->rekomendasi}" : '')
        ."\n\nMohon ditindaklanjuti. — EQOHSEE";
      $waPic = \App\Support\Ekspor::waLink($pesanWa, $r->company?->waNumber());
      $mlPic = $r->company?->pic_email
             ? \App\Support\Ekspor::mailLink($r->company->pic_email, "Hazard Report {$r->kode} — perlu tindak lanjut", $pesanWa)
             : null;
    @endphp
    <div class="mt-4 pt-3.5 border-t border-stone-100">
      <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400 mb-2">Teruskan ke PIC</p>
      <div class="flex flex-wrap gap-2">
        <a href="{{ $waPic }}" target="_blank" rel="noopener"
           class="rounded-lg bg-[#25D366] text-white px-3.5 py-2 text-[12px] font-bold hover:brightness-105 transition">
          WhatsApp{{ $r->company?->waNumber() ? ' PIC' : ' (pilih grup)' }}
        </a>
        @if($mlPic)
          <a href="{{ $mlPic }}" class="rounded-lg border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">Email PIC</a>
        @endif
        <a href="{{ route('hazard.ekspor.cetak', ['q' => $r->kode]) }}" target="_blank" rel="noopener"
           class="rounded-lg border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">⎙ Cetak</a>
      </div>
    </div>

    {{-- Form hapus DI LUAR form tindak lanjut (form bersarang tidak sah di HTML) --}}
    @can('admin')
      <form action="{{ route('hazard.destroy', $r) }}" method="POST" onsubmit="return confirm('Hapus laporan ini? Tindakan ini tidak dapat dibatalkan.')" class="mt-3 pt-3 border-t border-stone-100 text-right">
        @csrf @method('DELETE')
        <button class="rounded-xl px-4 py-2 text-[12px] font-bold text-red-500 hover:bg-red-50 transition">Hapus laporan</button>
      </form>
    @endcan
  </div>
</div>
@endsection
