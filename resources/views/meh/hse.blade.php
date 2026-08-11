@extends('layouts.app')
@section('title','HSE & SMKP')

@section('content')
@php use App\Support\Engineering as E; @endphp

<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">HSE Performance</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Frekuensi kecelakaan dihitung dari jumlah kejadian dan jam kerja kumulatif, bukan diketik
      sebagai angka jadi — definisinya berbeda antar perusahaan, dan yang dipakai di sini harus
      dapat ditelusuri. Pengalinya {{ number_format($h['pengali']) }} jam kerja.
    </p>
  </section>

  <div class="grid gap-4 grid-cols-2 lg:grid-cols-3">
    <x-kpi label="TRIFR" :nilai="number_format($h['trifr'],2)"
           :ket="$h['recordable'].' kejadian per '.number_format($h['jam_kerja']/1e6,2).' juta jam kerja'" warna="#C08A3E" />
    <x-kpi label="LTIFR" :nilai="number_format($h['ltifr'],2)"
           :ket="$h['lost_time'].' kejadian dengan hari kerja hilang'" warna="#E2663A" />
    <x-kpi label="Severity Rate" :nilai="number_format($h['severity'],1)"
           :ket="$h['hari_hilang'].' hari kerja hilang'" warna="#22312F" />
    <x-kpi label="Near Miss" :nilai="number_format($h['near_miss'])"
           ket="Terlaporkan pada periode berjalan" warna="#4C9AFF" />
    <x-kpi label="Safety Observation" :nilai="number_format($h['observasi'])"
           :ket="number_format(E::bagi($h['observasi'],$h['near_miss']),1).'× jumlah near miss'" warna="#0F766E" />
    <x-kpi label="Hari Tanpa LTI" :nilai="$h['hari_tanpa_lti']" satuan="hari"
           ket="Sejak kejadian terakhir" warna="#2A9D8F" />
  </div>

  <div class="grid gap-4 lg:grid-cols-2">
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Penerapan SMKP Minerba</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Tujuh elemen wajib menurut Kepdirjen 185.K/37.04/DJB/2019, beserta bobot penilaiannya.
      </p>

      <div class="mt-4">
        @foreach($smkp as $el)
          @php $w = $el['capaian'] >= 85 ? '#0F766E' : ($el['capaian'] >= 70 ? '#C08A3E' : '#E2663A'); @endphp
          <div class="py-3 hairline last:border-b-0">
            <div class="flex items-baseline justify-between gap-3">
              <span class="text-[12.5px] font-semibold text-cam-ink">
                <span class="num text-[11px] text-cam-lime-deep mr-2">{{ sprintf('%02d', $el['no']) }}</span>{{ $el['elemen'] }}
              </span>
              <span class="num text-[12.5px] font-bold shrink-0" style="color:{{ $w }}">
                {{ $el['capaian'] }}%<span class="text-[10.5px] text-stone-400 font-medium ml-2">bobot {{ $el['bobot'] }}%</span>
              </span>
            </div>
            <div class="mt-2 h-1.5 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-700"
                   style="width:{{ $el['capaian'] }}%; background:{{ $w }}"></div>
            </div>
          </div>
        @endforeach
      </div>

      @php $t = $h['nilaiSmkp']; $tingkat = $t >= 90 ? 'Sangat Baik' : ($t >= 80 ? 'Baik' : ($t >= 70 ? 'Cukup' : 'Perlu Perbaikan')); @endphp
      <div class="flex items-end justify-between gap-4 mt-5 pt-5 border-t border-dashed border-stone-200">
        <div>
          <div class="text-[10.5px] font-bold uppercase tracking-wide text-stone-500">Nilai Penerapan SMKP</div>
          <div class="stat stat-lg text-cam-lime-deep mt-1">{{ number_format($t,1) }}<span class="stat-unit">dari 100</span></div>
        </div>
        <div class="text-right">
          <div class="text-[10.5px] text-stone-400">Tingkat</div>
          <strong class="text-[14px] text-cam-ink">{{ $tingkat }}</strong>
        </div>
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Piramida Pelaporan</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Semakin lebar dasarnya, semakin dini bahaya tertangkap sebelum menjadi cedera.
      </p>
      <div class="space-y-2.5 mt-5">
        @foreach ([
          ['Safety Observation', $h['observasi'], '#0F766E'],
          ['Near Miss', $h['near_miss'], '#4C9AFF'],
          ['Recordable Injury', $h['recordable'], '#C08A3E'],
          ['Lost Time Injury', $h['lost_time'], '#E2663A'],
        ] as $i => [$nama,$nilai,$w])
          <div class="flex items-center justify-between gap-3 rounded-xl px-4 py-3 mx-auto transition"
               style="width:{{ 100 - $i * 15 }}%; background:{{ $w }}1A; border:1px solid {{ $w }}33">
            <span class="text-[12.5px] font-semibold text-cam-ink truncate">{{ $nama }}</span>
            <span class="num text-[15px] font-bold shrink-0" style="color:{{ $w }}">{{ number_format($nilai) }}</span>
          </div>
        @endforeach
      </div>
      <p class="text-[10.5px] text-stone-400 mt-5 leading-relaxed">
        Rasio observasi terhadap near miss {{ number_format(E::bagi($h['observasi'],$h['near_miss']),1) }}×.
        Dasar yang menyempit lebih sering berarti pelaporan yang menurun daripada bahaya yang berkurang.
      </p>
    </section>
  </div>

</div>
@endsection
