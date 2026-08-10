@extends('layouts.cetak')
@section('title','Laporan Kinerja Energi')

@php
  use App\Support\Energi;

  // Tiga lembar tetap: ringkasan, kinerja alat, lalu penghematan dan emisi.
  // Peringkat unit dipenggal karena satu lembar hanya memuat sekitar dua belas
  // baris tabel setelah kop dan judulnya — lebih dari itu tumpah ke halaman
  // berikutnya, dan nomor halaman pada kop menjadi bohong.
  $DARI  = 3;
  $turun = $baseline ? $baseline->penurunanTercapai($r['intensitas']) : 0.0;
@endphp

@section('content')
<div class="max-w-4xl mx-auto space-y-5 print:max-w-none print:space-y-0">

  {{-- ========== Lembar 1 — Ringkasan ========== --}}
  <x-lembar :dok="$dok" :halaman="1" :dari="$DARI">

    <header class="text-center border-b border-stone-200 pb-4 mb-5">
      <h1 class="font-display text-[16px] font-black text-cam-ink leading-tight uppercase">Laporan Kinerja Energi dan Emisi Karbon</h1>
      <p class="text-[11.5px] text-stone-500 mt-2">
        Periode {{ $dari->translatedFormat('d F Y') }} sampai {{ $sampai->translatedFormat('d F Y') }}
        · {{ $r['hari'] }} hari
      </p>
    </header>

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">1. Ringkasan Kinerja</h3>
    <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3">
      Seluruh sumber energi disamakan ke gigajoule sebelum dijumlahkan; tanpa itu liter solar
      dan kilowatt-jam tidak dapat dibandingkan. Angka pada laporan ini diturunkan dari catatan
      harian, bukan dicatat ulang, sehingga tidak ada angka yang perlu dicocokkan belakangan.
    </p>

    <table class="w-full text-[11.5px] mb-5">
      <tbody class="divide-y divide-stone-100">
        @foreach ([
          ['Total energi', number_format($r['gj'], 2).' GJ'],
          ['Intensitas energi', number_format($r['intensitas'], 4).' GJ per ton'],
          ['Produksi', number_format($r['ton'], 2).' ton · '.number_format($r['bcm'], 2).' BCM'],
          ['Emisi karbon', number_format($r['tco2e'], 3).' tCO₂e'],
          ['Biaya energi', 'Rp '.number_format($r['rupiah'], 0)],
        ] as [$k, $v])
          <tr>
            <td class="py-2 pr-3 text-stone-500 w-1/2">{{ $k }}</td>
            <td class="py-2 num font-semibold text-cam-ink">{{ $v }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">2. Rincian per Sumber Energi</h3>
    <div class="tabel-scroll mb-5">
      <table class="w-full text-[11.5px] min-w-[500px]">
        <thead>
          <tr class="border-b border-stone-300 text-left text-stone-500">
            <th class="py-1.5 pr-2 font-semibold">Sumber</th>
            <th class="py-1.5 px-2 font-semibold num">Jumlah</th>
            <th class="py-1.5 px-2 font-semibold num">GJ</th>
            <th class="py-1.5 px-2 font-semibold num">Porsi</th>
            <th class="py-1.5 px-2 font-semibold num">tCO₂e</th>
            <th class="py-1.5 pl-2 font-semibold num">Biaya</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-stone-100">
          @foreach($r['rincian'] as $nama => $x)
            <tr>
              <td class="py-2 pr-2 font-semibold text-cam-ink">{{ $nama }}</td>
              <td class="py-2 px-2 num">{{ number_format($x['jumlah'], 1) }} {{ $x['satuan'] }}</td>
              <td class="py-2 px-2 num">{{ number_format($x['gj'], 2) }}</td>
              <td class="py-2 px-2 num">{{ $r['gj'] > 0 ? number_format($x['gj'] / $r['gj'] * 100, 1) : '0,0' }}%</td>
              <td class="py-2 px-2 num">{{ number_format($x['tco2e'], 3) }}</td>
              <td class="py-2 pl-2 num">Rp {{ number_format($x['rupiah']) }}</td>
            </tr>
          @endforeach
          <tr class="font-bold text-cam-ink border-t border-stone-300">
            <td class="py-2 pr-2">Total</td>
            <td class="py-2 px-2 num">—</td>
            <td class="py-2 px-2 num">{{ number_format($r['gj'], 2) }}</td>
            <td class="py-2 px-2 num">100%</td>
            <td class="py-2 px-2 num">{{ number_format($r['tco2e'], 3) }}</td>
            <td class="py-2 pl-2 num">Rp {{ number_format($r['rupiah']) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">3. Capaian terhadap Garis Dasar</h3>
    @if($baseline)
      <p class="text-[11.5px] text-stone-600 leading-relaxed">
        Baseline tahun {{ $baseline->tahun }} ditetapkan pada
        <span class="num font-semibold text-cam-ink">{{ number_format($baseline->baseline_gj_ton, 4) }}</span> GJ per ton
        dengan sasaran <span class="num font-semibold text-cam-ink">{{ number_format($baseline->target_gj_ton, 4) }}</span> GJ per ton
        — penurunan yang dituju {{ number_format($baseline->penurunanTarget(), 1) }}%.
        Intensitas periode ini <span class="num font-semibold text-cam-ink">{{ number_format($r['intensitas'], 4) }}</span> GJ per ton,
        atau <strong>{{ $turun >= 0 ? 'turun' : 'naik' }} {{ number_format(abs($turun), 1) }}%</strong> terhadap garis dasar.
        Kemajuan menuju sasaran mencapai
        <span class="num font-semibold text-cam-ink">{{ number_format($baseline->kemajuan($r['intensitas']) * 100, 1) }}%</span>
        dari seluruh jarak baseline ke target.
        @if($baseline->catatan) {{ $baseline->catatan }} @endif
      </p>
    @else
      <p class="text-[11.5px] text-stone-600 leading-relaxed">
        Garis dasar belum ditetapkan untuk periode ini, sehingga capaian belum dapat dinilai.
        Angka intensitas tanpa pembandingnya hanya menjadi bilangan — tidak ada yang dapat
        disebut membaik atau memburuk.
      </p>
    @endif
  </x-lembar>

  {{-- ========== Lembar 2 — Kinerja alat ========== --}}
  <x-lembar :dok="$dok" :halaman="2" :dari="$DARI">

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">4. Pemakaian Bahan Bakar per Kelompok Alat</h3>
    @if(count($perKategori))
      <div class="tabel-scroll mb-5">
        <table class="w-full text-[11.5px] min-w-[440px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold">Kelompok</th>
              <th class="py-1.5 px-2 font-semibold num">Liter</th>
              <th class="py-1.5 px-2 font-semibold num">Jam Operasi</th>
              <th class="py-1.5 px-2 font-semibold num">L/HM</th>
              <th class="py-1.5 pl-2 font-semibold num">GJ</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($perKategori as $k)
              <tr>
                <td class="py-2 pr-2 font-semibold text-cam-ink">{{ $k['nama'] }}</td>
                <td class="py-2 px-2 num">{{ number_format($k['liter']) }}</td>
                <td class="py-2 px-2 num">{{ number_format($k['hm'], 1) }}</td>
                <td class="py-2 px-2 num font-semibold">{{ number_format($k['l_hm'], 2) }}</td>
                <td class="py-2 pl-2 num">{{ number_format($k['gj'], 2) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[11.5px] text-stone-500 mb-5">Tidak ada catatan bahan bakar pada periode ini.</p>
    @endif

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">5. Kinerja Energi per Unit</h3>
    <p class="text-[11.5px] text-stone-600 leading-relaxed mb-3">
      Status dinilai terhadap rata-rata kelompok alat masing-masing, bukan terhadap angka
      mutlak: dump truck dan excavator memang berbeda haus, dan menyandingkannya langsung
      akan selalu menempatkan unit bertenaga besar di puncak daftar boros.
      Batasnya 1,05 kali acuan untuk efisien dan 1,20 kali untuk perlu dipantau.
    </p>

    @if(count($peringkat))
      <div class="tabel-scroll">
        <table class="w-full text-[11.5px] min-w-[520px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold">Unit</th>
              <th class="py-1.5 px-2 font-semibold">Kelompok</th>
              <th class="py-1.5 px-2 font-semibold num">Liter</th>
              <th class="py-1.5 px-2 font-semibold num">L/HM</th>
              <th class="py-1.5 px-2 font-semibold num">Acuan</th>
              <th class="py-1.5 pl-2 font-semibold">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($peringkat as $b)
              <tr>
                <td class="py-2 pr-2 font-semibold text-cam-ink">{{ $b['unit']->kode }}</td>
                <td class="py-2 px-2 text-stone-600">{{ $b['unit']->labelKategori() }}</td>
                <td class="py-2 px-2 num">{{ number_format($b['liter']) }}</td>
                <td class="py-2 px-2 num font-semibold">{{ number_format($b['l_hm'], 2) }}</td>
                <td class="py-2 px-2 num text-stone-500">{{ number_format($b['acuan'], 2) }}</td>
                <td class="py-2 pl-2">{{ $b['status']['label'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[11.5px] text-stone-500">Tidak ada unit yang beroperasi pada periode ini.</p>
    @endif
  </x-lembar>

  {{-- ========== Lembar 3 — Penghematan, emisi, pengesahan ========== --}}
  <x-lembar :dok="$dok" :halaman="3" :dari="$DARI" akhir>

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">6. Program Penghematan Energi</h3>
    @if($peluang->count())
      <div class="tabel-scroll mb-5">
        <table class="w-full text-[11.5px] min-w-[520px]">
          <thead>
            <tr class="border-b border-stone-300 text-left text-stone-500">
              <th class="py-1.5 pr-2 font-semibold">Program</th>
              <th class="py-1.5 px-2 font-semibold">Status</th>
              <th class="py-1.5 px-2 font-semibold num">Solar/bln</th>
              <th class="py-1.5 px-2 font-semibold num">Listrik/bln</th>
              <th class="py-1.5 pl-2 font-semibold num">Nilai/bln</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            @foreach($peluang as $o)
              <tr>
                <td class="py-2 pr-2 text-cam-ink">
                  <span class="font-semibold">{{ $o->judul }}</span>
                  @if($o->area)<span class="block text-stone-500">{{ $o->area }}</span>@endif
                </td>
                <td class="py-2 px-2 text-stone-600">{{ ucfirst($o->status) }}</td>
                <td class="py-2 px-2 num">{{ number_format($o->hemat_liter) }} L</td>
                <td class="py-2 px-2 num">{{ number_format($o->hemat_kwh) }} kWh</td>
                <td class="py-2 pl-2 num">Rp {{ number_format($o->rupiah()) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <p class="text-[11.5px] text-stone-600 leading-relaxed mb-5">
        Dari seluruh program di atas, <span class="num font-semibold text-cam-ink">{{ $hemat['jumlah'] }}</span> sudah berjalan
        atau selesai, dengan penghematan
        <span class="num font-semibold text-cam-ink">{{ number_format($hemat['gj'], 2) }}</span> GJ,
        <span class="num font-semibold text-cam-ink">{{ number_format($hemat['tco2e'], 3) }}</span> tCO₂e, dan
        <span class="num font-semibold text-cam-ink">Rp {{ number_format($hemat['rupiah']) }}</span> tiap bulan.
        Usulan yang belum dikerjakan sengaja tidak ikut dihitung — usulan bukan penghematan.
      </p>
    @else
      <p class="text-[11.5px] text-stone-500 mb-5">Belum ada program penghematan yang dicatat.</p>
    @endif

    <h3 class="text-[12.5px] font-bold text-cam-ink mb-2">7. Emisi Karbon menurut Lingkup</h3>
    @php
      $s1 = Energi::literKeCo2($r['liter']) + Energi::m3KeCo2($r['m3']);
      $s2 = Energi::kwhKeCo2($r['kwh']);
    @endphp
    <table class="w-full text-[11.5px] mb-5">
      <tbody class="divide-y divide-stone-100">
        @foreach ([
          ['Lingkup 1 — pembakaran langsung di lokasi', number_format($s1, 3).' tCO₂e'],
          ['Lingkup 2 — listrik jaringan yang dibeli', number_format($s2, 3).' tCO₂e'],
          ['Total emisi', number_format($s1 + $s2, 3).' tCO₂e'],
          ['Intensitas karbon', ($r['ton'] > 0 ? number_format($r['tco2e'] / $r['ton'], 5) : '0').' tCO₂e per ton'],
        ] as [$k, $v])
          <tr>
            <td class="py-2 pr-3 text-stone-500 w-1/2">{{ $k }}</td>
            <td class="py-2 num font-semibold text-cam-ink">{{ $v }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <p class="text-[11.5px] text-stone-600 leading-relaxed mb-6">
      Solar genset tetap masuk Lingkup 1 meski keluarannya berupa listrik, karena bahan
      bakarnya dibakar sendiri di lokasi. Faktor yang dipakai:
      {{ Energi::TCO2E_PER_LITER * 1000 }} kg CO₂e per liter solar,
      {{ Energi::TCO2E_PER_KWH * 1000 }} kg per kilowatt-jam listrik jaringan, dan
      {{ Energi::TCO2E_PER_M3_GAS * 1000 }} kg per meter kubik gas.
    </p>

    {{-- Pengesahan --}}
    <table class="w-full text-[11.5px] border-collapse mt-8">
      <tbody>
        <tr>
          @foreach (['Disusun oleh', 'Diperiksa oleh', 'Disahkan oleh'] as $peran)
            <td class="border border-stone-300 p-2 w-1/3 align-top">
              <div class="text-center text-stone-500 pb-1 border-b border-stone-200">{{ $peran }}</div>
              <div class="h-16"></div>
              <div class="text-center border-t border-stone-300 pt-1 text-stone-400">Nama dan tanda tangan</div>
            </td>
          @endforeach
        </tr>
        <tr>
          <td colspan="3" class="border border-stone-300 px-2 py-1.5 text-stone-500">
            Diterbitkan {{ now()->translatedFormat('d F Y') }} · {{ $dok['perusahaan'] }}
          </td>
        </tr>
      </tbody>
    </table>
  </x-lembar>

</div>
@endsection
