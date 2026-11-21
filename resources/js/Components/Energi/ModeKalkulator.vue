<script setup lang="ts">
/*
  Kalkulator energi & emisi — dihitung di server (cacat D2).

  Versi sebelumnya menyimpan 0,0358 GJ/L dan 0,0036 GJ/kWh di berkas ini
  dan tidak menghitung CO₂ sama sekali; angkanya berbeda dengan laporan
  begitu faktor diperbarui. Sekarang setiap baris dikirim ke
  /energi/api/kalkulator, yang memakai registri faktor dan campuran FAME
  yang berlaku pada tanggal yang dipilih — persis jalur laporan.
*/
import { reactive, ref } from 'vue';
import Kartu from './Kartu.vue';
import KartuAngka from './KartuAngka.vue';
import { json, n, rp } from '../../energi';

const props = defineProps<{ kode: Record<string, { label: string; satuan: string }>; kategori: Record<string, string>; terpasang: boolean; tautan: Record<string, string> }>();

const isian = reactive({
  tanggal: new Date().toISOString().slice(0, 10), ton: '' as any,
  butir: [{ kode: 'solar', jumlah: '' as any, kategori_ipcc: '', persen_fame: '' as any, harga: '' as any }],
});
const hasil = ref<any | null>(null);
const galat = ref('');
const sibuk = ref(false);

const tambah = () => isian.butir.push({ kode: 'listrik_pln', jumlah: '', kategori_ipcc: '', persen_fame: '', harga: '' });
const buang = (i: number) => isian.butir.splice(i, 1);

async function hitung() {
  galat.value = ''; sibuk.value = true;
  try {
    hasil.value = await json(props.tautan.hitung, {
      tanggal: isian.tanggal, ton: isian.ton === '' ? null : Number(isian.ton),
      butir: isian.butir.filter(b => b.jumlah !== '').map(b => ({
        kode: b.kode, jumlah: Number(b.jumlah), kategori_ipcc: b.kategori_ipcc || null,
        persen_fame: b.persen_fame === '' ? null : Number(b.persen_fame), harga: b.harga === '' ? null : Number(b.harga),
      })),
    });
  } catch (e: any) {
    galat.value = e.message;
  } finally {
    sibuk.value = false;
  }
}
</script>

