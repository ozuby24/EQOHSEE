<script setup lang="ts">
/**
 * Master Data — Unit Alat.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import KepalaEnergi from '../../Components/KepalaEnergi.vue';
import { angka } from '../../energi';
import type { HalamanEnergiMaster, UnitEnergiMaster } from '../../types';

const props = defineProps<HalamanEnergiMaster>();

const form = useForm({
  company_id: '',
  kode: '',
  nama: '',
  kategori: Object.keys(props.opsi.kategori)[0] ?? '',
  merek: '',
  daya_hp: '',
  payload_ton: '',
});

function simpan() {
  form.post(props.tautan.simpan, { preserveScroll: true, onSuccess: () => form.reset() });
}

function hapus(u: UnitEnergiMaster) {
  if (!confirm(`Hapus ${u.kode} beserta seluruh catatan bahan bakarnya?`)) return;
  router.delete(u.urlHapus, { preserveScroll: true });
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="judul" />

  <div class="max-w-5xl mx-auto space-y-5">

    <KepalaEnergi judul="Master Data — Unit Alat"
        ket="Daftar alat yang catatan bahan bakarnya diikuti. Kategori menentukan acuan
             pembandingnya: sebuah unit dinilai terhadap rata-rata kelompoknya sendiri, jadi
             salah kategori berarti salah pula penilaiannya.">

      <div class="grid gap-4 grid-cols-2 lg:grid-cols-4 mt-6 pt-6 hairline border-b-0">
        <div v-for="h in hitungKategori" :key="h.kode">
          <div class="num text-[20px] font-bold text-cam-lime-deep">{{ h.jumlah }}</div>
          <div class="text-[10.5px] text-stone-400 mt-1">{{ h.nama }}</div>
        </div>
      </div>
    </KepalaEnergi>

    <div class="grid gap-4 lg:grid-cols-5">

      <section class="kartu-lux rounded-2xl p-6 lg:col-span-2">
        <h3 class="font-display text-[16px] font-black text-cam-ink">Daftarkan Unit</h3>

        <form class="space-y-3.5 mt-5" @submit.prevent="simpan">
          <div v-if="Object.keys(form.errors).length"
               class="rounded-xl bg-red-50 border border-red-100 text-red-700 px-4 py-3 text-[12px]">
            <ul class="space-y-0.5">
              <li v-for="(pesan, k) in form.errors" :key="k">• {{ pesan }}</li>
            </ul>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label :class="label">Kode</label>
              <input v-model="form.kode" required placeholder="HD785-01" :class="isian">
            </div>
            <div>
              <label :class="label">Kategori</label>
              <select v-model="form.kategori" :class="isian">
                <option v-for="(nama, kode) in opsi.kategori" :key="kode" :value="kode">{{ nama }}</option>
              </select>
            </div>
          </div>

          <div>
            <label :class="label">Nama</label>
            <input v-model="form.nama" required placeholder="Dump Truck Komatsu HD785-7" :class="isian">
          </div>

          <div>
            <label :class="label">Perusahaan</label>
            <select v-model="form.company_id" :class="isian">
              <option value="">Tidak ditentukan</option>
              <option v-for="c in opsi.perusahaan" :key="c.nilai" :value="c.nilai">{{ c.label }}</option>
            </select>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label :class="label">Merek</label>
              <input v-model="form.merek" placeholder="Komatsu" :class="isian">
            </div>
            <div>
              <label :class="label">Daya (HP)</label>
              <input v-model="form.daya_hp" type="number" :class="isian">
            </div>
            <div>
              <label :class="label">Payload (t)</label>
              <input v-model="form.payload_ton" type="number" step="0.01" :class="isian">
            </div>
          </div>

          <button type="submit" :disabled="form.processing"
                  class="w-full rounded-xl bg-cam-ink text-white px-4 py-2.5 text-[12.5px] font-bold hover:brightness-110 transition disabled:opacity-40">
            {{ form.processing ? 'Menyimpan…' : 'Daftarkan Unit' }}
          </button>
        </form>
      </section>

      <section class="kartu-lux rounded-2xl p-6 lg:col-span-3">
        <div class="flex items-baseline justify-between gap-3">
          <h3 class="font-display text-[16px] font-black text-cam-ink">Unit Terdaftar</h3>
          <span class="text-[11.5px] text-stone-400 shrink-0">{{ units.length }} unit</span>
        </div>

        <div v-if="units.length" class="overflow-x-auto mt-4 -mx-1">
          <table class="w-full text-[12.5px] min-w-[520px]">
            <thead>
              <tr class="text-[10.5px] font-bold uppercase tracking-wide text-stone-400 hairline">
                <th class="text-left py-2.5">Kode</th>
                <th class="text-left py-2.5">Nama</th>
                <th class="text-left py-2.5">Kategori</th>
                <th class="num py-2.5">HP</th>
                <th class="num py-2.5">Payload</th>
                <th class="py-2.5"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in units" :key="u.id" class="hairline">
                <td class="py-2.5">
                  <Link :href="u.urlDetail" class="font-bold text-cam-ink hover:text-cam-lime-deep transition">{{ u.kode }}</Link>
                </td>
                <td class="py-2.5 text-stone-500">
                  {{ u.nama }}
                  <span v-if="u.merek" class="block text-[10.5px] text-stone-400">{{ u.merek }}</span>
                </td>
                <td class="py-2.5 text-stone-500">{{ u.kategori }}</td>
                <td class="num py-2.5">{{ u.dayaHp ? angka(u.dayaHp) : '—' }}</td>
                <td class="num py-2.5">{{ u.payloadTon ? angka(u.payloadTon, 1) : '—' }}</td>
                <td class="py-2.5 text-right">
                  <button type="button" @click="hapus(u)"
                          class="text-[11.5px] font-bold text-stone-400 hover:text-cam-coral transition">Hapus</button>
                </td>
              </tr>
            </tbody>
          </table>

          <p class="text-[10.5px] text-stone-400 mt-4 leading-relaxed">
            Menghapus unit ikut menghapus seluruh catatan bahan bakarnya — angka riwayat pada
            halaman lain akan berubah. Untuk unit yang sekadar berhenti beroperasi, biarkan
            terdaftar; unit tanpa catatan pada suatu rentang memang tidak muncul di peringkat.
          </p>
        </div>
        <p v-else class="text-[12px] text-stone-400 mt-6">Belum ada unit yang terdaftar.</p>
      </section>
    </div>

  </div>
</template>
