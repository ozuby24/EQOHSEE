@extends('layouts.app')
@section('title','Fuel Management')

@section('content')
@php use App\Support\Energi; @endphp

<div class="max-w-5xl mx-auto space-y-5">

  <x-energi.kepala judul="Fuel Management"
      ket="Bahan bakar adalah pos biaya energi terbesar di tambang. Halaman ini memisahkan
           pemakaian menurut kelompok alat, lalu menutupnya dengan rekonsiliasi terhadap
           penyaluran — karena yang tercatat terpakai dan yang benar-benar keluar tangki
           tidak selalu sama."
      :dari="$dari" :sampai="$sampai" :rute="route('energi.fuel')">

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
      @foreach ([
        ['Solar Terpakai', number_format($r['liter']), 'L', '#F57C00'],
        ['Alat Berat', number_format($r['liter_alat']), 'L', '#FF9800'],
        ['Genset', number_format($r['liter_genset']), 'L', '#D9993A'],
        ['Biaya Solar', 'Rp '.Energi::ringkas(Energi::literKeRp($r['liter']), 2), null, '#E2663A'],
      ] as [$l, $v, $s, $w])
        <div>
          <div class="stat stat-sm" style="color:{{ $w }}">{{ $v }}@if($s)<span class="stat-unit">{{ $s }}</span>@endif</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">{{ $l }}</div>
        </div>
      @endforeach
    </div>
  </x-energi.kepala>

  {{-- Per kategori --}}
  @if(count($perKategori))
    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Pemakaian per Kelompok Alat</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">Liter per jam operasi tiap kelompok — inilah acuan status tiap unit di dalamnya.</p>

      @php $terbesar = max(array_column($perKategori, 'liter')); @endphp
      <div class="space-y-4 mt-5">
        @foreach($perKategori as $kode => $k)
          <div>
            <div class="flex items-baseline justify-between gap-3">
              <a href="{{ route('energi.equipment', ['kategori'=>$kode,'dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
                 class="text-[12.5px] font-bold text-cam-ink hover:text-cam-lime-deep transition">{{ $k['nama'] }} →</a>
              <span class="num text-[12px] text-stone-500">
                {{ number_format($k['liter']) }} L
                <span class="text-stone-300 mx-1">·</span>
                <span class="font-bold text-cam-ink">{{ number_format($k['l_hm'], 1) }} L/HM</span>
              </span>
            </div>
            <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
              <div class="h-full rounded-full lime-gradient transition-all duration-700"
                   style="width:{{ $terbesar > 0 ? $k['liter'] / $terbesar * 100 : 0 }}%"></div>
            </div>
            <div class="text-[10.5px] text-stone-400 mt-1">{{ number_format($k['hm'], 1) }} jam operasi · {{ number_format($k['gj'], 1) }} GJ</div>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  {{-- Peringkat unit --}}
  <section class="kartu-lux rounded-2xl p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="font-display text-[16px] font-black text-cam-ink">Unit Paling Boros</h3>
        <p class="text-[11.5px] text-stone-500 mt-1">
          Status dihitung terhadap rata-rata kategorinya sendiri, bukan angka mutlak: dump truck
          dan excavator memang berbeda haus.
        </p>
      </div>
      <a href="{{ route('energi.equipment', ['dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
         class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">Semua unit →</a>
    </div>

    @if(count($peringkat))
      <div class="overflow-x-auto mt-4 -mx-1">
        <table class="w-full text-[12.5px] min-w-[620px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Unit</th>
              <th class="text-left py-2.5">Kategori</th>
              <th class="num py-2.5">Liter</th>
              <th class="num py-2.5">HM</th>
              <th class="num py-2.5">L/HM</th>
              <th class="num py-2.5">Acuan</th>
              <th class="text-left py-2.5 pl-3">Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($peringkat as $b)
              <tr class="hairline hover:bg-cam-lime-soft/30 transition">
                <td class="py-2.5">
                  <a href="{{ route('energi.equipment.show', [$b['unit'],'dari'=>$dari->format('Y-m-d'),'sampai'=>$sampai->format('Y-m-d')]) }}"
                     class="font-bold text-cam-ink hover:text-cam-lime-deep transition">{{ $b['unit']->kode }}</a>
                  <div class="text-[10.5px] text-stone-400">{{ $b['unit']->nama }}</div>
                </td>
                <td class="py-2.5 text-stone-500">{{ $b['unit']->labelKategori() }}</td>
                <td class="num py-2.5">{{ number_format($b['liter']) }}</td>
                <td class="num py-2.5">{{ number_format($b['hm'], 1) }}</td>
                <td class="num py-2.5 font-bold" style="color:{{ $b['status']['warna'] }}">{{ number_format($b['l_hm'], 2) }}</td>
                <td class="num py-2.5 text-stone-400">{{ number_format($b['acuan'], 2) }}</td>
                <td class="py-2.5 pl-3">
                  <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg text-white whitespace-nowrap"
                        style="background:{{ $b['status']['warna'] }}">{{ $b['status']['label'] }}</span>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-6">Belum ada catatan bahan bakar pada rentang ini.</p>
    @endif
  </section>

  {{-- Rekonsiliasi --}}
  <section class="kartu-lux rounded-2xl p-6">
    <h3 class="font-display text-[16px] font-black text-cam-ink">Rekonsiliasi Bahan Bakar</h3>
    <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
      Yang seharusnya terpakai menurut pergerakan stok, dibanding yang tercatat di lembar harian
      unit. Selisih yang berulang menandakan kebocoran, kesalahan ukur, atau pencatatan yang
      tidak tertib — ketiganya perlu ditelusuri.
    </p>

    <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
      <x-kpi label="Menurut Stok" :nilai="number_format($recon['disalurkan'])" satuan="L" ket="Stok awal + penyaluran − stok akhir" />
      <x-kpi label="Tercatat Terpakai" :nilai="number_format($recon['tercatat'])" satuan="L" ket="Jumlah lembar harian unit" warna="#FF9800" />
      <x-kpi label="Selisih" :nilai="number_format($recon['selisih'], 1)" satuan="L"
             :warna="abs($recon['persen']) > 3 ? '#E2663A' : '#F57C00'" ket="Positif berarti ada solar yang tidak tercatat pemakaiannya" />
      <x-kpi label="Selisih Relatif" :nilai="number_format($recon['persen'], 2).'%'"
             :warna="abs($recon['persen']) > 3 ? '#E2663A' : '#F57C00'"
             :ket="abs($recon['persen']) > 3 ? 'Di atas 3% — perlu ditelusuri' : 'Dalam batas wajar'" />
    </div>

    @if($recon['baris']->count())
      <div class="overflow-x-auto mt-5 -mx-1">
        <table class="w-full text-[12.5px] min-w-[520px]">
          <thead>
            <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
              <th class="text-left py-2.5">Tanggal</th>
              <th class="num py-2.5">Stok Awal</th>
              <th class="num py-2.5">Disalurkan</th>
              <th class="num py-2.5">Stok Akhir</th>
              <th class="num py-2.5">Terpakai</th>
              <th class="text-left py-2.5 pl-3">Catatan</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recon['baris'] as $x)
              <tr class="hairline">
                <td class="py-2.5 font-semibold text-cam-ink">{{ $x->tanggal->translatedFormat('d M Y') }}</td>
                <td class="num py-2.5">{{ number_format($x->stok_awal_liter) }}</td>
                <td class="num py-2.5">{{ number_format($x->disalurkan_liter) }}</td>
                <td class="num py-2.5">{{ number_format($x->stok_akhir_liter) }}</td>
                <td class="num py-2.5 font-bold text-cam-lime-deep">{{ number_format($x->terpakaiMenurutStok()) }}</td>
                <td class="py-2.5 pl-3 text-stone-400">{{ $x->catatan ?: '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p class="text-[12px] text-stone-400 mt-5">Belum ada catatan penyaluran dan stok pada rentang ini.</p>
    @endif
  </section>

</div>
@endsection
