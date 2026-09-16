<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const STATUS_OPTIONS: Record<string, string> = {
  aktif: 'Aktif Dipantau',
  perlu_tindak_lanjut: 'Perlu Tindak Lanjut',
  tidak_aktif: 'Tidak Aktif',
};

const STATUS_BADGE: Record<string, string> = {
  aktif: 'bg-cam-lime-soft text-cam-lime-deep',
  perlu_tindak_lanjut: 'bg-amber-100 text-amber-700',
  tidak_aktif: 'bg-stone-100 text-stone-500',
};

const STATUS_FILL: Record<string, string> = {
  aktif: '#5EAE38',
  perlu_tindak_lanjut: '#F0B429',
  tidak_aktif: '#A8B0B8',
};

interface Pjp {
  id: number; nama_perusahaan: string; status: string; updated_at: string;
}
interface PageLink { url: string | null; label: string; active: boolean }

const props = defineProps<{
  pjps: { data: Pjp[]; links: PageLink[] };
  filters: { search: string; status: string };
  statusCounts: Record<string, number>;
}>();

const saring = useForm({ search: props.filters.search, status: props.filters.status });

function terapkan() {
  saring.transform((d) => Object.fromEntries(Object.entries(d).filter(([, v]) => v)))
    .get('/pjp', { preserveState: true, preserveScroll: true, replace: true });
}

function resetFilter() {
  saring.search = '';
  saring.status = '';
  router.get('/pjp', {}, { preserveState: true, preserveScroll: true });
}

function hapus(pjp: Pjp) {
  if (!confirm(`Hapus data PJP "${pjp.nama_perusahaan}"? Tindakan ini tidak bisa dibatalkan.`)) return;
  router.delete(`/pjp/${pjp.id}`, { preserveScroll: true });
}

