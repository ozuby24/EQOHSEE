<script setup lang="ts">
/**
 * Fuel Management — pemakaian per kelompok alat, ditutup rekonsiliasi stok.
 */
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import KartuKpi from '../../Components/KartuKpi.vue';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka, ringkas, rupiah } from '../../energi';
import type { HalamanEnergiFuel } from '../../types';

const props = defineProps<HalamanEnergiFuel>();

const terbesarKategori = computed(() =>
  props.perKategori.length ? Math.max(...props.perKategori.map((k) => k.liter)) : 0);
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Fuel Management"
        ket="Bahan bakar adalah pos biaya energi terbesar di tambang. Halaman ini memisahkan
             pemakaian menurut kelompok alat, lalu menutupnya dengan rekonsiliasi terhadap
             penyaluran — karena yang tercatat terpakai dan yang benar-benar keluar tangki
             tidak selalu sama."
        :dari="dari" :sampai="sampai" :rute="tautan.fuel">

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div>
          <div class="stat stat-sm" style="color:#F57C00">{{ angka(r.liter) }}<span class="stat-unit">L</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Solar Terpakai</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#FF9800">{{ angka(r.liter_alat) }}<span class="stat-unit">L</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Alat Berat</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#D9993A">{{ angka(r.liter_genset) }}<span class="stat-unit">L</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Genset</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#E2663A">{{ rupiah(biayaSolar, 2) }}</div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Biaya Solar</div>
        </div>
      </div>
    </KepalaEnergi>

    <section v-if="perKategori.length" class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Pemakaian per Kelompok Alat</h3>
      <p class="text-[11.5px] text-stone-500 mt-1">
        Liter per jam operasi tiap kelompok — inilah acuan status tiap unit di dalamnya.
      </p>

      <div class="space-y-4 mt-5">
        <div v-for="k in perKategori" :key="k.kode">
          <div class="flex items-baseline justify-between gap-3">
            <Link :href="k.url" class="text-[12.5px] font-bold text-cam-ink hover:text-cam-lime-deep transition">
              {{ k.nama }} →
            </Link>
            <span class="num text-[12px] text-stone-500">
              {{ angka(k.liter) }} L
              <span class="text-stone-300 mx-1">·</span>
              <span class="font-bold text-cam-ink">{{ angka(k.l_hm, 1) }} L/HM</span>
            </span>
          </div>
          <div class="mt-1.5 h-2 rounded-full bg-stone-100 overflow-hidden">
            <div class="h-full rounded-full lime-gradient transition-all duration-700"
                 :style="{ width: (terbesarKategori > 0 ? (k.liter / terbesarKategori) * 100 : 0) + '%' }"></div>
          </div>
          <div class="text-[10.5px] text-stone-400 mt-1">{{ angka(k.hm, 1) }} jam operasi · {{ angka(k.gj, 1) }} GJ</div>
        </div>
      </div>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="font-display text-[16px] font-black text-cam-ink">Unit Paling Boros</h3>
          <p class="text-[11.5px] text-stone-500 mt-1">
            Status dihitung terhadap rata-rata kategorinya sendiri, bukan angka mutlak: dump truck
            dan excavator memang berbeda haus.
          </p>
        </div>
        <Link :href="tautan.equipment" class="shrink-0 text-[11.5px] font-bold text-cam-lime-deep hover:underline">
          Semua unit →
        </Link>
      </div>

      <div v-if="peringkat.length" class="overflow-x-auto mt-4 -mx-1">
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
            <tr v-for="b in peringkat" :key="b.id" class="hairline hover:bg-cam-lime-soft/30 transition">
              <td class="py-2.5">
                <Link :href="b.url" class="font-bold text-cam-ink hover:text-cam-lime-deep transition">{{ b.kode }}</Link>
                <div class="text-[10.5px] text-stone-400">{{ b.nama }}</div>
              </td>
              <td class="py-2.5 text-stone-500">{{ b.kategori }}</td>
              <td class="num py-2.5">{{ angka(b.liter) }}</td>
              <td class="num py-2.5">{{ angka(b.hm, 1) }}</td>
              <td class="num py-2.5 font-bold" :style="{ color: b.status.warna }">{{ angka(b.lHm, 2) }}</td>
              <td class="num py-2.5 text-stone-400">{{ angka(b.acuan, 2) }}</td>
              <td class="py-2.5 pl-3">
                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-1 rounded-lg text-white whitespace-nowrap"
                      :style="{ background: b.status.warna }">{{ b.status.label }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada catatan bahan bakar pada rentang ini.</p>
    </section>

    <section class="kartu-lux rounded-2xl p-6">
      <h3 class="font-display text-[16px] font-black text-cam-ink">Rekonsiliasi Bahan Bakar</h3>
      <p class="text-[11.5px] text-stone-500 mt-1 leading-relaxed">
        Yang seharusnya terpakai menurut pergerakan stok, dibanding yang tercatat di lembar harian
        unit. Selisih yang berulang menandakan kebocoran, kesalahan ukur, atau pencatatan yang
        tidak tertib — ketiganya perlu ditelusuri.
      </p>

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-5">
        <KartuKpi label="Menurut Stok" :nilai="angka(recon.disalurkan)" satuan="L" ket="Stok awal + penyaluran − stok akhir" />
        <KartuKpi label="Tercatat Terpakai" :nilai="angka(recon.tercatat)" satuan="L" ket="Jumlah lembar harian unit" warna="#FF9800" />
        <KartuKpi label="Selisih" :nilai="angka(recon.selisih, 1)" satuan="L"
                  :warna="Math.abs(recon.persen) > 3 ? '#E2663A' : '#F57C00'"
                  ket="Positif berarti ada solar yang tidak tercatat pemakaiannya" />
        <KartuKpi label="Selisih Relatif" :nilai="angka(recon.persen, 2) + '%'"
                  :warna="Math.abs(recon.persen) > 3 ? '#E2663A' : '#F57C00'"
                  :ket="Math.abs(recon.persen) > 3 ? 'Di atas 3% — perlu ditelusuri' : 'Dalam batas wajar'" />
      </div>

      <div v-if="recon.baris.length" class="overflow-x-auto mt-5 -mx-1">
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
            <tr v-for="(x, i) in recon.baris" :key="i" class="hairline">
              <td class="py-2.5 font-semibold text-cam-ink">{{ x.tanggal }}</td>
              <td class="num py-2.5">{{ angka(x.stokAwal) }}</td>
              <td class="num py-2.5">{{ angka(x.disalurkan) }}</td>
              <td class="num py-2.5">{{ angka(x.stokAkhir) }}</td>
              <td class="num py-2.5 font-bold text-cam-lime-deep">{{ angka(x.terpakai) }}</td>
              <td class="py-2.5 pl-3 text-stone-400">{{ x.catatan ?? '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-else class="text-[12px] text-stone-400 mt-5">Belum ada catatan penyaluran dan stok pada rentang ini.</p>
    </section>

  </div>
</template>
