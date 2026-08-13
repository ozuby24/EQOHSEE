<script setup lang="ts">
/**
 * Energy Saving Opportunities.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka, rupiah } from '../../energi';
import type { HalamanEnergiHemat, PeluangHemat } from '../../types';

const props = defineProps<HalamanEnergiHemat>();

const form = useForm({
  judul: '',
  area: '',
  status: 'usulan',
  hemat_liter: '',
  hemat_kwh: '',
  penanggung_jawab: '',
  target_selesai: '',
  uraian: '',
});

function simpan() {
  form.post(props.tautan.simpan, { preserveScroll: true, onSuccess: () => form.reset() });
}

function ubahStatus(o: PeluangHemat, status: string) {
  router.put(o.urlUbah, { status }, { preserveScroll: true });
}

function hapus(o: PeluangHemat) {
  if (!confirm(`Hapus peluang "${o.judul}"?`)) return;
  router.delete(o.urlHapus, { preserveScroll: true });
}

const warnaStatus: Record<string, string> = {
  selesai: '#F57C00', berjalan: '#FF9800', disetujui: '#D9993A', ditolak: '#9AA3AE',
};

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Energy Saving Opportunities"
        ket="Peluang penghematan dicatat pada satuan asalnya — liter dan kilowatt-jam — bukan
             dalam rupiah. Rupiah dan karbonnya dihitung ulang saat dibaca, jadi perubahan harga
             bahan bakar tidak membuat angka lama menjadi keliru.">

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div>
          <div class="stat stat-sm" style="color:#F57C00">{{ daftar.length }}<span class="stat-unit">usulan</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Total Peluang</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#FF9800">{{ terwujud.jumlah }}<span class="stat-unit">program</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Sudah Berjalan</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#D9993A">{{ rupiah(potensi.rupiah, 2) }}<span class="stat-unit">/bulan</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Potensi Penuh</div>
        </div>
        <div>
          <div class="stat stat-sm" style="color:#E2663A">{{ rupiah(terwujud.rupiah, 2) }}<span class="stat-unit">/bulan</span></div>
          <div class="text-[10.5px] text-stone-400 mt-1.5">Sudah Terwujud</div>
        </div>
      </div>
    </KepalaEnergi>

    <div class="grid gap-4 lg:grid-cols-5">

      <section class="kartu-lux rounded-2xl p-6 lg:col-span-2">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Catat Peluang</h3>

        <form class="space-y-3.5 mt-5" @submit.prevent="simpan">
          <div v-if="Object.keys(form.errors).length"
               class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12px]">
            <ul class="space-y-0.5">
              <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
            </ul>
          </div>

          <div>
            <label :class="label">Judul</label>
            <input v-model="form.judul" required maxlength="200"
                   placeholder="Batasi idle dump truck di area loading" :class="isian">
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Area</label>
              <input v-model="form.area" placeholder="Pit / Workshop / Camp" :class="isian">
            </div>
            <div>
              <label :class="label">Status</label>
              <select v-model="form.status" :class="isian">
                <option v-for="s in opsi.status" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Hemat Solar (L/bulan)</label>
              <input v-model="form.hemat_liter" type="number" step="0.01" :class="isian">
            </div>
            <div>
              <label :class="label">Hemat Listrik (kWh/bulan)</label>
              <input v-model="form.hemat_kwh" type="number" step="0.01" :class="isian">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Penanggung Jawab</label>
              <input v-model="form.penanggung_jawab" :class="isian">
            </div>
            <div>
              <label :class="label">Target Selesai</label>
              <input v-model="form.target_selesai" type="date" :class="isian">
            </div>
          </div>

          <div>
            <label :class="label">Uraian</label>
            <textarea v-model="form.uraian" rows="3"
                      placeholder="Apa yang dikerjakan dan dari mana perkiraan penghematannya." :class="isian"></textarea>
          </div>

          <button type="submit" :disabled="form.processing"
                  class="w-full rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition disabled:opacity-40">
            {{ form.processing ? 'Menyimpan…' : 'Simpan Peluang' }}
          </button>
        </form>
      </section>

      <section class="kartu-lux rounded-2xl p-6 lg:col-span-3">
        <div class="flex items-baseline justify-between gap-3">
          <h3 class="font-display text-[16px] font-black text-cam-ink">Daftar Peluang</h3>
          <span class="text-[11.5px] text-stone-400 shrink-0">Urut dari yang terbesar penghematannya</span>
        </div>

        <div v-if="daftar.length" class="space-y-3 mt-5">
          <div v-for="o in daftar" :key="o.id" class="rounded-xl border p-4"
               :class="o.terwujud ? 'border-cam-lime/40 bg-cam-lime-soft/30' : 'border-stone-100'">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="text-[13px] font-bold text-cam-ink leading-snug">{{ o.judul }}</div>
                <div class="text-[10.5px] text-stone-400 mt-1">
                  {{ o.area ?? 'Tanpa area' }}
                  <template v-if="o.penanggungJawab"> · {{ o.penanggungJawab }}</template>
                  <template v-if="o.targetSelesai"> · target {{ o.targetSelesai }}</template>
                </div>
              </div>
              <span class="shrink-0 text-[9.5px] font-bold uppercase tracking-wide text-white px-2 py-1 rounded-lg"
                    :style="{ background: warnaStatus[o.status] ?? '#22312F' }">{{ o.status }}</span>
            </div>

            <p v-if="o.uraian" class="text-[11.5px] text-stone-500 mt-2.5 leading-relaxed">{{ o.uraian }}</p>

            <div class="grid grid-cols-4 gap-2 mt-3.5 pt-3.5 hairline border-b-0">
              <div>
                <div class="num text-[12.5px] font-bold text-cam-ink">{{ angka(o.hematLiter) }} L</div>
                <div class="text-[10px] text-stone-400 mt-0.5">Solar/bulan</div>
              </div>
              <div>
                <div class="num text-[12.5px] font-bold text-cam-ink">{{ angka(o.hematKwh) }} kWh</div>
                <div class="text-[10px] text-stone-400 mt-0.5">Listrik/bulan</div>
              </div>
              <div>
                <div class="num text-[12.5px] font-bold text-cam-ink">{{ angka(o.tco2e, 2) }} t</div>
                <div class="text-[10px] text-stone-400 mt-0.5">Emisi/bulan</div>
              </div>
              <div>
                <div class="num text-[12.5px] font-bold text-cam-ink">{{ rupiah(o.rupiah, 1) }}</div>
                <div class="text-[10px] text-stone-400 mt-0.5">Nilai/bulan</div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 mt-3.5">
              <select :value="o.status" @change="ubahStatus(o, ($event.target as HTMLSelectElement).value)"
                      class="ring-focus rounded-xl border border-stone-200 px-2.5 py-1.5 text-[11.5px] transition">
                <option v-for="s in opsi.status" :key="s" :value="s">{{ s.charAt(0).toUpperCase() + s.slice(1) }}</option>
              </select>
              <button type="button" @click="hapus(o)"
                      class="text-[11.5px] font-bold text-stone-400 hover:text-cam-coral transition px-2 py-1.5">Hapus</button>
            </div>
          </div>
        </div>
        <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada peluang yang dicatat.</p>
      </section>
    </div>

  </div>
</template>
