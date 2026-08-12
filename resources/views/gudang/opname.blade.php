@extends('layouts.app')
@section('title','Stok Opname')
@section('subjudul','Hitung fisik dan koreksi saldo buku')

@section('content')
@php use App\Support\Gudang; @endphp

<div class="max-w-[1200px] mx-auto space-y-5">

  @include('gudang.partials.pesan')

  @can('admin')
    <form method="POST" action="{{ route('gudang.opname.simpan') }}"
          class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden"
          x-data="opname()">
      @csrf

      <div class="px-6 py-5 border-b border-stone-100 flex flex-wrap items-end gap-4">
        <div>
          <label class="block text-[12px] font-semibold text-[#0F1720] mb-1.5">Tanggal Opname</label>
          <input name="tanggal" type="date" required
                 value="{{ old('tanggal', \App\Support\Waktu::kini()->toDateString()) }}"
                 class="rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
        <div class="flex-1 min-w-[220px]">
          <label class="block text-[12px] font-semibold text-[#0F1720] mb-1.5">Keterangan</label>
          <input name="keterangan" placeholder="Opname bulanan, pemeriksaan mendadak, dll."
                 class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px]">
        </div>
      </div>

      <div class="px-6 py-3.5 bg-stone-50 border-b border-stone-100">
        <p class="text-[12px] text-stone-600">
          Isi <b>jumlah fisik</b> hanya untuk barang yang dihitung. Baris yang dikosongkan
          tidak diubah, dan baris yang jumlahnya sama dengan stok buku tidak dicatat —
          opname tanpa selisih tidak mengubah apa pun.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-[12.5px]">
          <thead>
            <tr class="text-left text-stone-500 border-b border-stone-100">
              <th class="py-3 px-4 font-semibold">Barang</th>
              <th class="py-3 px-3 font-semibold">Lokasi</th>
              <th class="py-3 px-3 font-semibold text-right">Stok Buku</th>
              <th class="py-3 px-3 font-semibold text-right w-40">Jumlah Fisik</th>
              <th class="py-3 px-4 font-semibold text-right">Selisih</th>
            </tr>
          </thead>
          <tbody>
            @foreach($barang as $b)
              @php $stok = Gudang::stok($b); @endphp
              <tr class="border-b border-stone-50 last:border-0">
                <td class="py-2.5 px-4">
                  <span class="font-semibold text-[#0F1720]">{{ $b->nama }}</span>
                  <span class="block text-[11px] text-stone-400">{{ $b->kode }}</span>
                </td>
                <td class="py-2.5 px-3 text-stone-500">{{ $b->lokasi?->nama ?? '—' }}</td>
                <td class="py-2.5 px-3 text-right font-bold tabular-nums text-[#0F1720]"
                    data-buku="{{ $stok }}">
                  {{ rtrim(rtrim(number_format($stok, 2, ',', '.'), '0'), ',') }}
                  <span class="text-stone-400 font-normal">{{ $b->satuan }}</span>
                </td>
                <td class="py-2.5 px-3">
                  <input type="number" step="0.01" min="0" name="fisik[{{ $b->id }}]"
                         data-buku="{{ $stok }}" @input="hitung($event)"
                         class="w-full rounded-lg border border-stone-200 px-3 py-2 text-[12.5px] text-right
                                focus:border-[color:var(--eq-aksen,#F57C00)] focus:ring-0">
                </td>
                <td class="py-2.5 px-4 text-right font-bold tabular-nums text-stone-300" data-selisih>—</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="px-6 py-4 bg-stone-50 border-t border-stone-100 flex items-center justify-between gap-3 flex-wrap">
        <p class="text-[12px] text-stone-500">
          <b class="text-[#0F1720]" x-text="berselisih"></b> baris berselisih dari
          <b class="text-[#0F1720]" x-text="terisi"></b> yang dihitung
        </p>
        <button class="eq-btn-utama" style="flex:none;padding:10px 22px">Simpan Opname</button>
      </div>
    </form>
  @endcan

  {{-- ══════════ RIWAYAT OPNAME ══════════ --}}
  <section class="eq-panel">
    <div class="eq-panel-kepala"><h3>Opname Terakhir</h3></div>

    @if($lalu->isEmpty())
      <div class="eq-kosong">
        <strong>Belum pernah ada opname</strong>
        <p>Hasil hitung fisik yang berselisih akan tercatat di sini.</p>
      </div>
    @else
      <ul class="space-y-1">
        @foreach($lalu as $m)
          @include('gudang.partials.baris-mutasi', ['m' => $m])
        @endforeach
      </ul>
    @endif
  </section>

</div>

@push('scripts')
<script>
  /* Selisih dihitung di peramban hanya sebagai bantuan baca. Yang
     menentukan tetap server: nilai stok buku dapat berubah oleh mutasi
     orang lain sementara halaman ini terbuka, dan angka di layar tidak
     boleh dipercaya sebagai dasar penyimpanan. */
  function opname(){
    return {
      terisi: 0,
      berselisih: 0,

      hitung(e){
        const input = e.target;
        const buku  = parseFloat(input.dataset.buku || '0');
        const sel   = input.closest('tr').querySelector('[data-selisih]');
        const isi   = input.value.trim();

        if (isi === '') {
          sel.textContent = '—';
          sel.className = 'py-2.5 px-4 text-right font-bold tabular-nums text-stone-300';
        } else {
          const beda = Math.round((parseFloat(isi) - buku) * 100) / 100;
          sel.textContent = beda === 0 ? 'cocok' : (beda > 0 ? '+' : '') + beda.toLocaleString('id-ID');
          sel.className = 'py-2.5 px-4 text-right font-bold tabular-nums ' +
            (beda === 0 ? 'text-stone-400' : (beda > 0 ? 'text-[#4A8E2C]' : 'text-[#C03A3A]'));
        }

        this.ringkas();
      },

      ringkas(){
        let terisi = 0, beda = 0;
        document.querySelectorAll('input[name^="fisik["]').forEach(function(i){
          if (i.value.trim() === '') return;
          terisi++;
          if (Math.abs(parseFloat(i.value) - parseFloat(i.dataset.buku || '0')) >= 0.005) beda++;
        });
        this.terisi = terisi;
        this.berselisih = beda;
      },
    };
  }
</script>
@endpush
@endsection
