@extends('layouts.app')
@section('title','Tahap I — Permulaan Audit '.$audit->tahun)

@php
  $p  = (array) ($audit->permulaan ?? []);
  $kj = (array) ($audit->kinerja ?? []);
  $kc = (array) ($audit->kecukupan ?? []);
  $inp = 'ring-focus w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[12.5px] transition';
  $lbl = 'block text-[11px] font-bold uppercase tracking-wide text-stone-500 mb-1.5';
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  @if(session('ok'))<div class="rounded-xl bg-cam-lime-soft border border-cam-lime/25 text-cam-lime-deep px-4 py-3 text-[12.5px] font-medium animate-pop">{{ session('ok') }}</div>@endif

  <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h2 class="text-[15px] font-bold text-cam-ink">Tahap I — Permulaan Audit</h2>
        <p class="text-[12px] text-stone-400 mt-1 leading-relaxed">
          Kontak awal, penentuan kelayakan, dan peninjauan kecukupan dokumentasi.
          Hasilnya menjadi Berita Acara dan dasar penyusunan Rencana Audit.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 shrink-0">
        <a href="{{ route('smkp.berita-acara',$audit) }}" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">Berita Acara</a>
        <a href="{{ route('smkp.show',$audit) }}" class="rounded-xl border border-stone-200 px-3.5 py-2 text-[12px] font-bold text-stone-600 hover:bg-stone-50 transition">← Ringkasan</a>
      </div>
    </div>
  </div>

  <form method="POST" action="{{ route('smkp.tahap1.simpan',$audit) }}" class="space-y-5">
    @csrf

    {{-- A. Kontak awal & penugasan --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <h3 class="text-[13px] font-bold text-cam-ink">A. Kontak Awal dan Surat Penugasan</h3>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="{{ $lbl }}">Tanggal kontak awal</label>
          <input type="date" name="permulaan[tanggal_kontak]" value="{{ old('permulaan.tanggal_kontak',$p['tanggal_kontak'] ?? '') }}" class="{{ $inp }}">
        </div>
        <div>
          <label class="{{ $lbl }}">Media</label>
          <input name="permulaan[media_kontak]" value="{{ old('permulaan.media_kontak',$p['media_kontak'] ?? '') }}" placeholder="Pertemuan offline / daring" class="{{ $inp }}">
        </div>
        <div>
          <label class="{{ $lbl }}">Perwakilan auditi</label>
          <input name="permulaan[wakil_auditi]" value="{{ old('permulaan.wakil_auditi',$p['wakil_auditi'] ?? '') }}" class="{{ $inp }}">
        </div>
        <div>
          <label class="{{ $lbl }}">Jabatan perwakilan</label>
          <input name="permulaan[jabatan_wakil]" value="{{ old('permulaan.jabatan_wakil',$p['jabatan_wakil'] ?? '') }}" class="{{ $inp }}">
        </div>
        <div>
          <label class="{{ $lbl }}">Nomor surat pengangkatan tim audit</label>
          <input name="permulaan[surat_nomor]" value="{{ old('permulaan.surat_nomor',$p['surat_nomor'] ?? '') }}" class="{{ $inp }}">
        </div>
        <div>
          <label class="{{ $lbl }}">Tanggal surat</label>
          <input type="date" name="permulaan[surat_tanggal]" value="{{ old('permulaan.surat_tanggal',$p['surat_tanggal'] ?? '') }}" class="{{ $inp }}">
        </div>
      </div>
    </section>

    {{-- B. Kinerja Keselamatan Pertambangan --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <h3 class="text-[13px] font-bold text-cam-ink">B. Data Kinerja Keselamatan Pertambangan pada Periode Audit</h3>
      <div class="grid gap-3 sm:grid-cols-2">
        @foreach($kinerja as $key => $b)
          <div>
            <label class="{{ $lbl }}">{{ $b['label'] }}@if($b['satuan']) <span class="normal-case text-stone-300">({{ $b['satuan'] }})</span>@endif</label>
            <input name="kinerja[{{ $key }}]" value="{{ old('kinerja.'.$key, $kj[$key] ?? '') }}" inputmode="decimal" class="{{ $inp }} num">
          </div>
        @endforeach
      </div>
    </section>

    {{-- C. Kelayakan audit --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <h3 class="text-[13px] font-bold text-cam-ink">C. Penentuan Kelayakan Audit</h3>
      <div class="space-y-3">
        @foreach($kelayakan as $key => $label)
          <div>
            <label class="{{ $lbl }} normal-case tracking-normal text-[11.5px] font-semibold text-stone-600">{{ $loop->iteration }}. {{ $label }}</label>
            <input name="permulaan[kelayakan][{{ $key }}]" value="{{ old('permulaan.kelayakan.'.$key, $p['kelayakan'][$key] ?? '') }}"
                   placeholder="Hasil evaluasi" class="{{ $inp }}">
          </div>
        @endforeach
      </div>
      <div>
        <label class="{{ $lbl }}">Kesimpulan kelayakan</label>
        <textarea name="permulaan[kesimpulan]" rows="2" class="{{ $inp }}" placeholder="Mis. audit layak untuk dilaksanakan.">{{ old('permulaan.kesimpulan',$p['kesimpulan'] ?? '') }}</textarea>
      </div>
    </section>

    {{-- D. Hari kerja audit --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <h3 class="text-[13px] font-bold text-cam-ink">D. Perhitungan Hari Kerja Audit</h3>

      <p class="text-[11.5px] text-stone-500 leading-relaxed">Dasar faktor penyesuaian — centang kondisi yang terpenuhi.</p>
      <div class="space-y-2">
        @foreach($faktor as $key => $label)
          <label class="flex items-start gap-2.5 rounded-xl border border-stone-100 px-3.5 py-2.5 cursor-pointer hover:bg-stone-50 transition">
            <input type="checkbox" name="permulaan[faktor][{{ $key }}]" value="1" class="mt-0.5 shrink-0"
                   @checked(old('permulaan.faktor.'.$key, !empty($p['faktor'][$key])))>
            <span class="text-[12px] text-stone-600 leading-relaxed">{{ $loop->iteration }}. {{ $label }}</span>
          </label>
        @endforeach
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="{{ $lbl }}">Jumlah pekerja auditi</label>
          <input name="permulaan[jumlah_pekerja]" value="{{ old('permulaan.jumlah_pekerja',$p['jumlah_pekerja'] ?? '') }}" inputmode="numeric" class="{{ $inp }} num">
        </div>
        <div>
          <label class="{{ $lbl }}">Kelas risiko</label>
          <select name="permulaan[kelas_risiko]" class="{{ $inp }}">
            <option value="">—</option>
            @foreach(\App\Support\SmkpTahap::kelasRisiko() as $k)
              <option value="{{ $k }}" @selected(old('permulaan.kelas_risiko',$p['kelas_risiko'] ?? '')===$k)>{{ $k }}</option>
            @endforeach
          </select>
        </div>
        <div>
          <label class="{{ $lbl }}">Mandays dasar (hari)</label>
          <input name="permulaan[mandays_dasar]" value="{{ old('permulaan.mandays_dasar',$p['mandays_dasar'] ?? '') }}" inputmode="decimal" class="{{ $inp }} num">
        </div>
        <div>
          <label class="{{ $lbl }}">Jumlah auditor</label>
          <input name="permulaan[jumlah_auditor]" value="{{ old('permulaan.jumlah_auditor',$p['jumlah_auditor'] ?? 1) }}" inputmode="numeric" class="{{ $inp }} num">
        </div>
        <div class="sm:col-span-2">
          <label class="{{ $lbl }}">Faktor penyesuaian (hari)</label>
          <input name="permulaan[penyesuaian]" value="{{ old('permulaan.penyesuaian',$p['penyesuaian'] ?? '') }}" inputmode="decimal" class="{{ $inp }} num"
                 placeholder="Kosongkan untuk memakai usulan: {{ $mandays['usul_penyesuaian'] }} hari">
          <p class="text-[11px] text-stone-400 mt-1.5">Usulan dari kondisi yang dicentang: {{ $mandays['usul_penyesuaian'] }} hari.</p>
        </div>
      </div>

      <div class="grid gap-3 grid-cols-2 sm:grid-cols-4 rounded-xl bg-stone-50 p-4">
        @foreach ([
          ['Per auditor', $mandays['per_auditor']],
          ['Total mandays', $mandays['total']],
          ['Alokasi Tahap I (maks 10%)', $mandays['tahap1']],
          ['Alokasi Tahap II (min 90%)', $mandays['tahap2']],
        ] as [$l,$v])
          <div>
            <div class="num text-[16px] font-bold text-cam-ink">{{ number_format($v,2) }}</div>
            <div class="text-[10.5px] text-stone-400 mt-1 leading-tight">{{ $l }}</div>
          </div>
        @endforeach
      </div>
    </section>

    {{-- E. Kecukupan dokumentasi --}}
    <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <h3 class="text-[13px] font-bold text-cam-ink">E. Penentuan Kecukupan Dokumentasi</h3>
        <span class="text-[11px] font-bold shrink-0 {{ $rekap['siap'] ? 'text-cam-lime-deep' : 'text-amber-600' }}">
          {{ $rekap['lengkap'] }} lengkap · {{ $rekap['tidak'] }} tidak lengkap · {{ $rekap['belum'] }} belum ditinjau
        </span>
      </div>

      <div class="space-y-2">
        @foreach($elemen as $e)
          @php $cur = old('kecukupan.'.$e['kode'].'.status', $kc[$e['kode']]['status'] ?? ''); @endphp
          <div class="rounded-xl border border-stone-100 p-3.5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="text-[12.5px] font-semibold text-cam-ink min-w-0">Elemen {{ $e['kode'] }} — {{ $e['nama'] }}</div>
              <div class="flex gap-1.5 shrink-0">
                @foreach ([\App\Support\SmkpTahap::LENGKAP => 'Lengkap', \App\Support\SmkpTahap::TIDAK_LENGKAP => 'Tidak Lengkap'] as $v => $t)
                  <label class="cursor-pointer">
                    <input type="radio" class="peer sr-only" name="kecukupan[{{ $e['kode'] }}][status]" value="{{ $v }}" @checked($cur===$v)>
                    <span class="block rounded-lg border border-stone-200 px-3 py-1.5 text-[11.5px] font-bold text-stone-500
                                 peer-checked:border-cam-lime peer-checked:bg-cam-lime-soft peer-checked:text-cam-lime-deep transition">{{ $t }}</span>
                  </label>
                @endforeach
              </div>
            </div>
            <input name="kecukupan[{{ $e['kode'] }}][ket]" value="{{ old('kecukupan.'.$e['kode'].'.ket', $kc[$e['kode']]['ket'] ?? '') }}"
                   placeholder="Catatan peninjauan" class="{{ $inp }} mt-2.5">
          </div>
        @endforeach
      </div>
    </section>

    <button class="lime-gradient shadow-glow rounded-xl text-white px-5 py-3 text-[13px] font-bold hover:brightness-105 transition">Simpan Tahap I</button>
  </form>
</div>
@endsection
