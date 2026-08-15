@extends('layouts.app')
@section('title','Energy Calculator')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-4xl mx-auto space-y-5"
     x-data="{
       liter: 1000, kwh: 5000, m3: 0, ton: 2000,
       hemat_liter: 0, hemat_kwh: 0,
       f: {
         gjL: {{ Energi::GJ_PER_LITER }},   coL: {{ Energi::TCO2E_PER_LITER }},  rpL: {{ Energi::RP_PER_LITER }},
         gjK: {{ Energi::GJ_PER_KWH }},     coK: {{ Energi::TCO2E_PER_KWH }},    rpK: {{ Energi::RP_PER_KWH }},
         gjM: {{ Energi::GJ_PER_M3_GAS }},  coM: {{ Energi::TCO2E_PER_M3_GAS }}, rpM: {{ Energi::RP_PER_M3_GAS }},
       },
       n(v) { return Number(v) || 0 },
       get gj()     { return this.n(this.liter)*this.f.gjL + this.n(this.kwh)*this.f.gjK + this.n(this.m3)*this.f.gjM },
       get tco2e()  { return this.n(this.liter)*this.f.coL + this.n(this.kwh)*this.f.coK + this.n(this.m3)*this.f.coM },
       get rupiah() { return this.n(this.liter)*this.f.rpL + this.n(this.kwh)*this.f.rpK + this.n(this.m3)*this.f.rpM },
       get intensitas() { return this.n(this.ton) > 0 ? this.gj / this.n(this.ton) : 0 },
       get hematRp()  { return this.n(this.hemat_liter)*this.f.rpL + this.n(this.hemat_kwh)*this.f.rpK },
       get hematCo2() { return this.n(this.hemat_liter)*this.f.coL + this.n(this.hemat_kwh)*this.f.coK },
       get hematGj()  { return this.n(this.hemat_liter)*this.f.gjL + this.n(this.hemat_kwh)*this.f.gjK },
       angka(v, d = 2) { return new Intl.NumberFormat('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d }).format(v) },
       bulat(v) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(v) },
     }">

  <x-energi.kepala judul="Energy Calculator"
      ket="Alat hitung cepat memakai faktor konversi yang sama persis dengan seluruh halaman
           Energy. Angkanya tidak disimpan — ini untuk menimbang sebuah usulan sebelum
           dicatat, bukan untuk melaporkan." />

  <div class="grid gap-4 lg:grid-cols-2">

    {{-- Konversi --}}
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Konversi Energi</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Masukkan pemakaian, lihat setara energi, biaya, dan emisinya.</p>

      <div class="space-y-3.5 mt-5">
        @foreach ([
          ['liter', 'Solar (liter)'],
          ['kwh',   'Listrik (kWh)'],
          ['m3',    'Gas (m³)'],
          ['ton',   'Produksi (ton)'],
        ] as [$model, $label])
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">{{ $label }}</label>
            <input type="number" step="any" min="0" x-model="{{ $model }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition num">
          </div>
        @endforeach
      </div>

      <div class="grid grid-cols-2 gap-3 mt-5 pt-5 hairline border-b-0">
        @foreach ([
          ['Total Energi', 'angka(gj, 2)', 'GJ', '#F57C00'],
          ['Intensitas', 'angka(intensitas, 4)', 'GJ/ton', '#FF9800'],
          ['Emisi', 'angka(tco2e, 3)', 'tCO₂e', '#22312F'],
          ['Biaya', "'Rp ' + bulat(rupiah)", null, '#E2663A'],
        ] as [$l, $expr, $s, $w])
          <div>
            <div class="stat stat-sm" style="color:{{ $w }}">
              <span x-text="{{ $expr }}"></span>@if($s)<span class="stat-unit">{{ $s }}</span>@endif
            </div>
            <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
          </div>
        @endforeach
      </div>
    </section>

    {{-- Nilai penghematan --}}
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Nilai Penghematan</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Perkiraan penghematan bulanan sebuah usulan, dinilai dalam rupiah, energi, dan karbon
        sekaligus — ketiganya sering dibutuhkan pada lembar usulan yang sama.
      </p>

      <div class="space-y-3.5 mt-5">
        @foreach ([
          ['hemat_liter', 'Hemat solar (L per bulan)'],
          ['hemat_kwh',   'Hemat listrik (kWh per bulan)'],
        ] as [$model, $label])
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">{{ $label }}</label>
            <input type="number" step="any" min="0" x-model="{{ $model }}"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition num">
          </div>
        @endforeach
      </div>

      <div class="space-y-3 mt-5 pt-5 hairline border-b-0">
        @foreach ([
          ['Per bulan', "'Rp ' + bulat(hematRp)", 'angka(hematGj, 2)', 'angka(hematCo2, 3)'],
          ['Per tahun', "'Rp ' + bulat(hematRp * 12)", 'angka(hematGj * 12, 2)', 'angka(hematCo2 * 12, 3)'],
        ] as [$l, $rp, $gj, $co])
          <div class="rounded-xl border border-stone-100 px-4 py-3">
            <div class="text-[10px] font-bold uppercase tracking-wide text-stone-400">{{ $l }}</div>
            <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1 mt-2">
              <span class="num text-[17px] font-bold text-cam-coral" x-text="{{ $rp }}"></span>
              <span class="num text-[12.5px] text-stone-500"><span x-text="{{ $gj }}"></span> GJ</span>
              <span class="num text-[12.5px] text-stone-500"><span x-text="{{ $co }}"></span> tCO₂e</span>
            </div>
          </div>
        @endforeach
      </div>

      <a href="{{ route('energi.hemat') }}"
         class="block text-center rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition mt-4">
        Catat sebagai peluang penghematan
      </a>
    </section>
  </div>

  {{-- Faktor yang dipakai --}}
  <section class="kartu-lux rounded-2xl p-6">
    <h3 class="font-display text-[16px] font-black text-cam-ink">Faktor Konversi yang Dipakai</h3>
    <p class="text-[11.5px] text-stone-500 mt-1">
      Faktor yang sama dipakai seluruh halaman Energy, disimpan di satu berkas. Mengubahnya
      di sana langsung tercermin di sini dan di seluruh riwayat.
    </p>

    <div class="overflow-x-auto mt-4 -mx-1">
      <table class="w-full text-[12.5px] min-w-[480px]">
        <thead>
          <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
            <th class="text-left py-2.5">Sumber</th>
            <th class="num py-2.5">Energi</th>
            <th class="num py-2.5">Emisi</th>
            <th class="num py-2.5">Harga</th>
          </tr>
        </thead>
        <tbody>
          @foreach ([
            ['Solar', Energi::GJ_PER_LITER.' GJ/L', (Energi::TCO2E_PER_LITER * 1000).' kg CO₂e/L', 'Rp '.number_format(Energi::RP_PER_LITER).'/L'],
            ['Listrik', Energi::GJ_PER_KWH.' GJ/kWh', (Energi::TCO2E_PER_KWH * 1000).' kg CO₂e/kWh', 'Rp '.number_format(Energi::RP_PER_KWH).'/kWh'],
            ['Gas', Energi::GJ_PER_M3_GAS.' GJ/m³', (Energi::TCO2E_PER_M3_GAS * 1000).' kg CO₂e/m³', 'Rp '.number_format(Energi::RP_PER_M3_GAS).'/m³'],
          ] as [$nama, $gj, $co, $rp])
            <tr class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ $nama }}</td>
              <td class="num py-2.5">{{ $gj }}</td>
              <td class="num py-2.5">{{ $co }}</td>
              <td class="num py-2.5">{{ $rp }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </section>

</div>
@endsection
