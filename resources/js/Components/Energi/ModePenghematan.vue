<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Kartu from './Kartu.vue';
import KartuAngka from './KartuAngka.vue';
import Dialog from '../Dialog.vue';
import { useDialog } from '../../dialog';
import { n, rp } from '../../energi';

const props = defineProps<{ daftar: any[]; terwujud: Record<string, any>; potensi: Record<string, any> }>();
const { dialog, tanya, batal, lanjut } = useDialog();

/* Kanban usulan → selesai (FSD §8.6). Daftar status datang dari kode yang
   sama dengan validasi server — cacat D1 lahir dari daftar yang disalin. */
const KOLOM = [['usulan', 'Usulan'], ['review', 'Review'], ['disetujui', 'Disetujui'], ['berjalan', 'Berjalan'], ['selesai', 'Selesai (M&V)'], ['ditolak', 'Ditolak']];
const perStatus = computed(() => Object.fromEntries(KOLOM.map(([k]) => [k, props.daftar.filter(p => p.status === k)])));

const baru = useForm<Record<string, any>>({ judul: '', area: '', status: 'usulan', uraian: '', hemat_liter: 0, hemat_kwh: 0, penanggung_jawab: '', target_selesai: '', capex_rp: '' });
const simpan = () => baru.post('/energi/penghematan', { preserveScroll: true, onSuccess: () => baru.reset() });

const mv = ref<any | null>(null);
const mvForm = useForm<Record<string, any>>({ status: 'selesai', metode_mv: 'B', hemat_terverifikasi_liter: '', hemat_terverifikasi_kwh: '', tanggal_mulai_hemat: '', mv_catatan: '', capex_rp: '' });

function ubah(p: any, status: string) {
  if (status === 'selesai') {
    mv.value = p;
    mvForm.defaults({ status: 'selesai', metode_mv: p.metode_mv ?? 'B', hemat_terverifikasi_liter: p.hemat_terverifikasi_liter ?? '',
      hemat_terverifikasi_kwh: p.hemat_terverifikasi_kwh ?? '', tanggal_mulai_hemat: p.tanggal_mulai_hemat ?? '', mv_catatan: p.mv_catatan ?? '', capex_rp: p.capex_rp ?? '' });
    mvForm.reset();
    return;
  }
  router.put(`/energi/penghematan/${p.id}`, { status }, { preserveScroll: true });
}
const simpanMv = () => mvForm.put(`/energi/penghematan/${mv.value.id}`, { preserveScroll: true, onSuccess: () => { mv.value = null; } });
async function hapus(p: any) { if (await tanya(`Hapus peluang "${p.judul}"?`)) router.delete(`/energi/penghematan/${p.id}`, { preserveScroll: true }); }
</script>

