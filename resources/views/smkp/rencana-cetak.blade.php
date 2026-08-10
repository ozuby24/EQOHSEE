@extends('layouts.cetak')
@section('title','Rencana Audit SMKP '.$audit->tahun)

@php
  $r  = (array) ($audit->rencana ?? []);
  $rk = (array) ($audit->risiko ?? []);
  $tgl = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d F Y') : null;
  $isi = fn ($k) => trim((string) ($r[$k] ?? '')) !== '' ? $r[$k] : null;
  $h2  = 'text-[12.5px] font-bold text-cam-ink mb-2';
  $kosong = '<span class="text-stone-300 italic">belum ditetapkan</span>';
  $DARI = 3;   // lembar tetap: 1) butir 1-4  2) butir 5-6  3) butir 7-9
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">


  @unless($rekap['lengkap'])
    <div class="print:hidden rounded-xl bg-amber-50 border border-amber-200 px-4 py-3">
      <p class="text-[12px] font-bold text-amber-800">Rencana Audit belum lengkap</p>
      <p class="text-[11.5px] text-amber-700/90 mt-1 leading-relaxed">
        Belum terisi: {{ implode(', ', $rekap['kurang']) }}. Laporan tetap dapat dicetak,
        tetapi bagian tersebut akan kosong.
      </p>
    </div>
  @endunless

  {{-- ══════════ Lembar 1 — identitas dan butir 1 s.d. 4 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="1" :dari="$DARI">

    <header class="text-center border-b border-stone-200 pb-4 mb-5">
      <h1 class="font-display text-[17px] font-black text-cam-ink leading-tight uppercase">Formulir Rencana Audit Internal</h1>
      <h2 class="font-display text-[13px] font-bold text-cam-ink mt-0.5 uppercase">Sistem Manajemen Keselamatan Pertambangan</h2>
      <p class="text-[10.5px] text-stone-400 mt-1.5">Kepdirjen Minerba Nomor 185.K/37.04/DJB/2019 — Lampiran II</p>
    </header>

    <section class="mb-5">
      <h3 class="{{ $h2 }}">Data Auditi</h3>
      <table class="w-full text-[12px]">
        <tbody class="divide-y divide-stone-100">
          @foreach ([
            ['Nama perusahaan auditi', $audit->company?->name ?? 'Seluruh Perusahaan'],
            ['Jenis perizinan',        $audit->company?->izin_type ?: '—'],
            ['Jenis komoditas',        $audit->company?->commodity ?: '—'],
            ['Alamat perusahaan',      $audit->company?->address ?: ($audit->company?->location ?: '—')],
            ['Periode audit',          $audit->tahun],
          ] as [$k,$v])
            <tr>
              <td class="py-1.5 pr-3 text-stone-500 w-56 align-top">{{ $k }}</td>
              <td class="py-1.5 text-cam-ink whitespace-pre-line">{{ $v }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>

    @foreach (['tujuan','kriteria','ruang_lingkup'] as $i => $k)
      <section class="mb-4">
        <h3 class="{{ $h2 }}">{{ $i+1 }}. {{ $komponen[$k]['judul'] }}</h3>
        <p class="text-[12px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi($k) ? e($r[$k]) : $kosong !!}</p>
      </section>
    @endforeach

    <section>
      <h3 class="{{ $h2 }}">4. {{ $komponen['tanggal']['judul'] }}</h3>
      @if($tgl($r['tanggal_mulai'] ?? null) && $tgl($r['tanggal_selesai'] ?? null))
        <p class="text-[12px] text-stone-600">{{ $tgl($r['tanggal_mulai']) }} &ndash; {{ $tgl($r['tanggal_selesai']) }}</p>
      @else
        <p class="text-[12px]">{!! $kosong !!}</p>
      @endif
      <p class="text-[11px] text-stone-400 mt-1.5">
        Alokasi hari kerja audit Tahap II: <span class="num font-semibold">{{ number_format($mandays['tahap2'],2) }}</span> hari
        dari total <span class="num font-semibold">{{ number_format($mandays['total'],2) }}</span> hari.
      </p>
    </section>
  </x-lembar>

  {{-- ══════════ Lembar 2 — butir 5 dan 6 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="2" :dari="$DARI">

    <section class="mb-6">
      <h3 class="{{ $h2 }}">5. {{ $komponen['susunan']['judul'] }}</h3>
      @if(!empty($r['susunan']))
        <div class="tabel-scroll">
          <table class="w-full text-[11.5px] min-w-[620px]">
            <thead>
              <tr class="border-b border-stone-300 text-left text-stone-500">
                <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
                <th class="py-1.5 px-2 font-semibold w-28">Tanggal</th>
                <th class="py-1.5 px-2 font-semibold w-24">Waktu</th>
                <th class="py-1.5 px-2 font-semibold">Kegiatan</th>
                <th class="py-1.5 px-2 font-semibold">Auditi</th>
                <th class="py-1.5 pl-2 font-semibold">Auditor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($r['susunan'] as $b)
                <tr>
                  <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-1.5 px-2 text-stone-600">{{ $tgl($b['tanggal'] ?? null) ?? '—' }}</td>
                  <td class="py-1.5 px-2 text-stone-600 num">{{ $b['waktu'] ?? '' }}</td>
                  <td class="py-1.5 px-2 text-cam-ink">{{ $b['kegiatan'] ?? '' }}</td>
                  <td class="py-1.5 px-2 text-stone-600">{{ $b['auditi'] ?? '' }}</td>
                  <td class="py-1.5 pl-2 text-stone-600">{{ $b['auditor'] ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-[12px]">{!! $kosong !!}</p>
      @endif
      <p class="text-[11px] text-stone-400 mt-2 leading-relaxed">
        Rencana Audit Tahap II meliputi: {{ implode('; ', $kegiatan) }}.
      </p>
    </section>

    <section>
      <h3 class="{{ $h2 }}">6. {{ $komponen['tugas']['judul'] }}</h3>
      @if(!empty($r['tugas']))
        <div class="tabel-scroll">
          <table class="w-full text-[11.5px] min-w-[560px]">
            <thead>
              <tr class="border-b border-stone-300 text-left text-stone-500">
                <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
                <th class="py-1.5 px-2 font-semibold">Nama</th>
                <th class="py-1.5 px-2 font-semibold w-36">Jabatan</th>
                <th class="py-1.5 px-2 font-semibold w-52">Nomor Registrasi Auditor</th>
                <th class="py-1.5 pl-2 font-semibold">Lingkup</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($r['tugas'] as $b)
                <tr>
                  <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-1.5 px-2 font-semibold text-cam-ink">{{ $b['nama'] ?? '' }}</td>
                  <td class="py-1.5 px-2 text-stone-600">{{ $b['peran'] ?? '' }}</td>
                  <td class="py-1.5 px-2 num text-stone-600">{{ $b['registrasi'] ?? '' }}</td>
                  <td class="py-1.5 pl-2 text-stone-600">{{ $b['lingkup'] ?? '' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <p class="text-[12px]">{!! $kosong !!}</p>
      @endif
    </section>
  </x-lembar>

  {{-- ══════════ Lembar 3 — butir 7 s.d. 9 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="3" :dari="$DARI" akhir>

    <section class="mb-5">
      <h3 class="{{ $h2 }}">7. {{ $komponen['sumberdaya']['judul'] }}</h3>
      <p class="text-[12px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('sumberdaya') ? e($r['sumberdaya']) : $kosong !!}</p>
    </section>

    <section class="mb-5">
      <h3 class="{{ $h2 }}">8. {{ $komponen['metode']['judul'] }}</h3>
      <p class="text-[12px] text-stone-600 leading-relaxed whitespace-pre-line">{!! $isi('metode') ? e($r['metode']) : $kosong !!}</p>

      @if($isi('sampel'))
        <p class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mt-3 mb-1">Dasar Pengambilan Sampel</p>
        <p class="text-[12px] text-stone-600 leading-relaxed whitespace-pre-line">{{ $r['sampel'] }}</p>
      @endif

      @foreach ([['present','Data Top Risks — Risk of Present'],['future','Data Top Risks — Risk of Future']] as [$slot,$judul])
        @if(!empty($rk[$slot]))
          <p class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mt-3.5 mb-1.5">{{ $judul }}</p>
          <div class="tabel-scroll">
            <table class="w-full text-[11.5px] min-w-[460px]">
              <thead>
                <tr class="border-b border-stone-300 text-left text-stone-500">
                  <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
                  <th class="py-1.5 px-2 font-semibold">Kegiatan</th>
                  <th class="py-1.5 px-2 font-semibold">Risiko</th>
                  <th class="py-1.5 pl-2 font-semibold w-20 num">Nilai</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-stone-100">
                @foreach($rk[$slot] as $b)
                  <tr>
                    <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                    <td class="py-1.5 px-2 text-cam-ink">{{ $b['kegiatan'] ?? '' }}</td>
                    <td class="py-1.5 px-2 text-stone-600">{{ $b['risiko'] ?? '' }}</td>
                    <td class="py-1.5 pl-2 num font-bold text-cam-ink">{{ $b['nilai'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      @endforeach
    </section>

    <section>
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
      <div class="flex flex-wrap justify-center gap-8 text-center text-[11.5px] mt-5">
        @foreach($ttd as $o)
          <div class="w-52 max-w-full">
            <p class="text-stone-500">{{ ($o['jabatan'] ?? '') ?: $o['peran'] }}</p>
            @if(!empty($o['tanggal']))
              <p class="text-[10.5px] text-stone-400 mt-0.5">{{ $tgl($o['tanggal']) }}</p>
            @endif
            <div class="h-14"></div>
            <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">{!! trim((string) ($o['nama'] ?? '')) !== '' ? e($o['nama']) : '&nbsp;' !!}</p>
          </div>
        @endforeach
      </div>
    </section>
  </x-lembar>
</div>
@endsection
