@extends('layouts.app')
@section('title','Rencana Audit SMKP '.$audit->tahun)

@php
  $r   = (array) ($audit->rencana ?? []);
  $rk  = (array) ($audit->risiko ?? []);
  $inp = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition';
  $sel = 'ring-focus w-full rounded-lg border border-stone-200 px-2.5 py-2 text-[12px] transition';
  $lbl = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';

  // Alpine memerlukan minimal satu baris kosong agar tabel dapat diisi.
  $susunan = $r['susunan'] ?? [];
  $tugas   = $r['tugas']   ?? [];
  if (!$susunan) $susunan = [['tanggal'=>'','waktu'=>'','kegiatan'=>'','auditi'=>'','auditor'=>'']];
  if (!$tugas)   $tugas   = [['nama'=>'','peran'=>'','registrasi'=>'','lingkup'=>'']];
  $present = ($rk['present'] ?? []) ?: [['kegiatan'=>'','risiko'=>'','nilai'=>'']];
  $future  = ($rk['future']  ?? []) ?: [['kegiatan'=>'','risiko'=>'','nilai'=>'']];
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-[15px] font-bold text-cam-ink">Rencana Audit Tahap II</h2>
        <p class="text-[12px] text-stone-400 mt-1 leading-relaxed">
          Sembilan komponen wajib sesuai Kepdirjen 185.K/37.04/DJB/2019. Rencana ini
          menjadi dasar kesepakatan antara klien audit, tim audit, dan auditi.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        <a href="{{ route('smkp.rencana.cetak',$audit) }}" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">Laporan</a>
        <a href="{{ route('smkp.show',$audit) }}" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">← Ringkasan</a>
      </div>
    </div>

    {{-- Kelengkapan sembilan komponen --}}
    <div class="mt-4 pt-4 border-t border-stone-100">
      <div class="flex items-center justify-between gap-3 mb-2">
        <span class="text-[11.5px] font-bold {{ $rekap['lengkap'] ? 'text-cam-lime-deep' : 'text-amber-600' }}">
          {{ $rekap['jumlah'] }} dari {{ $rekap['total'] }} komponen terisi
        </span>
      </div>
      <div class="flex flex-wrap gap-1.5">
        @foreach($komponen as $k => $c)
          <span class="text-[10px] font-bold px-2 py-1 rounded {{ $rekap['terisi'][$k] ? 'bg-cam-lime-soft text-cam-lime-deep' : 'bg-stone-100 text-stone-400' }}"
                title="{{ $c['ket'] }}">{{ $loop->iteration }}. {{ $c['judul'] }}</span>
        @endforeach
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('smkp.rencana.simpan',$audit) }}" class="space-y-5">
    @csrf

    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <label class="{{ $lbl }}">Nomor formulir Rencana Audit</label>
      <input name="nomor" value="{{ old('nomor',$r['nomor'] ?? '') }}" placeholder="Mis. GBU-OHSE-IV.059" class="{{ $inp }}">
    </section>

    {{-- 1–3 uraian --}}
    @foreach (['tujuan','kriteria','ruang_lingkup'] as $i => $k)
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[13px] font-bold text-cam-ink">{{ $i+1 }}. {{ $komponen[$k]['judul'] }}</h3>
        <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen[$k]['ket'] }}</p>
        <textarea name="{{ $k }}" rows="4" class="{{ $inp }}">{{ old($k,$r[$k] ?? '') }}</textarea>
      </section>
    @endforeach

    {{-- 4. Tanggal --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">4. {{ $komponen['tanggal']['judul'] }}</h3>
      <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen['tanggal']['ket'] }}</p>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="{{ $lbl }}">Mulai</label>
          <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai',$r['tanggal_mulai'] ?? '') }}" class="{{ $inp }}">
        </div>
        <div>
          <label class="{{ $lbl }}">Selesai</label>
          <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai',$r['tanggal_selesai'] ?? '') }}" class="{{ $inp }}">
        </div>
      </div>
      <p class="text-[11px] text-stone-400 mt-2.5">
        Alokasi hari kerja Tahap II dari Tahap I: <span class="num font-bold">{{ number_format($mandays['tahap2'],2) }}</span> hari.
      </p>
    </section>

    {{-- 5. Susunan kegiatan --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5"
             x-data="{ baris: {{ Illuminate\Support\Js::from(array_values($susunan)) }} }">
      <h3 class="text-[13px] font-bold text-cam-ink">5. {{ $komponen['susunan']['judul'] }}</h3>
      <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen['susunan']['ket'] }}</p>

      <p class="text-[11px] text-stone-400 mb-2.5">Kegiatan Tahap II: {{ implode(' · ', $kegiatan) }}.</p>

      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[720px]">
          <thead>
            <tr class="text-left text-stone-400 border-b border-stone-200">
              <th class="py-2 pr-2 font-semibold w-36">Tanggal</th>
              <th class="py-2 px-2 font-semibold w-28">Waktu</th>
              <th class="py-2 px-2 font-semibold">Kegiatan</th>
              <th class="py-2 px-2 font-semibold">Auditi</th>
              <th class="py-2 px-2 font-semibold">Auditor</th>
              <th class="py-2 pl-2 w-8"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <template x-for="(b,i) in baris" :key="i">
              <tr>
                <td class="py-1.5 pr-2"><input type="date" :name="`susunan[${i}][tanggal]`" x-model="b.tanggal" class="{{ $sel }}"></td>
                <td class="py-1.5 px-2"><input :name="`susunan[${i}][waktu]`" x-model="b.waktu" placeholder="08.00–10.00" class="{{ $sel }}"></td>
                <td class="py-1.5 px-2"><input :name="`susunan[${i}][kegiatan]`" x-model="b.kegiatan" class="{{ $sel }}"></td>
                <td class="py-1.5 px-2"><input :name="`susunan[${i}][auditi]`" x-model="b.auditi" class="{{ $sel }}"></td>
                <td class="py-1.5 px-2"><input :name="`susunan[${i}][auditor]`" x-model="b.auditor" class="{{ $sel }}"></td>
                <td class="py-1.5 pl-2 text-center">
                  <button type="button" @click="baris.splice(i,1)" class="text-stone-300 hover:text-red-500 transition text-[15px] leading-none">&times;</button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <button type="button" @click="baris.push({tanggal:'',waktu:'',kegiatan:'',auditi:'',auditor:''})"
              class="mt-3 rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">+ Baris</button>
    </section>

    {{-- 6. Pembagian tugas --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5"
             x-data="{ baris: {{ Illuminate\Support\Js::from(array_values($tugas)) }} }">
      <h3 class="text-[13px] font-bold text-cam-ink">6. {{ $komponen['tugas']['judul'] }}</h3>
      <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen['tugas']['ket'] }}</p>

      <div class="tabel-scroll">
        <table class="w-full text-[12px] min-w-[680px]">
          <thead>
            <tr class="text-left text-stone-400 border-b border-stone-200">
              <th class="py-2 pr-2 font-semibold">Nama</th>
              <th class="py-2 px-2 font-semibold w-40">Peran</th>
              <th class="py-2 px-2 font-semibold w-56">Nomor registrasi auditor</th>
              <th class="py-2 px-2 font-semibold">Elemen / lingkup</th>
              <th class="py-2 pl-2 w-8"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <template x-for="(b,i) in baris" :key="i">
              <tr>
                <td class="py-1.5 pr-2"><input :name="`tugas[${i}][nama]`" x-model="b.nama" class="{{ $sel }}"></td>
                <td class="py-1.5 px-2"><input :name="`tugas[${i}][peran]`" x-model="b.peran" placeholder="Ketua Tim Auditor" class="{{ $sel }}"></td>
                <td class="py-1.5 px-2"><input :name="`tugas[${i}][registrasi]`" x-model="b.registrasi" placeholder="000/AUD-SMKP/37.04/DBT/2021" class="{{ $sel }} num"></td>
                <td class="py-1.5 px-2"><input :name="`tugas[${i}][lingkup]`" x-model="b.lingkup" placeholder="Elemen I–IV" class="{{ $sel }}"></td>
                <td class="py-1.5 pl-2 text-center">
                  <button type="button" @click="baris.splice(i,1)" class="text-stone-300 hover:text-red-500 transition text-[15px] leading-none">&times;</button>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
      <button type="button" @click="baris.push({nama:'',peran:'',registrasi:'',lingkup:''})"
              class="mt-3 rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">+ Auditor</button>
    </section>

    {{-- 7. Sumber daya --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">7. {{ $komponen['sumberdaya']['judul'] }}</h3>
      <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen['sumberdaya']['ket'] }}</p>
      <textarea name="sumberdaya" rows="3" class="{{ $inp }}">{{ old('sumberdaya',$r['sumberdaya'] ?? '') }}</textarea>
    </section>

    {{-- 8. Metode & sampel + top risks --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <div>
        <h3 class="text-[13px] font-bold text-cam-ink">8. {{ $komponen['metode']['judul'] }}</h3>
        <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen['metode']['ket'] }}</p>
        <label class="{{ $lbl }}">Metode audit</label>
        <textarea name="metode" rows="3" class="{{ $inp }}">{{ old('metode',$r['metode'] ?? '') }}</textarea>
      </div>
      <div>
        <label class="{{ $lbl }}">Dasar pengambilan sampel</label>
        <textarea name="sampel" rows="3" class="{{ $inp }}">{{ old('sampel',$r['sampel'] ?? '') }}</textarea>
      </div>

      @foreach ([['present','Top Risks — Risk of Present (periode audit)'],['future','Top Risks — Risk of Future (rencana kegiatan)']] as [$slot,$judul])
        <div x-data="{ baris: {{ Illuminate\Support\Js::from(array_values($slot === 'present' ? $present : $future)) }} }">
          <label class="{{ $lbl }}">{{ $judul }}</label>
          <div class="tabel-scroll">
            <table class="w-full text-[12px] min-w-[540px]">
              <thead>
                <tr class="text-left text-stone-400 border-b border-stone-200">
                  <th class="py-2 pr-2 font-semibold">Kegiatan</th>
                  <th class="py-2 px-2 font-semibold">Risiko</th>
                  <th class="py-2 px-2 font-semibold w-28">Nilai risiko</th>
                  <th class="py-2 pl-2 w-8"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-stone-100">
                <template x-for="(b,i) in baris" :key="i">
                  <tr>
                    <td class="py-1.5 pr-2"><input :name="`risiko[{{ $slot }}][${i}][kegiatan]`" x-model="b.kegiatan" class="{{ $sel }}"></td>
                    <td class="py-1.5 px-2"><input :name="`risiko[{{ $slot }}][${i}][risiko]`" x-model="b.risiko" class="{{ $sel }}"></td>
                    <td class="py-1.5 px-2"><input :name="`risiko[{{ $slot }}][${i}][nilai]`" x-model="b.nilai" inputmode="numeric" class="{{ $sel }} num"></td>
                    <td class="py-1.5 pl-2 text-center">
                      <button type="button" @click="baris.splice(i,1)" class="text-stone-300 hover:text-red-500 transition text-[15px] leading-none">&times;</button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
          <button type="button" @click="baris.push({kegiatan:'',risiko:'',nilai:''})"
                  class="mt-2.5 rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">+ Risiko</button>
        </div>
      @endforeach
    </section>

    {{-- 9. Pengesahan --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
      <h3 class="text-[13px] font-bold text-cam-ink">9. {{ $komponen['pengesahan']['judul'] }}</h3>
      <p class="text-[11.5px] text-stone-400 mt-1 mb-3 leading-relaxed">{{ $komponen['pengesahan']['ket'] }}</p>

      <div class="space-y-3">
        @foreach($pengesah as $key => $def)
          <div class="rounded-xl border border-stone-100 p-3.5">
            <div class="text-[12px] font-bold text-cam-ink mb-2.5">
              {{ $def['peran'] }}
              @unless($def['wajib'])<span class="text-[10.5px] font-semibold text-stone-400">— bila auditi perusahaan jasa pertambangan</span>@endunless
            </div>
            <div class="grid gap-2.5 sm:grid-cols-3">
              <input name="pengesahan[{{ $key }}][nama]" value="{{ old('pengesahan.'.$key.'.nama', $r['pengesahan'][$key]['nama'] ?? '') }}" placeholder="Nama" class="{{ $inp }}">
              <input name="pengesahan[{{ $key }}][jabatan]" value="{{ old('pengesahan.'.$key.'.jabatan', $r['pengesahan'][$key]['jabatan'] ?? $def['peran']) }}" placeholder="Jabatan" class="{{ $inp }}">
              <input type="date" name="pengesahan[{{ $key }}][tanggal]" value="{{ old('pengesahan.'.$key.'.tanggal', $r['pengesahan'][$key]['tanggal'] ?? '') }}" class="{{ $inp }}">
            </div>
          </div>
        @endforeach
      </div>
    </section>

    <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold hover:brightness-105 transition">Simpan Rencana Audit</button>
  </form>
</div>
@endsection
