@extends('layouts.app')
@section('title','Laporan Rencana Audit SMKP '.$audit->tahun)

@php
  $r  = (array) ($audit->rencana ?? []);
  $rk = (array) ($audit->risiko ?? []);
  $tgl = function ($v) { return $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d F Y') : null; };
  $isi = fn ($k) => trim((string) ($r[$k] ?? '')) !== '' ? $r[$k] : null;
  $h2  = 'text-[13px] font-bold text-cam-ink mb-2';
  $sec = 'break-inside-avoid';
  $kosong = '<span class="text-stone-300 italic">belum ditetapkan</span>';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <div class="print:hidden flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('smkp.rencana',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Ubah Rencana Audit</a>
    <button onclick="window.print()"
            class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Cetak</button>
  </div>

  @unless($rekap['lengkap'])
    <div class="print:hidden rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
      <p class="text-[12px] font-bold text-amber-800">Rencana Audit belum lengkap</p>
      <p class="text-[11.5px] text-amber-700/90 mt-1 leading-relaxed">
        Belum terisi: {{ implode(', ', $rekap['kurang']) }}. Laporan tetap dapat dicetak,
        tetapi bagian tersebut akan kosong.
      </p>
    </div>
  @endunless

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-7 space-y-6">

    {{-- Kepala --}}
    <header class="text-center border-b border-stone-100 pb-5">
      <h1 class="font-display text-[21px] font-black text-cam-ink leading-tight">Rencana Audit Internal</h1>
      <h2 class="font-display text-[15px] font-bold text-cam-ink mt-0.5">Sistem Manajemen Keselamatan Pertambangan</h2>
      <p class="text-[11.5px] text-stone-400 mt-2">Kepdirjen Minerba Nomor 185.K/37.04/DJB/2019 — Lampiran II</p>
      <p class="text-[13.5px] font-bold text-cam-ink mt-3">{{ $audit->company?->name ?? 'Seluruh Perusahaan' }}</p>
      <p class="text-[12px] text-stone-500">Periode Audit {{ $audit->tahun }}</p>
      @if($isi('nomor'))
        <p class="text-[11.5px] text-stone-500 mt-1">Nomor Formulir: <span class="num font-semibold">{{ $r['nomor'] }}</span></p>
      @endif
    </header>

    {{-- 1 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">1. {{ $komponen['tujuan']['judul'] }}</h3>
      <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('tujuan') ? e($r['tujuan']) : $kosong !!}</p>
    </section>

    {{-- 2 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">2. {{ $komponen['kriteria']['judul'] }}</h3>
      <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('kriteria') ? e($r['kriteria']) : $kosong !!}</p>
    </section>

    {{-- 3 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">3. {{ $komponen['ruang_lingkup']['judul'] }}</h3>
      <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('ruang_lingkup') ? e($r['ruang_lingkup']) : $kosong !!}</p>
    </section>

    {{-- 4 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">4. {{ $komponen['tanggal']['judul'] }}</h3>
      @if($tgl($r['tanggal_mulai'] ?? null) && $tgl($r['tanggal_selesai'] ?? null))
        <p class="text-[12.5px] text-stone-600">{{ $tgl($r['tanggal_mulai']) }} &ndash; {{ $tgl($r['tanggal_selesai']) }}</p>
      @else
        <p class="text-[12.5px]">{!! $kosong !!}</p>
      @endif
      <p class="text-[11.5px] text-stone-400 mt-1.5">
        Alokasi hari kerja audit Tahap II: <span class="num font-semibold">{{ number_format($mandays['tahap2'],2) }}</span> hari
        (dari total <span class="num font-semibold">{{ number_format($mandays['total'],2) }}</span> hari).
      </p>
    </section>

    {{-- 5 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">5. {{ $komponen['susunan']['judul'] }}</h3>
      @if(!empty($r['susunan']))
        <div class="tabel-scroll">
          <table class="w-full text-[12px] min-w-[620px]">
            <thead>
              <tr class="border-b border-stone-200 text-left text-stone-400">
                <th class="py-2 pr-3 font-semibold w-8 num">No</th>
                <th class="py-2 px-3 font-semibold w-32">Tanggal</th>
                <th class="py-2 px-3 font-semibold w-28">Waktu</th>
                <th class="py-2 px-3 font-semibold">Kegiatan</th>
                <th class="py-2 px-3 font-semibold">Auditi</th>
                <th class="py-2 pl-3 font-semibold">Auditor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($r['susunan'] as $b)
                <tr>
                  <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-2 px-3 text-stone-600">{{ $tgl($b['tanggal'] ?? null) ?? '—' }}</td>
                  <td class="py-2 px-3 text-stone-600 num">{{ $b['waktu'] ?? '' }}</td>
                  <td class="py-2 px-3 text-cam-ink">{{ $b['kegiatan'] ?? '' }}</td>
                  <td class="py-2 px-3 text-stone-600">{{ $b['auditi'] ?? '' }}</td>
                  <td class="py-2 pl-3 text-stone-600">{{ $b['auditor'] ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-[12.5px]">{!! $kosong !!}</p>
      @endif
      <p class="text-[11.5px] text-stone-400 mt-2 leading-relaxed">
        Rencana Audit Tahap II meliputi: {{ implode('; ', $kegiatan) }}.
      </p>
    </section>

    {{-- 6 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">6. {{ $komponen['tugas']['judul'] }}</h3>
      @if(!empty($r['tugas']))
        <div class="tabel-scroll">
          <table class="w-full text-[12px] min-w-[600px]">
            <thead>
              <tr class="border-b border-stone-200 text-left text-stone-400">
                <th class="py-2 pr-3 font-semibold w-8 num">No</th>
                <th class="py-2 px-3 font-semibold">Nama</th>
                <th class="py-2 px-3 font-semibold w-40">Peran</th>
                <th class="py-2 px-3 font-semibold w-56">Nomor Registrasi Auditor</th>
                <th class="py-2 pl-3 font-semibold">Lingkup</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($r['tugas'] as $b)
                <tr>
                  <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-2 px-3 font-semibold text-cam-ink">{{ $b['nama'] ?? '' }}</td>
                  <td class="py-2 px-3 text-stone-600">{{ $b['peran'] ?? '' }}</td>
                  <td class="py-2 px-3 num text-stone-600">{{ $b['registrasi'] ?? '' }}</td>
                  <td class="py-2 pl-3 text-stone-600">{{ $b['lingkup'] ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-[12.5px]">{!! $kosong !!}</p>
      @endif
    </section>

    {{-- 7 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">7. {{ $komponen['sumberdaya']['judul'] }}</h3>
      <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('sumberdaya') ? e($r['sumberdaya']) : $kosong !!}</p>
    </section>

    {{-- 8 --}}
    <section class="{{ $sec }}">
      <h3 class="{{ $h2 }}">8. {{ $komponen['metode']['judul'] }}</h3>
      <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('metode') ? e($r['metode']) : $kosong !!}</p>

      @if($isi('sampel'))
        <p class="text-[11px] font-bold uppercase tracking-wide text-stone-500 mt-3 mb-1">Dasar Pengambilan Sampel</p>
        <p class="text-[12.5px] text-stone-600 leading-relaxed whitespace-pre-line">{{ $r['sampel'] }}</p>
      @endif

      @foreach ([['present','Data Top Risks — Risk of Present'],['future','Data Top Risks — Risk of Future']] as [$slot,$judul])
        @if(!empty($rk[$slot]))
          <p class="text-[11px] font-bold uppercase tracking-wide text-stone-500 mt-4 mb-1.5">{{ $judul }}</p>
          <div class="tabel-scroll">
            <table class="w-full text-[12px] min-w-[480px]">
              <thead>
                <tr class="border-b border-stone-200 text-left text-stone-400">
                  <th class="py-2 pr-3 font-semibold w-8 num">No</th>
                  <th class="py-2 px-3 font-semibold">Kegiatan</th>
                  <th class="py-2 px-3 font-semibold">Risiko</th>
                  <th class="py-2 pl-3 font-semibold w-24 num">Nilai</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-stone-100">
                @foreach($rk[$slot] as $b)
                  <tr>
                    <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                    <td class="py-2 px-3 text-cam-ink">{{ $b['kegiatan'] ?? '' }}</td>
                    <td class="py-2 px-3 text-stone-600">{{ $b['risiko'] ?? '' }}</td>
                    <td class="py-2 pl-3 num font-bold text-cam-ink">{{ $b['nilai'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      @endforeach
    </section>

    {{-- 9 --}}
    <section class="{{ $sec }} pt-2">
      <h3 class="{{ $h2 }}">9. {{ $komponen['pengesahan']['judul'] }}</h3>
      @php
        // PJO hanya muncul bila auditi perusahaan jasa pertambangan; blok tanda
        // tangan dirapatkan agar tidak menyisakan kolom kosong saat ia absen.
        $ttd = [];
        foreach ($pengesah as $key => $def) {
            $o = (array) ($r['pengesahan'][$key] ?? []);
            if (!$def['wajib'] && trim((string) ($o['nama'] ?? '')) === '') continue;
            $ttd[] = $o + ['peran' => $def['peran']];
        }
      @endphp
      <div class="flex flex-wrap justify-center gap-8 text-center text-[12px] mt-5">
        @foreach($ttd as $o)
          <div class="w-56 max-w-full">
            <p class="text-stone-500">{{ ($o['jabatan'] ?? '') ?: $o['peran'] }}</p>
            @if(!empty($o['tanggal']))
              <p class="text-[11px] text-stone-400 mt-0.5">{{ $tgl($o['tanggal']) }}</p>
            @endif
            <div class="h-16"></div>
            <p class="font-bold text-cam-ink border-t border-stone-300 pt-1.5">{!! trim((string) ($o['nama'] ?? '')) !== '' ? e($o['nama']) : '&nbsp;' !!}</p>
          </div>
        @endforeach
      </div>
    </section>
  </div>
</div>
@endsection
