@extends('layouts.app')
@section('title','Engineering Tools')

@section('content')
@php use App\Support\Engineering as E; $a = E::ringkasArmada(); @endphp

<div class="max-w-6xl mx-auto space-y-5">

  <section class="kartu-lux rounded-2xl p-6">
    <h2 class="font-display text-[20px] font-black text-cam-ink">Engineering Tools</h2>
    <p class="text-[12.5px] text-stone-500 mt-1.5 leading-relaxed max-w-3xl">
      Alat hitung yang dipakai sehari-hari di departemen engineering. Seluruh hitungan berjalan di
      peramban; tidak ada angka yang dikirim ke mana pun maupun disimpan.
    </p>
    <div class="rounded-xl bg-cam-coral-soft border border-cam-coral/25 px-4 py-3 mt-5 text-[12px] leading-relaxed text-cam-ink">
      <strong class="text-cam-coral-dark">Engineering reference only.</strong>
      Verify calculations against applicable company procedures and regulations.
    </div>
  </section>

  {{-- ============ Alat hitung sederhana ============ --}}
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">

    @php
      // Tiap alat: kunci, judul, rumus, dua isian, satuan hasil, desimal,
      // dan ungkapan JavaScript-nya. Dijadikan data supaya kartunya
      // digambar satu kali, bukan disalin enam kali.
      $alat = [
        ['fuel', 'Fuel Consumption', 'Fuel Used ÷ Operating Hours',
         ['Fuel Used (Liter)', 'liter', 23180], ['Operating Hours', 'jam', 604],
         'L/jam', 2, 'jam > 0 ? liter / jam : null',
         'Rata-rata armada saat ini '.number_format($a['fuelRate'],1).' L/jam.'],

        ['rasio', 'Fuel Ratio', 'Fuel Consumption ÷ Production',
         ['Fuel Consumption (L)', 'liter', 15380], ['Production (Ton)', 'ton', 12450],
         'L/ton', 4, 'ton > 0 ? liter / ton : null',
         'Setara '.number_format(E::GJ_PER_LITER, 4).' GJ per liter bila dibawa ke gigajoule.'],

        ['intensitas', 'Energy Intensity', 'Total Energy ÷ Production',
         ['Total Energy (GJ)', 'gj', 629], ['Production (Ton)', 'ton', 12450],
         'GJ/ton', 5, 'ton > 0 ? gj / ton : null',
         'Baseline tahun lalu '.number_format(E::BASELINE_GJ_TON, 4).' GJ/ton.'],

        ['availability', 'Availability', '(Scheduled − Downtime) ÷ Scheduled × 100',
         ['Scheduled Hours', 'terjadwal', 720], ['Downtime Hours', 'downtime', 62],
         '%', 2, 'terjadwal > 0 && downtime <= terjadwal ? (terjadwal - downtime) / terjadwal * 100 : null',
         'Downtime tidak boleh melebihi jam terjadwal.'],

        ['utilisasi', 'Utilization', 'Operating Hours ÷ Available Hours × 100',
         ['Operating Hours', 'operasi', 604], ['Available Hours', 'tersedia', 658],
         '%', 2, 'tersedia > 0 && operasi <= tersedia ? operasi / tersedia * 100 : null',
         'Sisanya adalah waktu alat siap tetapi tidak dioperasikan.'],

        ['sr', 'Stripping Ratio', 'Overburden (BCM) ÷ Batu bara (Ton)',
         ['Overburden (BCM)', 'bcm', 97600], ['Batu bara (Ton)', 'ton', 12450],
         'BCM/ton', 2, 'ton > 0 ? bcm / ton : null',
         'Semakin tinggi, semakin banyak tanah penutup per ton batu bara.'],
      ];
    @endphp

    @foreach($alat as [$id, $judul, $rumus, $isianA, $isianB, $satuan, $desimal, $ungkapan, $catatan])
      <article class="kartu-lux rounded-2xl p-5 flex flex-col"
               x-data="{ {{ $isianA[1] }}: {{ $isianA[2] }}, {{ $isianB[1] }}: {{ $isianB[2] }},
                          get hasil() {
                            const {{ $isianA[1] }} = Number(this.{{ $isianA[1] }});
                            const {{ $isianB[1] }} = Number(this.{{ $isianB[1] }});
                            if (!Number.isFinite({{ $isianA[1] }}) || !Number.isFinite({{ $isianB[1] }})) return null;
                            return {{ $ungkapan }};
                          },
                          get teks() {
                            return this.hasil === null ? '—'
                              : this.hasil.toLocaleString('id-ID', { minimumFractionDigits: {{ $desimal }}, maximumFractionDigits: {{ $desimal }} });
                          } }">
        <h3 class="text-[13.5px] font-bold text-cam-ink">{{ $judul }}</h3>
        <div class="text-[11px] num text-stone-500 bg-stone-50 border border-stone-200 rounded-lg px-3 py-2 mt-3 overflow-x-auto whitespace-nowrap">
          {{ $rumus }}
        </div>

        <div class="grid grid-cols-2 gap-3 mt-4">
          @foreach ([$isianA, $isianB] as [$label, $kunci, $awal])
            <div>
              <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1"
                     for="{{ $id }}-{{ $kunci }}">{{ $label }}</label>
              <input type="number" step="any" min="0" inputmode="decimal" id="{{ $id }}-{{ $kunci }}"
                     x-model.number="{{ $kunci }}"
                     class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] num transition">
            </div>
          @endforeach
        </div>

        <div class="mt-auto pt-4">
          <div class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Hasil</div>
          <div class="stat mt-1" :class="hasil === null ? 'text-stone-300' : 'text-cam-lime-deep'">
            <span x-text="teks"></span><span class="stat-unit">{{ $satuan }}</span>
          </div>
          <p class="text-[11px] text-stone-400 mt-2 leading-relaxed"
             x-text="hasil === null ? 'Periksa kembali angkanya — pembagi harus lebih dari nol.' : @js($catatan)"></p>
        </div>
      </article>
    @endforeach
  </div>

  {{-- ============ Kalkulator pajanan bising ============ --}}
  <section class="kartu-lux rounded-2xl p-6"
           x-data="bising()">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="max-w-2xl">
        <h3 class="font-display text-[16px] font-black text-cam-ink">TWA Noise Calculator</h3>
        <p class="text-[11.5px] text-stone-500 mt-1.5 leading-relaxed">
          Kriteria {{ E::NAB_DBA }} dBA untuk 8 jam dengan laju pertukaran {{ E::LAJU_TUKAR_DB }} dB —
          dasar yang dipakai Permenaker No. 5 Tahun 2018 dan ISO 1999. Beberapa baris pajanan
          <strong>dijumlahkan dosisnya</strong>, bukan dirata-rata tingkatnya: satu jam pada 100 dBA
          jauh lebih berat daripada delapan jam pada 86 dBA, dan perataan biasa menyembunyikan itu.
        </p>
      </div>
      <div class="text-[11px] num text-stone-500 bg-stone-50 border border-stone-200 rounded-lg px-3 py-2 shrink-0">
        T = 8 ÷ 2^((L − 85) ÷ 3)<br>
        Dosis = 100 × Σ(C ÷ T)<br>
        TWA = 85 + 3 × log₂(Dosis ÷ 100)
      </div>
    </div>

    <div class="mt-5 space-y-2.5">
      <template x-for="(b, i) in baris" :key="i">
        <div class="grid grid-cols-[1fr_1fr_auto] gap-2.5 items-end">
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Tingkat (dBA)</label>
            <input type="number" step="0.1" min="0" max="140" inputmode="decimal" x-model.number="b.db"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] num transition">
          </div>
          <div>
            <label class="block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1">Durasi (jam)</label>
            <input type="number" step="0.25" min="0" max="24" inputmode="decimal" x-model.number="b.jam"
                   class="ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] num transition">
          </div>
          <button type="button" @click="hapus(i)"
                  class="w-10 h-10 rounded-xl border border-stone-200 grid place-items-center text-stone-400
                         hover:border-cam-coral hover:text-cam-coral transition"
                  :aria-label="'Hapus baris pajanan ' + (i + 1)">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
              <path stroke-linecap="round" d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/>
            </svg>
          </button>
        </div>
      </template>
    </div>

    <button type="button" @click="tambah()"
            class="mt-3 inline-flex items-center gap-2 rounded-xl border border-stone-200 px-4 py-2
                   text-[12px] font-bold text-stone-600 hover:border-cam-lime hover:text-cam-lime-deep transition">
      <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
        <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
      </svg>
      Tambah pajanan
    </button>

    <div class="grid gap-4 sm:grid-cols-3 mt-6 pt-6 hairline border-b-0">
      <div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-stone-500">TWA 8 jam</div>
        <div class="stat stat-lg mt-1" :class="lewat ? 'text-cam-coral' : 'text-cam-lime-deep'">
          <span x-text="twaTeks"></span><span class="stat-unit">dBA</span>
        </div>
      </div>
      <div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Dosis</div>
        <div class="stat stat-lg mt-1" :class="lewat ? 'text-cam-coral' : 'text-cam-lime-deep'">
          <span x-text="dosisTeks"></span><span class="stat-unit">%</span>
        </div>
      </div>
      <div>
        <div class="text-[10px] font-bold uppercase tracking-wide text-stone-500">Total Durasi</div>
        <div class="stat stat-lg text-cam-ink mt-1"><span x-text="jamTeks"></span><span class="stat-unit">jam</span></div>
      </div>
    </div>

    <p class="text-[12px] mt-4 leading-relaxed" :class="lewat ? 'text-cam-coral font-semibold' : 'text-stone-500'"
       x-text="pesan"></p>
  </section>

