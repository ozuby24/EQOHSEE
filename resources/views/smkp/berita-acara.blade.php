@extends('layouts.app')
@section('title','Berita Acara Tahap I — Audit SMKP '.$audit->tahun)

@php
  $p  = (array) ($audit->permulaan ?? []);
  $kj = (array) ($audit->kinerja ?? []);
  $kc = (array) ($audit->kecukupan ?? []);
  $tgl = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d F Y') : '—';
  $h2  = 'text-[13px] font-bold text-cam-ink mb-2';
  $kosong = '<span class="text-stone-300 italic">belum diisi</span>';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <div class="print:hidden flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('smkp.tahap1',$audit) }}" class="text-[12px] font-bold text-cam-lime-deep hover:underline">← Ubah Tahap I</a>
    <button onclick="window.print()"
            class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-105 transition">Cetak</button>
  </div>

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-7 space-y-6">

    <header class="text-center border-b border-stone-100 pb-5">
      <h1 class="font-display text-[19px] font-black text-cam-ink leading-tight">Berita Acara Hasil Pelaksanaan Tahapan Awal</h1>
      <h2 class="font-display text-[15px] font-bold text-cam-ink mt-0.5">Audit Internal Sistem Manajemen Keselamatan Pertambangan</h2>
      <p class="text-[11.5px] text-stone-400 mt-2">Kepdirjen Minerba Nomor 185.K/37.04/DJB/2019 — Lampiran II</p>
      <p class="text-[13.5px] font-bold text-cam-ink mt-3">{{ $audit->company?->name ?? 'Seluruh Perusahaan' }}</p>
      <p class="text-[12px] text-stone-500">Periode Audit {{ $audit->tahun }}</p>
    </header>

    {{-- I. Data kinerja --}}
    <section class="break-inside-avoid">
      <h3 class="{{ $h2 }}">I. Data Kinerja Keselamatan Pertambangan pada Periode Audit</h3>
      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[420px]">
          <tbody class="divide-y divide-stone-100">
            @foreach($kinerja as $key => $b)
              <tr>
                <td class="py-2 pr-3 text-stone-600">{{ $b['label'] }}</td>
                <td class="py-2 pl-3 num font-semibold text-cam-ink w-40 text-right">
                  {{ ($kj[$key] ?? '') !== '' ? $kj[$key] : '—' }} {{ $b['satuan'] }}
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    {{-- II. Kelayakan --}}
    <section class="break-inside-avoid">
      <h3 class="{{ $h2 }}">II. Penentuan Kelayakan Audit</h3>
      @if(!empty($p['tanggal_kontak']))
        <p class="text-[11.5px] text-stone-500 mb-2">
          Kontak awal: {{ $tgl($p['tanggal_kontak']) }}
          @if(!empty($p['media_kontak'])) · {{ $p['media_kontak'] }} @endif
          @if(!empty($p['wakil_auditi'])) · {{ $p['wakil_auditi'] }}@if(!empty($p['jabatan_wakil'])) ({{ $p['jabatan_wakil'] }})@endif @endif
        </p>
      @endif
      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[480px]">
          <thead>
            <tr class="border-b border-stone-200 text-left text-stone-400">
              <th class="py-2 pr-3 font-semibold w-8 num">No</th>
              <th class="py-2 px-3 font-semibold">Indikator Kelayakan Audit</th>
              <th class="py-2 pl-3 font-semibold w-64">Hasil Evaluasi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($kelayakan as $key => $label)
              <tr>
                <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                <td class="py-2 px-3 text-stone-600">{{ $label }}</td>
                <td class="py-2 pl-3 text-cam-ink">{{ $p['kelayakan'][$key] ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if(!empty($p['kesimpulan']))
        <p class="text-[12.5px] text-stone-600 leading-relaxed mt-3 whitespace-pre-line">{{ $p['kesimpulan'] }}</p>
      @endif
    </section>

    {{-- III. Hari kerja audit --}}
    <section class="break-inside-avoid">
      <h3 class="{{ $h2 }}">III. Perhitungan Hari Kerja Audit dan Jumlah Auditor</h3>

      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[480px] mb-4">
          <thead>
            <tr class="border-b border-stone-200 text-left text-stone-400">
              <th class="py-2 pr-3 font-semibold w-8 num">No</th>
              <th class="py-2 px-3 font-semibold">Dasar Faktor Penyesuaian</th>
              <th class="py-2 pl-3 font-semibold w-20 text-center">Ya</th>
              <th class="py-2 pl-3 font-semibold w-20 text-center">Tidak</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($faktor as $key => $label)
              @php $ya = !empty($p['faktor'][$key]); @endphp
              <tr>
                <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                <td class="py-2 px-3 text-stone-600 leading-relaxed">{{ $label }}</td>
                <td class="py-2 pl-3 text-center text-cam-ink font-bold">{{ $ya ? '✓' : '' }}</td>
                <td class="py-2 pl-3 text-center text-cam-ink font-bold">{{ $ya ? '' : '✓' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[420px]">
          <tbody class="divide-y divide-stone-100">
            @foreach ([
              ['Jumlah pekerja auditi', ($p['jumlah_pekerja'] ?? '') !== '' ? $p['jumlah_pekerja'].' karyawan' : '—'],
              ['Kelas risiko',          $p['kelas_risiko'] ?? '—'],
              ['Mandays dasar',         number_format($mandays['dasar'],2).' hari / '.$mandays['auditor'].' auditor = '.number_format($mandays['per_auditor'],2).' hari'],
              ['Faktor penyesuaian',    number_format($mandays['penyesuaian'],2).' hari'],
              ['Total mandays',         number_format($mandays['total'],2).' hari'],
              ['Alokasi Tahap I (maksimal 10% dari total)',  number_format($mandays['tahap1'],2).' hari'],
              ['Alokasi Tahap II (minimal 90% dari total)',  number_format($mandays['tahap2'],2).' hari'],
            ] as [$l,$v])
              <tr>
                <td class="py-2 pr-3 text-stone-600">{{ $l }}</td>
                <td class="py-2 pl-3 num font-semibold text-cam-ink text-right">{{ $v }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    {{-- IV. Tim audit --}}
    @if($audit->tim())
      <section class="break-inside-avoid">
        <h3 class="{{ $h2 }}">IV. Penetapan Tim Audit</h3>
        @if(!empty($p['surat_nomor']))
          <p class="text-[11.5px] text-stone-500 mb-2">
            Surat Pengangkatan Tim Audit nomor <span class="num">{{ $p['surat_nomor'] }}</span>
            @if(!empty($p['surat_tanggal'])) tanggal {{ $tgl($p['surat_tanggal']) }}@endif.
          </p>
        @endif
        <div class="tabel-scroll">
          <table class="w-full text-[12px] min-w-[480px]">
            <thead>
              <tr class="border-b border-stone-200 text-left text-stone-400">
                <th class="py-2 pr-3 font-semibold w-8 num">No</th>
                <th class="py-2 px-3 font-semibold">Nama</th>
                <th class="py-2 px-3 font-semibold w-40">Jabatan</th>
                <th class="py-2 pl-3 font-semibold w-56">Nomor Registrasi Auditor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($audit->tim() as $a)
                <tr>
                  <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-2 px-3 font-semibold text-cam-ink">{{ $a['nama'] ?? '' }}</td>
                  <td class="py-2 px-3 text-stone-600">{{ $a['peran'] ?? '' }}</td>
                  <td class="py-2 pl-3 num text-stone-600">{{ $a['registrasi'] ?? ($a['kompetensi'] ?? '') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    {{-- V. Kecukupan dokumentasi --}}
    <section class="break-inside-avoid">
      <h3 class="{{ $h2 }}">V. Penentuan Kecukupan Dokumentasi</h3>
      <p class="text-[11.5px] text-stone-500 leading-relaxed mb-2.5">
        Peninjauan dokumen dan rekaman auditi terhadap tujuh elemen Sistem Manajemen
        Keselamatan Pertambangan sesuai Lampiran II Kepdirjen 185.K/37.04/DJB/2019.
      </p>
      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[520px]">
          <thead>
            <tr class="border-b border-stone-200 text-left text-stone-400">
              <th class="py-2 pr-3 font-semibold w-8 num">No</th>
              <th class="py-2 px-3 font-semibold">Peninjauan Dokumentasi</th>
              <th class="py-2 px-3 font-semibold w-32">Hasil Evaluasi</th>
              <th class="py-2 pl-3 font-semibold">Catatan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($elemen as $e)
              @php $s = $kc[$e['kode']]['status'] ?? null; @endphp
              <tr>
                <td class="py-2 pr-3 num text-stone-400">{{ $loop->iteration }}</td>
                <td class="py-2 px-3 text-stone-600">Elemen {{ $e['kode'] }} — {{ $e['nama'] }}</td>
                <td class="py-2 px-3 font-semibold {{ $s === \App\Support\SmkpTahap::LENGKAP ? 'text-cam-lime-deep' : ($s ? 'text-amber-600' : 'text-stone-300') }}">
                  {{ \App\Support\SmkpTahap::labelKecukupan($s) }}
                </td>
                <td class="py-2 pl-3 text-stone-500">{{ $kc[$e['kode']]['ket'] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="text-[12.5px] text-stone-600 leading-relaxed mt-3">
        @if($rekap['siap'])
          Berdasarkan hasil evaluasi tersebut, pelaksanaan audit dapat dilanjutkan ke tahap berikutnya.
        @else
          {!! $kosong !!} — {{ $rekap['belum'] }} elemen belum ditinjau, sehingga kesimpulan
          kelanjutan ke Tahap II belum dapat ditetapkan.
        @endif
      </p>
    </section>

    {{-- Tanda tangan --}}
    <section class="pt-6 grid gap-8 sm:grid-cols-2 text-center text-[12px] break-inside-avoid">
      <div>
        <p class="text-stone-500">Ketua Tim Audit</p>
        <div class="h-16"></div>
        <p class="font-bold text-cam-ink border-t border-stone-300 pt-1.5">{!! $audit->ketua_auditor ?: '&nbsp;' !!}</p>
      </div>
      <div>
        <p class="text-stone-500">Kepala Teknik Tambang</p>
        <div class="h-16"></div>
        <p class="font-bold text-cam-ink border-t border-stone-300 pt-1.5">{!! $audit->rencana['pengesahan']['ktt']['nama'] ?? '&nbsp;' !!}</p>
      </div>
    </section>
  </div>
</div>
@endsection
