@extends('layouts.app')
@section('title','Kriteria Audit SMKP Minerba')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink leading-tight">Kriteria Audit SMKP Minerba</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed">
      {{ $meta['basis'] ?? 'Kepdirjen Minerba Nomor 185.K/37.04/DJB/2019 — Lampiran II' }}.
      Halaman ini acuan baca: struktur elemen, bobot, dan nilai maksimum yang dipakai
      seluruh formulir penilaian. Tiap sub-elemen menyebut halaman Kepdirjen-nya
      supaya angkanya dapat ditelusuri kembali ke sumbernya.
    </p>

    <div class="grid gap-3 grid-cols-2 sm:grid-cols-4 mt-5 pt-5 hairline border-b-0">
      @foreach ([
        ['Elemen', count($elemen)],
        ['Sub-elemen', array_sum(array_map(fn($e) => count($e['sub']), $elemen))],
        ['Butir dinilai', count(\App\Support\Smkp::butir())],
        ['Nilai maksimum', \App\Support\Smkp::totalNilai()],
      ] as [$l,$v])
        <div>
          <div class="num text-[20px] font-bold text-cam-lime-deep">{{ $v }}</div>
          <div class="text-[11px] text-stone-400 mt-1">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </section>

  {{-- Ambang kategori dan tingkat penerapan --}}
  <div class="grid gap-4 md:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Kategori Temuan</h3>
      <p class="text-[11.5px] text-stone-500 mb-3 leading-relaxed">Ditentukan dari capaian nilai tiap sub-elemen.</p>
      {{-- Rentangnya diambil dari keterangan pada berkas acuan, bukan
           dihitung ulang di sini — supaya yang tampil selalu sama dengan
           yang dipakai mesin penilaian. --}}
      <div class="space-y-2">
        @foreach($kategori as $k)
          <div class="rounded-xl border border-stone-100 px-3.5 py-2.5">
            <div class="flex items-center gap-3">
              <span class="shrink-0 w-2.5 h-2.5 rounded-full" style="background:{{ $k['warna'] ?? '#9AA3AE' }}"></span>
              <span class="text-[12.5px] font-semibold text-cam-ink flex-1 min-w-0">{{ $k['label'] }}</span>
            </div>
            @if(!empty($k['ket']))
              <p class="text-[11px] text-stone-500 mt-1 ml-5.5 leading-relaxed">{{ $k['ket'] }}</p>
            @endif
          </div>
        @endforeach
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-5">
      <h3 class="text-[13px] font-bold text-cam-ink mb-3">Tingkat Penerapan</h3>
      <p class="text-[11.5px] text-stone-500 mb-3 leading-relaxed">Ditentukan dari nilai akhir audit, skala 0–100.</p>
      @php
        // Ambang hanya menyimpan batas bawah; batas atasnya diturunkan dari
        // ambang tingkat di atasnya agar rentangnya terbaca utuh.
        $urut = collect($tingkat)->sortByDesc('min')->values();
      @endphp
      <div class="space-y-2">
        @foreach($urut as $i => $t)
          @php $atas = $i === 0 ? null : ($urut[$i-1]['min'] - 1); @endphp
          <div class="flex items-center gap-3 rounded-xl border border-stone-100 px-3.5 py-2.5">
            <span class="shrink-0 w-2.5 h-2.5 rounded-full" style="background:{{ $t['warna'] ?? '#9AA3AE' }}"></span>
            <span class="text-[12.5px] font-semibold text-cam-ink flex-1 min-w-0">{{ $t['label'] }}</span>
            <span class="num text-[11.5px] text-stone-500 shrink-0">
              {{ $atas === null ? '≥ '.$t['min'] : $t['min'].' – '.$atas }}
            </span>
          </div>
        @endforeach
      </div>
    </section>
  </div>

  {{-- Tujuh elemen --}}
  <div class="space-y-3">
    @foreach($elemen as $e)
      @php $maks = \App\Support\Smkp::maksElemen($e); @endphp
      <details class="kartu-lux rounded-2xl overflow-hidden group">
        <summary class="px-5 py-4 cursor-pointer flex flex-wrap items-center gap-3 hover:bg-cam-sand/25 transition">
          <span class="shrink-0 num text-[11px] font-black text-white px-2.5 py-1 rounded-lg bg-cam-lime">{{ $e['kode'] }}</span>
          <span class="text-[13.5px] font-bold text-cam-ink flex-1 min-w-0">{{ $e['nama'] }}</span>
          <span class="shrink-0 text-[11px] text-stone-400">
            bobot <span class="num font-bold text-cam-ink">{{ $e['bobot'] }}</span> ·
            {{ count($e['sub']) }} sub-elemen ·
            maks <span class="num font-bold text-cam-ink">{{ $maks }}</span>
          </span>
          <span class="shrink-0 text-stone-300 group-open:rotate-180 transition">▾</span>
        </summary>

        <div class="hairline"></div>
        <div class="tabel-scroll px-5 py-4">
          <table class="w-full text-[12px] min-w-[520px]">
            <thead>
              <tr class="border-b border-stone-200 text-left text-stone-400">
                <th class="py-2 pr-3 font-semibold w-20">Kode</th>
                <th class="py-2 px-3 font-semibold">Sub-elemen</th>
                <th class="py-2 px-3 font-semibold num w-16">Maks</th>
                <th class="py-2 pl-3 font-semibold w-28">Acuan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              @foreach($e['sub'] as $s)
                <tr>
                  <td class="py-2 pr-3 num font-semibold text-cam-lime-deep align-top">{{ $s['kode'] }}</td>
                  <td class="py-2 px-3 text-cam-ink">
                    {{ $s['nama'] }}
                    @if(!empty($s['subsub']))
                      <ul class="mt-1.5 space-y-1">
                        @foreach($s['subsub'] as $ss)
                          <li class="text-[11.5px] text-stone-500 flex gap-2">
                            <span class="num shrink-0 text-stone-400">{{ $ss['kode'] }}</span>
                            <span class="min-w-0">{{ $ss['nama'] }}</span>
                            <span class="num shrink-0 text-stone-400 ml-auto">{{ $ss['maks'] }}</span>
                          </li>
                        @endforeach
                      </ul>
                    @endif
                  </td>
                  <td class="py-2 px-3 num font-bold text-cam-ink align-top">{{ \App\Support\Smkp::maksSub($s) }}</td>
                  <td class="py-2 pl-3 text-[11px] text-stone-400 align-top">{{ $s['ref'] ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </details>
    @endforeach
  </div>
</div>
@endsection