<template>
  <div class="space-y-4">
    <Kartu judul="Kalkulator energi & emisi" ket="Hitungan memakai registri faktor dan campuran FAME yang berlaku pada tanggal yang dipilih — hasilnya identik dengan laporan. Tidak ada data yang disimpan.">
      <p v-if="!props.terpasang" class="rounded-lg bg-amber-50 text-amber-800 text-[12px] p-3 mb-3">Paket faktor belum dipasang (php artisan energi:pasang) — seluruh baris akan ditandai "faktor tidak tersedia".</p>
      <form class="space-y-3" @submit.prevent="hitung">
        <div class="grid gap-3 sm:grid-cols-3">
          <label class="text-[11px] font-semibold text-stone-500">Tanggal aktivitas<input v-model="isian.tanggal" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Produksi (ton, untuk intensitas)<input v-model="isian.ton" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        </div>
        <div v-for="(b, i) in isian.butir" :key="i" class="grid gap-2 sm:grid-cols-6 items-end rounded-xl bg-stone-50 p-3">
          <label class="text-[11px] font-semibold text-stone-500 sm:col-span-2">Sumber
            <select v-model="b.kode" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
              <option v-for="[kode, k] in Object.entries((props.kode ?? {}) as Record<string, any>)" :key="kode" :value="kode">{{ k.label }}</option>
            </select>
          </label>
          <label class="text-[11px] font-semibold text-stone-500">Jumlah ({{ props.kode[b.kode]?.satuan }})<input v-model="b.jumlah" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label class="text-[11px] font-semibold text-stone-500">Kategori IPCC
            <select v-model="b.kategori_ipcc" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
              <option value="">Bawaan</option>
              <option v-for="[k, l] in Object.entries((props.kategori ?? {}) as Record<string, any>)" :key="k" :value="k">{{ l }}</option>
            </select>
          </label>
          <label v-if="b.kode === 'solar'" class="text-[11px] font-semibold text-stone-500">FAME (%)<input v-model="b.persen_fame" type="number" step="any" min="0" max="100" placeholder="campuran berlaku" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <label v-else class="text-[11px] font-semibold text-stone-500">Harga/satuan (opsional)<input v-model="b.harga" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
          <button type="button" class="eq-btn-lain" :disabled="isian.butir.length === 1" @click="buang(i)">Hapus baris</button>
        </div>
        <div class="flex gap-2">
          <button type="button" class="eq-btn-lain" @click="tambah">+ Sumber lain</button>
          <button class="eq-btn-utama" :disabled="sibuk">{{ sibuk ? 'Menghitung…' : 'Hitung' }}</button>
        </div>
        <p v-if="galat" class="text-[12px] text-red-600">{{ galat }}</p>
      </form>
    </Kartu>

    <template v-if="hasil">
      <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <KartuAngka label="Total energi" :nilai="hasil.total.gj" satuan="GJ" :catatan="`${n(hasil.total.toe, 2)} TOE`" />
        <KartuAngka label="Emisi (CO₂e fosil)" :nilai="hasil.total.co2e_t" satuan="tCO₂e" :desimal="3" :catatan="`GWP ${hasil.gwp}`" />
        <KartuAngka label="CO₂ biogenik (terpisah)" :nilai="hasil.total.co2_bio_t" satuan="tCO₂" :desimal="3" />
        <KartuAngka label="Biaya" :nilai="hasil.total.rupiah" satuan="Rp" :desimal="0" />
        <KartuAngka label="Intensitas" :nilai="hasil.total.intensitas" satuan="GJ/ton" :desimal="4" :catatan="hasil.total.intensitas_emisi !== null ? `${n(hasil.total.intensitas_emisi, 5)} tCO₂e/ton` : null" />
      </section>
      <Kartu judul="Rincian per sumber" ket="Faktor yang dipakai beserta sumber dokumennya — sama dengan yang dibekukan pada snapshot laporan.">
        <div v-for="(b, i) in hasil.butir" :key="i" class="border-b border-stone-100 py-3 last:border-0">
          <p class="text-[12.5px] font-semibold">{{ b.label }} — {{ n(b.jumlah, 2) }} {{ b.satuan }}
            <span class="text-stone-400 font-normal">· Scope {{ b.scope || 'EBT' }}<template v-if="b.kode === 'solar'"> · FAME {{ n(b.persen_fame, 0) }}%</template></span></p>
          <p class="text-[11.5px] text-stone-600">{{ n(b.gj, 3) }} GJ · CO₂ {{ n(b.co2_fosil_t, 4) }} t · CH₄ {{ n(b.ch4_t, 6) }} t · N₂O {{ n(b.n2o_t, 6) }} t · <b>{{ n(b.co2e_t, 4) }} tCO₂e</b> · {{ rp(b.rupiah) }}</p>
          <p v-if="b.kurang.length" class="text-[11px] text-red-600 mt-1">Faktor tidak tersedia: {{ b.kurang.join(', ') }}</p>
          <p v-for="c in b.catatan" :key="c" class="text-[11px] text-amber-700 mt-1">{{ c }}</p>
          <ul class="mt-1 text-[10.5px] text-stone-500 space-y-0.5">
            <li v-for="f in Object.values(b.faktor ?? {}) as any[]" :key="f.id">
              {{ f.kode_energi }}/{{ f.parameter }}<template v-if="f.kategori_ipcc"> ({{ f.kategori_ipcc }})</template> = {{ f.nilai }} {{ f.satuan }} — {{ f.sumber_dokumen }}<template v-if="f.perlu_cocok"> · <b class="text-amber-700">perlu dicocokkan</b></template>
            </li>
          </ul>
        </div>
      </Kartu>
    </template>
  </div>
</template>