</div>

@push('scripts')
<script>
/**
 * Pajanan bising — dosis dan TWA.
 *
 * Rumusnya kembar dengan App\Support\Engineering::bising(), dan itu
 * disengaja: hitungan di peramban memberi hasil seketika saat angka
 * diketik, sementara yang di PHP dipakai pengujian. Uji itulah yang
 * menjaga keduanya tidak berselisih.
 */
function bising() {
  const NAB = {{ App\Support\Engineering::NAB_DBA }};
  const LAJU = {{ App\Support\Engineering::LAJU_TUKAR_DB }};

  return {
    baris: [{ db: 92, jam: 4 }, { db: 85, jam: 3 }],

    tambah() { this.baris.push({ db: 88, jam: 2 }); },
    hapus(i) { if (this.baris.length > 1) this.baris.splice(i, 1); },

    get hitung() {
      let dosis = 0, jam = 0, sah = true;
      for (const b of this.baris) {
        const db = Number(b.db), c = Number(b.jam);
        if (!Number.isFinite(db) || !Number.isFinite(c) || db < 0 || c < 0) { sah = false; continue; }
        jam += c;
        if (c === 0) continue;
        dosis += c / (8 / Math.pow(2, (db - NAB) / LAJU));
      }
      dosis *= 100;
      return {
        sah, jam, dosis,
        twa: dosis > 0 ? NAB + LAJU * (Math.log(dosis / 100) / Math.log(2)) : null,
      };
    },

    angka(v, d) {
      return v.toLocaleString('id-ID', { minimumFractionDigits: d, maximumFractionDigits: d });
    },

    get lewat()     { return this.hitung.dosis > 100; },
    get twaTeks()   { const h = this.hitung; return h.twa === null || !h.sah ? '—' : this.angka(h.twa, 1); },
    get dosisTeks() { const h = this.hitung; return h.dosis > 0 && h.sah ? this.angka(h.dosis, 0) : '—'; },
    get jamTeks()   { return this.angka(this.hitung.jam, 2); },

    get pesan() {
      const h = this.hitung;
      if (!h.sah) return 'Isi tingkat dan durasi dengan angka yang wajar.';
      if (h.dosis <= 0) return 'Belum ada pajanan yang berdurasi.';
      if (h.jam > 24) return `Total durasi ${this.angka(h.jam, 2)} jam melebihi satu hari kerja — periksa kembali datanya.`;
      return h.dosis > 100
        ? `MELEBIHI NAB. Dosis ${this.angka(h.dosis, 0)}% terhadap batas harian dan TWA ${this.angka(h.twa, 1)} dBA `
          + `di atas ${NAB} dBA. Perlu pengendalian teknis, pembatasan durasi, atau alat pelindung pendengaran yang memadai.`
        : `Dalam batas. Dosis ${this.angka(h.dosis, 0)}% terhadap batas harian, TWA ${this.angka(h.twa, 1)} dBA `
          + `terhadap NAB ${NAB} dBA selama 8 jam (Permenaker No. 5 Tahun 2018, laju pertukaran ${LAJU} dB).`;
    },
  };
}
</script>
@endpush
@endsection