function formatTanggal(iso: string): string {
  return new Date(iso).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

const totalStatus = Object.values(props.statusCounts).reduce((a, b) => a + b, 0);

const exportUrl = () => {
  const q = new URLSearchParams(Object.fromEntries(
    Object.entries(props.filters).filter(([, v]) => v),
  )).toString();
  return `/pjp/export${q ? `?${q}` : ''}`;
};

const importing = ref(false);

function onImportFile(e: Event) {
  const input = e.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;

  const formData = new FormData();
  formData.append('file', file);

  router.post('/pjp/import', formData, {
    forceFormData: true,
    preserveScroll: true,
    onStart: () => (importing.value = true),
    onFinish: () => { importing.value = false; input.value = ''; },
  });
}
</script>

<template>
  <Head title="Data PJP" />

  <div class="max-w-6xl mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
      <div>
        <h2 class="font-serif text-xl font-bold text-cam-ink">Data PJP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Daftar seluruh Perusahaan Jasa Pertambangan (PJP) yang dipantau.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="/pjp/import-template"
           class="rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-semibold
                  text-stone-600 hover:bg-stone-50 transition">Unduh Template</a>
        <label class="rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-semibold
                      text-stone-600 hover:bg-stone-50 transition cursor-pointer"
               :class="importing ? 'opacity-60 cursor-wait' : ''">
          {{ importing ? 'Mengimpor…' : 'Import Excel' }}
          <input type="file" accept=".xlsx,.xls,.csv" class="hidden" :disabled="importing" @change="onImportFile">
        </label>
        <a :href="exportUrl()"
           class="rounded-xl border border-stone-200 bg-white px-3.5 py-2 text-[12px] font-semibold
                  text-stone-600 hover:bg-stone-50 transition">Export Excel</a>
        <Link href="/pjp/create"
              class="lime-gradient shadow-glow rounded-xl text-white px-3.5 py-2 text-[12.5px] font-bold
                     hover:brightness-105 transition">+ Tambah PJP</Link>
      </div>
    </div>

    <!-- Status bar ringkas -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 mb-4">
      <div class="flex items-baseline justify-between mb-3">
        <h3 class="text-[13px] font-bold text-cam-ink">Capaian Status Seluruh PJP</h3>
        <span class="text-[11.5px] text-stone-400">{{ totalStatus }} PJP</span>
      </div>
      <div v-if="totalStatus > 0" class="flex h-5 w-full gap-[3px] overflow-hidden rounded-full bg-stone-100">
        <div v-for="key in ['aktif','perlu_tindak_lanjut','tidak_aktif']" :key="key"
             v-show="(statusCounts[key] ?? 0) > 0"
             class="h-full first:rounded-l-full last:rounded-r-full"
             :style="{ flexGrow: statusCounts[key] ?? 0, flexBasis: 0, background: STATUS_FILL[key] }"></div>
      </div>
      <div v-else class="h-5 rounded-full bg-stone-100"></div>
      <dl class="grid grid-cols-1 sm:grid-cols-3 gap-2 mt-4">
        <div v-for="key in ['aktif','perlu_tindak_lanjut','tidak_aktif']" :key="key"
             class="flex items-center gap-2 text-[12.5px]">
          <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: STATUS_FILL[key] }"></span>
          <span class="text-stone-600">{{ STATUS_OPTIONS[key] }}</span>
          <span class="ml-auto font-bold text-cam-ink">{{ statusCounts[key] ?? 0 }}</span>
        </div>
      </dl>
    </div>

    <!-- Filter -->
    <form class="bg-white rounded-2xl shadow-card border border-stone-100 p-4 mb-4
                 flex flex-wrap items-end gap-3" @submit.prevent="terapkan">
      <div class="flex-1 min-w-[200px]">
        <label class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Cari</label>
        <input v-model="saring.search" placeholder="Nama, NIB, penanggung jawab, atau alamat…"
               class="ring-focus mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-[13px]">
      </div>
      <div class="min-w-[180px]">
        <label class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Status</label>
        <select v-model="saring.status" class="ring-focus mt-1 w-full rounded-xl border border-stone-200 px-3.5 py-2 text-[13px]">
          <option value="">Semua Status</option>
          <option v-for="(label, key) in STATUS_OPTIONS" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="lime-gradient shadow-glow rounded-xl text-white px-4 py-2 text-[12.5px] font-bold">Terapkan</button>
        <button v-if="filters.search || filters.status" type="button" @click="resetFilter"
                class="rounded-xl border border-stone-200 px-4 py-2 text-[12.5px] font-semibold text-stone-600 hover:bg-stone-50">Reset</button>
      </div>
    </form>

    <div v-if="!pjps.data.length" class="bg-white rounded-2xl border border-dashed border-stone-200 p-14 text-center text-[13px] text-stone-400">
      {{ filters.search || filters.status ? 'Tidak ada data PJP yang cocok dengan filter.' : 'Belum ada data PJP. Tambahkan data untuk mulai memantau.' }}
    </div>

    <template v-else>
      <div class="hidden md:block bg-white rounded-2xl shadow-card border border-stone-100 overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-[13px]">
          <thead class="border-b border-stone-100 bg-stone-50 text-[10.5px] font-bold uppercase tracking-wide text-stone-400">
            <tr>
              <th class="px-4 py-3">Nama Perusahaan</th>
              <th class="px-4 py-3">Status</th>
              <th class="px-4 py-3">Terakhir Diperbarui</th>
              <th class="px-4 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-stone-100">
            <tr v-for="pjp in pjps.data" :key="pjp.id" class="hover:bg-stone-50 transition-colors">
              <td class="px-4 py-3 font-semibold text-cam-ink">{{ pjp.nama_perusahaan }}</td>
              <td class="px-4 py-3">
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="STATUS_BADGE[pjp.status]">
                  {{ STATUS_OPTIONS[pjp.status] ?? pjp.status }}
                </span>
              </td>
              <td class="px-4 py-3 text-stone-500">{{ formatTanggal(pjp.updated_at) }}</td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-3 text-[12.5px] font-semibold">
                  <Link :href="`/pjp/${pjp.id}`" class="text-cam-lime-deep hover:underline">Detail</Link>
                  <Link :href="`/pjp/${pjp.id}/edit`" class="text-cam-lime-deep hover:underline">Ubah</Link>
                  <button type="button" class="text-red-500 hover:underline" @click="hapus(pjp)">Hapus</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="md:hidden space-y-3">
        <div v-for="pjp in pjps.data" :key="pjp.id" class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
          <div class="flex items-start justify-between gap-3">
            <p class="font-semibold text-cam-ink">{{ pjp.nama_perusahaan }}</p>
            <span class="inline-flex shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="STATUS_BADGE[pjp.status]">
              {{ STATUS_OPTIONS[pjp.status] ?? pjp.status }}
            </span>
          </div>
          <p class="text-[11.5px] text-stone-400 mt-1">Diperbarui {{ formatTanggal(pjp.updated_at) }}</p>
          <div class="flex gap-4 text-[12.5px] font-semibold mt-3">
            <Link :href="`/pjp/${pjp.id}`" class="text-cam-lime-deep">Detail</Link>
            <Link :href="`/pjp/${pjp.id}/edit`" class="text-cam-lime-deep">Ubah</Link>
            <button type="button" class="text-red-500" @click="hapus(pjp)">Hapus</button>
          </div>
        </div>
      </div>

      <nav v-if="pjps.links.length > 3" class="mt-5 flex flex-wrap justify-center gap-1.5">
        <component v-for="(l, i) in pjps.links" :key="i" :is="l.url ? Link : 'span'" :href="l.url ?? undefined"
                   class="px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition"
                   :class="l.active ? 'border-transparent bg-cam-ink text-white'
                         : l.url ? 'border-stone-200 text-stone-600 hover:bg-stone-50' : 'border-stone-100 text-stone-300'"
                   v-html="l.label.replace('&laquo; Previous', '← Sebelumnya').replace('Next &raquo;', 'Selanjutnya →')" />
      </nav>
    </template>
  </div>
</template>
