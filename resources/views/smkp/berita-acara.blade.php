@extends('layouts.cetak')
@section('title','Berita Acara Tahap I — Audit SMKP '.$audit->tahun)

@php
  $p  = (array) ($audit->permulaan ?? []);
  $kj = (array) ($audit->kinerja ?? []);
  $kc = (array) ($audit->kecukupan ?? []);
  $tgl = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->translatedFormat('d F Y') : '—';
  $h2  = 'text-[12.5px] font-bold text-cam-ink mb-2';
  // Empat lembar: tiap bagian formulir diberi satu lembar penuh agar
  // tabelnya tidak meluber dan nomor pada kop cocok dengan halaman cetaknya.
  $DARI = 4;
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">


  {{-- ══════════ Lembar 1 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="1" :dari="$DARI">

    <header class="text-center border-b border-stone-200 pb-4 mb-5">
      <h1 class="font-display text-[16px] font-black text-cam-ink leading-tight uppercase">Formulir Berita Acara Hasil Pelaksanaan</h1>
      <h2 class="font-display text-[14px] font-black text-cam-ink leading-tight uppercase">Tahapan Awal Audit Internal</h2>
      <h2 class="font-display text-[13px] font-bold text-cam-ink mt-0.5 uppercase">Sistem Manajemen Keselamatan Pertambangan</h2>
    </header>

    <p class="text-[12px] font-bold text-cam-ink mb-2">I. INFORMASI PELAKSANAAN AUDIT SMKP</p>

    <section class="mb-5 pl-3">
      <h3 class="{{ $h2 }}">A. Data Perusahaan Auditi</h3>
      <table class="w-full text-[12px]">
        <tbody class="divide-y divide-stone-100">
          @foreach ([
            ['Nama perusahaan auditi', $audit->company?->name ?? 'Seluruh Perusahaan'],
            ['Jenis perizinan',        $audit->company?->izin_type ?: '—'],
            ['Jenis komoditas',        $audit->company?->commodity ?: '—'],
            ['Alamat perusahaan',      $audit->company?->address ?: ($audit->company?->location ?: '—')],
            ['Periode audit',          $audit->tanggal_mulai
                                        ? $audit->tanggal_mulai->translatedFormat('d F Y').($audit->tanggal_selesai ? ' – '.$audit->tanggal_selesai->translatedFormat('d F Y') : '')
                                        : 'Tahun '.$audit->tahun],
          ] as [$k,$v])
            <tr>
              <td class="py-1.5 pr-3 text-stone-500 w-56 align-top">{{ $k }}</td>
              <td class="py-1.5 text-cam-ink whitespace-pre-line">{{ $v }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>

    <section class="pl-3">
      <h3 class="{{ $h2 }}">Data Kinerja Keselamatan Pertambangan pada Periode Audit</h3>
      <table class="w-full text-[12px]">
        <tbody class="divide-y divide-stone-100">
          @foreach($kinerja as $key => $b)
            <tr>
              <td class="py-1.5 pr-3 text-stone-600">{{ $b['label'] }}</td>
              <td class="py-1.5 pl-3 num font-semibold text-cam-ink w-40 text-right">
                {{ ($kj[$key] ?? '') !== '' ? $kj[$key] : '—' }} {{ $b['satuan'] }}
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
  </x-lembar>

  {{-- ══════════ Lembar 2 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="2" :dari="$DARI">

    <section class="mb-5">
      <h3 class="{{ $h2 }}">B. Pelaksanaan Kontak Awal dan Penentuan Kelayakan Audit</h3>
      @if(!empty($p['tanggal_kontak']))
        <p class="text-[11.5px] text-stone-500 mb-2 leading-relaxed">
          Kontak awal dilakukan pada {{ $tgl($p['tanggal_kontak']) }}
          @if(!empty($p['media_kontak'])) melalui {{ $p['media_kontak'] }} @endif
          @if(!empty($p['wakil_auditi'])) dengan {{ $p['wakil_auditi'] }}@if(!empty($p['jabatan_wakil'])) ({{ $p['jabatan_wakil'] }})@endif @endif.
        </p>
      @endif
      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[460px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
              <th class="py-1.5 px-2 font-semibold">Indikator Kelayakan Audit</th>
              <th class="py-1.5 pl-2 font-semibold w-60">Hasil Evaluasi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($kelayakan as $key => $label)
              <tr>
                <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                <td class="py-1.5 px-2 text-stone-600 leading-relaxed">{{ $label }}</td>
                <td class="py-1.5 pl-2 text-cam-ink">{{ $p['kelayakan'][$key] ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      @if(!empty($p['kesimpulan']))
        <p class="text-[12px] text-stone-600 leading-relaxed mt-2.5 whitespace-pre-line">{{ $p['kesimpulan'] }}</p>
      @endif
    </section>
  </x-lembar>

  {{-- ══════════ Lembar 3 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="3" :dari="$DARI">

    <section>
      <h3 class="{{ $h2 }}">C. Perhitungan Hari Kerja Audit dan Jumlah Auditor</h3>

      <p class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1.5">Dasar Faktor Penyesuaian</p>
      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[460px] mb-3.5">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
              <th class="py-1.5 px-2 font-semibold">Kondisi</th>
              <th class="py-1.5 pl-2 font-semibold w-16 text-center">Ya</th>
              <th class="py-1.5 pl-2 font-semibold w-16 text-center">Tidak</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($faktor as $key => $label)
              @php $ya = !empty($p['faktor'][$key]); @endphp
              <tr>
                <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                <td class="py-1.5 px-2 text-stone-600 leading-relaxed">{{ $label }}</td>
                <td class="py-1.5 pl-2 text-center text-cam-ink font-bold">{{ $ya ? '✓' : '' }}</td>
                <td class="py-1.5 pl-2 text-center text-cam-ink font-bold">{{ $ya ? '' : '✓' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <table class="w-full text-[12px]">
        <tbody class="divide-y divide-stone-100">
          @foreach ([
            ['Jumlah pekerja auditi', ($p['jumlah_pekerja'] ?? '') !== '' ? $p['jumlah_pekerja'].' karyawan' : '—'],
            ['Kelas risiko',          $p['kelas_risiko'] ?? '—'],
            ['Mandays',               number_format($mandays['dasar'],2).' hari / '.$mandays['auditor'].' auditor = '.number_format($mandays['per_auditor'],2).' hari'],
            ['Faktor penyesuaian',    number_format($mandays['penyesuaian'],2).' hari'],
            ['Total mandays',         number_format($mandays['total'],2).' hari'],
            ['Alokasi mandays untuk Tahap I Audit (maksimal 10% dari total)',  number_format($mandays['tahap1'],2).' hari'],
            ['Alokasi mandays untuk Tahap II Audit (minimal 90% dari total)',  number_format($mandays['tahap2'],2).' hari'],
          ] as [$l,$v])
            <tr>
              <td class="py-1.5 pr-3 text-stone-600">{{ $l }}</td>
              <td class="py-1.5 pl-3 num font-semibold text-cam-ink text-right whitespace-nowrap">{{ $v }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>
  </x-lembar>

  {{-- ══════════ Lembar 4 ══════════ --}}
  <x-lembar :dok="$dok" :halaman="4" :dari="$DARI" akhir>

    <p class="text-[12px] font-bold text-cam-ink mb-2">II. HASIL PERMULAAN AUDIT DAN PENINJAUAN DOKUMEN</p>

    @if($audit->tim())
      <section class="mb-5 pl-3">
        <h3 class="{{ $h2 }}">A. Penetapan Tim Audit</h3>
        @if(!empty($p['surat_nomor']))
          <p class="text-[11.5px] text-stone-500 mb-2 leading-relaxed">
            Kepala Teknik Tambang telah menugaskan Tim Audit melalui Surat Pengangkatan Tim Audit
            nomor <span class="num">{{ $p['surat_nomor'] }}</span>@if(!empty($p['surat_tanggal'])) tanggal {{ $tgl($p['surat_tanggal']) }}@endif.
          </p>
        @endif
        <div class="tabel-scroll">
          <table class="w-full text-[11.5px] min-w-[460px]">
            <thead>
              <tr class="border-b border-stone-300 text-left text-stone-500">
                <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
                <th class="py-1.5 px-2 font-semibold">Nama</th>
                <th class="py-1.5 px-2 font-semibold w-36">Jabatan</th>
                <th class="py-1.5 pl-2 font-semibold w-52">Nomor Registrasi Auditor</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($audit->tim() as $a)
                <tr>
                  <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                  <td class="py-1.5 px-2 font-semibold text-cam-ink">{{ $a['nama'] ?? '' }}</td>
                  <td class="py-1.5 px-2 text-stone-600">{{ $a['peran'] ?? '' }}</td>
                  <td class="py-1.5 pl-2 num text-stone-600">{{ $a['registrasi'] ?? ($a['kompetensi'] ?? '') }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </section>
    @endif

    <section class="mb-6 pl-3">
      <h3 class="{{ $h2 }}">B. Penentuan Kecukupan Dokumentasi</h3>
      <p class="text-[11.5px] text-stone-500 leading-relaxed mb-2">
        Peninjauan dokumen dan rekaman auditi terhadap tujuh elemen Sistem Manajemen
        Keselamatan Pertambangan sesuai Lampiran II Kepdirjen 185.K/37.04/DJB/2019.
      </p>
      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[500px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold w-8 num">No</th>
              <th class="py-1.5 px-2 font-semibold">Peninjauan Dokumentasi</th>
              <th class="py-1.5 px-2 font-semibold w-28">Hasil Evaluasi</th>
              <th class="py-1.5 pl-2 font-semibold">Catatan</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($elemen as $e)
              @php $s = $kc[$e['kode']]['status'] ?? null; @endphp
              <tr>
                <td class="py-1.5 pr-2 num text-stone-400">{{ $loop->iteration }}</td>
                <td class="py-1.5 px-2 text-stone-600">Elemen {{ $e['kode'] }} {{ $e['nama'] }}</td>
                <td class="py-1.5 px-2 font-semibold {{ $s === \App\Support\SmkpTahap::LENGKAP ? 'text-cam-lime-deep' : ($s ? 'text-amber-600' : 'text-stone-300') }}">
                  {{ \App\Support\SmkpTahap::labelKecukupan($s) }}
                </td>
                <td class="py-1.5 pl-2 text-stone-500">{{ $kc[$e['kode']]['ket'] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="text-[12px] text-stone-600 leading-relaxed mt-2.5">
        @if($rekap['siap'])
          Berdasarkan hasil evaluasi tersebut di atas, maka pelaksanaan Audit Internal Sistem
          Manajemen Keselamatan Pertambangan dapat dilanjutkan ke tahap berikutnya.
        @else
          <span class="text-amber-700">{{ $rekap['belum'] }} elemen belum ditinjau, sehingga kesimpulan
          kelanjutan ke Tahap II belum dapat ditetapkan.</span>
        @endif
      </p>
    </section>

    <section class="grid gap-8 sm:grid-cols-2 text-center text-[11.5px] pt-4">
      <div>
        <p class="text-stone-500">Ketua Tim Audit</p>
        <div class="h-14"></div>
        <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">{!! $audit->ketua_auditor ?: '&nbsp;' !!}</p>
      </div>
      <div>
        <p class="text-stone-500">Kepala Teknik Tambang</p>
        <div class="h-14"></div>
        <p class="font-bold text-cam-ink border-t border-stone-400 pt-1.5">{!! $audit->rencana['pengesahan']['ktt']['nama'] ?? ($audit->company?->ktt ?: '&nbsp;') !!}</p>
      </div>
    </section>
  </x-lembar>
</div>
@endsection
