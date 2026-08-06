@extends('layouts.app')
@section('title','Pengingat Tindak Lanjut')

@section('content')
@php use App\Support\{Ekspor, Hazard}; @endphp
<div class="max-w-5xl mx-auto space-y-5">

  <div class="glass-light rounded-xl border border-stone-200/60 px-4 py-3">
    <p class="text-[12px] text-stone-500 leading-relaxed">
      Kirim pengingat temuan yang <b>belum ditutup</b> kepada PIC tiap perusahaan lewat
      <b>WhatsApp</b> atau <b>email</b>. Isi pesan disusun otomatis berisi daftar temuan,
      tingkat risiko, dan lokasinya. Atur kontak PIC di
      <a href="{{ route('admin.companies.index') }}" class="font-bold text-cam-lime-deep hover:underline">Kelola Perusahaan</a>.
    </p>
  </div>

  @forelse($perusahaan as $row)
    @php
      $c = $row['c'];
      $daftar = $row['terbuka'];
      $judul  = 'Pengingat Tindak Lanjut Temuan — '.$c->name;
      $baris  = $daftar->take(20)->map(fn($h,$i) =>
          ($i+1).'. ['.$h->kode.'] '.$h->risiko.' — '.\Illuminate\Support\Str::limit($h->deskripsi, 70)
          .' (📍'.($h->lokasi ?: '-').', '.optional($h->tanggal)->format('d/m/Y').', status '.$h->status.')')->implode("\n");
      $pesan = "*{$judul}*\n\n"
             ."Kepada Yth. ".($c->pic_name ?: 'PIC '.$c->name).",\n\n"
             ."Terdapat *{$daftar->count()} temuan* yang belum ditutup"
             .($row['tinggi'] ? ", termasuk *{$row['tinggi']} berisiko tinggi*" : '')
             .($row['lama'] ? ", dan *{$row['lama']} sudah lebih dari 14 hari*" : '')
             .".\n\n{$baris}\n\n"
             ."Mohon segera ditindaklanjuti dan diperbarui statusnya pada sistem EQOHSEE.\n\n"
             ."— Tim HSE EQOHSEE";
      $wa   = Ekspor::waLink($pesan, $c->waNumber());
      $mail = $c->pic_email ? Ekspor::mailLink($c->pic_email, $judul, $pesan) : null;
    @endphp

    <div class="bg-white rounded-2xl shadow-card border {{ $row['tinggi'] ? 'border-red-200' : 'border-stone-100' }} p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
          <h3 class="text-[15px] font-bold text-cam-ink">{{ $c->name }}</h3>
          <div class="text-[11.5px] text-stone-400 mt-1">
            PIC: {{ $c->pic_name ?: '— belum diisi —' }}
            @if($c->pic_email) · ✉ {{ $c->pic_email }} @endif
            @if($c->pic_phone) · ☎ {{ $c->pic_phone }} @endif
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <span class="text-[11px] font-bold bg-stone-100 text-stone-600 px-2.5 py-1 rounded-full num">{{ $daftar->count() }} belum tutup</span>
            @if($row['tinggi'])<span class="text-[11px] font-bold bg-red-100 text-red-700 px-2.5 py-1 rounded-full num">{{ $row['tinggi'] }} risiko tinggi</span>@endif
            @if($row['lama'])<span class="text-[11px] font-bold bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full num">{{ $row['lama'] }} lewat 14 hari</span>@endif
          </div>
        </div>

        <div class="flex flex-wrap gap-2 shrink-0">
          <a href="{{ $wa }}" target="_blank" rel="noopener"
             class="rounded-xl bg-[#25D366] text-white px-4 py-2.5 text-[12px] font-bold hover:brightness-105 transition">
            WhatsApp{{ $c->waNumber() ? '' : ' (pilih grup)' }}
          </a>
          @if($mail)
            <a href="{{ $mail }}" class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">Email PIC</a>
          @else
            <a href="{{ route('admin.companies.edit', $c) }}" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-[12px] font-bold text-amber-700 hover:bg-amber-100 transition">Isi email PIC</a>
          @endif
          <a href="{{ route('hazard.index', ['perusahaan' => $c->id, 'status' => 'Open']) }}"
             class="rounded-xl border border-stone-200 px-4 py-2.5 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">Lihat</a>
        </div>
      </div>

      <details class="mt-4 pt-3.5 border-t border-stone-100">
        <summary class="text-[12px] font-bold text-cam-lime-deep cursor-pointer hover:underline">Lihat isi pesan pengingat</summary>
        <pre class="mt-2.5 bg-stone-50 rounded-xl p-3.5 text-[11.5px] text-stone-600 whitespace-pre-wrap leading-relaxed">{{ $pesan }}</pre>
      </details>
    </div>
  @empty
    <div class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center">
      <div class="text-[36px]">✓</div>
      <p class="text-[14px] font-bold text-cam-ink mt-2">Semua temuan sudah ditutup</p>
      <p class="text-[12.5px] text-stone-400 mt-1">Tidak ada pengingat yang perlu dikirim.</p>
    </div>
  @endforelse
</div>
@endsection