<template>
  <div class="space-y-4">
    <section class="ka-kisi">
      <KartuAngka label="Potensi seluruh peluang" :nilai="potensi.gj" satuan="GJ/bulan" />
      <KartuAngka label="Potensi emisi dihindari" :nilai="potensi.tco2e" satuan="tCO₂e/bulan" :desimal="2" />
      <KartuAngka label="Sudah berjalan" :nilai="terwujud.gj" satuan="GJ/bulan" :catatan="`${terwujud.jumlah ?? 0} peluang berjalan/selesai`" />
      <KartuAngka label="Nilai berjalan" :nilai="terwujud.rupiah" satuan="Rp/bulan" :desimal="0" />
    </section>

    <section class="grid gap-3 lg:grid-cols-3 xl:grid-cols-6">
      <div v-for="[kode, label] in KOLOM" :key="kode" class="rounded-2xl bg-stone-50 border border-stone-100 p-3 space-y-2 min-h-[120px]">
        <p class="text-[11px] font-bold text-stone-600">{{ label }} <span class="text-stone-400">· {{ perStatus[kode].length }}</span></p>
        <article v-for="p in perStatus[kode]" :key="p.id" class="rounded-xl bg-white border border-stone-100 p-3 space-y-1.5">
          <p class="text-[12px] font-semibold text-cam-ink">{{ p.judul }}</p>
          <p class="text-[10.5px] text-stone-500">{{ p.area || '—' }} · {{ p.penanggung_jawab || 'tanpa PIC' }}</p>
          <p class="text-[10.5px] text-stone-600">
            <span v-if="p.hemat_liter > 0">{{ n(p.hemat_liter, 0) }} L</span>
            <span v-if="p.hemat_kwh > 0"> {{ n(p.hemat_kwh, 0) }} kWh</span>/bulan
            <span v-if="p.capex_rp"> · CAPEX {{ rp(p.capex_rp) }}</span>
          </p>
          <p v-if="p.status === 'selesai'" class="text-[10.5px] text-emerald-700 font-semibold">M&V opsi {{ p.metode_mv }} · terverifikasi {{ n(p.hemat_terverifikasi_liter, 0) }} L, {{ n(p.hemat_terverifikasi_kwh, 0) }} kWh</p>
          <div class="flex items-center gap-1.5">
            <select :value="p.status" class="rounded border-stone-200 text-[11px] flex-1" aria-label="Ubah status peluang" @change="ubah(p, ($event.target as HTMLSelectElement).value)">
              <option v-for="[k, l] in KOLOM" :key="k" :value="k">{{ l }}</option>
            </select>
            <button type="button" class="text-red-600 text-[11px] px-1" @click="hapus(p)">Hapus</button>
          </div>
        </article>
      </div>
    </section>

    <Kartu v-if="mv" :judul="`Hasil M&V — ${mv.judul}`">
      <form class="grid gap-3 md:grid-cols-3" @submit.prevent="simpanMv">
        <label class="text-[11px] font-semibold text-stone-500">Metode IPMVP
          <select v-model="mvForm.metode_mv" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
            <option value="A">A — pengukuran parameter kunci</option><option value="B">B — pengukuran seluruh parameter</option>
            <option value="C">C — seluruh fasilitas (meter utama)</option><option value="D">D — simulasi terkalibrasi</option>
          </select>
        </label>
        <label class="text-[11px] font-semibold text-stone-500">Hemat terverifikasi (L/bulan)<input v-model="mvForm.hemat_terverifikasi_liter" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Hemat terverifikasi (kWh/bulan)<input v-model="mvForm.hemat_terverifikasi_kwh" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Mulai hemat<input v-model="mvForm.tanggal_mulai_hemat" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">CAPEX (Rp)<input v-model="mvForm.capex_rp" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Catatan M&V<input v-model="mvForm.mv_catatan" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div class="md:col-span-3 flex gap-2"><button class="eq-btn-utama" :disabled="mvForm.processing">Simpan & tandai selesai</button><button type="button" class="eq-btn-lain" @click="mv = null">Batal</button></div>
      </form>
      <p v-if="mvForm.errors.status" class="text-[11px] text-red-600 mt-2">{{ mvForm.errors.status }}</p>
    </Kartu>

    <Kartu judul="Catat peluang penghematan">
      <form class="grid gap-3 md:grid-cols-3" @submit.prevent="simpan">
        <label class="text-[11px] font-semibold text-stone-500">Judul<input v-model="baru.judul" required class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Area<input v-model="baru.area" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Status awal
          <select v-model="baru.status" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]">
            <option v-for="[k, l] in KOLOM.filter(x => x[0] !== 'selesai')" :key="k" :value="k">{{ l }}</option>
          </select>
        </label>
        <label class="text-[11px] font-semibold text-stone-500">Perkiraan hemat solar (L/bulan)<input v-model="baru.hemat_liter" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Perkiraan hemat listrik (kWh/bulan)<input v-model="baru.hemat_kwh" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">CAPEX (Rp)<input v-model="baru.capex_rp" type="number" step="any" min="0" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Penanggung jawab<input v-model="baru.penanggung_jawab" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Target selesai<input v-model="baru.target_selesai" type="date" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <label class="text-[11px] font-semibold text-stone-500">Uraian<input v-model="baru.uraian" class="block w-full mt-1 rounded-lg border-stone-200 text-[12px]"></label>
        <div><button class="eq-btn-utama" :disabled="baru.processing">Catat peluang</button></div>
      </form>
      <p v-for="(pesan, k) in baru.errors" :key="k" class="text-[11px] text-red-600 mt-1">{{ pesan }}</p>
    </Kartu>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
