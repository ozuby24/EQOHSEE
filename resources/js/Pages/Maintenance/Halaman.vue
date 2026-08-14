<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{ mode: 'dashboard' | 'order' | 'armada'; [key: string]: any }>();
const halaman = usePage<any>();
const isAdmin = computed(() => Boolean(halaman.props.pengguna?.admin));

const judul: Record<string, string> = {
  dashboard: 'Maintenance & Reliability Center',
  order: 'Perintah Kerja',
  armada: 'Keandalan per Alat',
};

const angka = (v: unknown, d = 0) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: d, minimumFractionDigits: d }).format(Number(v || 0));
const persen = (v: unknown) => `${angka(v, 1)}%`;
const rupiah = (v: unknown) => `Rp ${angka(v, 0)}`;
const label = (v: string) => String(v || '').replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
const jam = (v: unknown) => v === null || v === undefined ? '—' : `${angka(v, 1)} jam`;

const tautan = computed(() => props.tautan || {});
const untuk = (pola: string | undefined, id: number | string) => String(pola || '').replace('__ID__', String(id));

const order = useForm<any>({
  company_id: '', ko_object_id: '', nomor: '', jenis: 'korektif', prioritas: 'sedang',
  gejala: '', penyebab: '', dilaporkan_pada: new Date().toISOString().slice(0, 16), hm_saat_rusak: '',
});

function simpan() {
  order.post(tautan.value.simpan, {
    preserveScroll: true,
    onSuccess: () => order.reset('nomor', 'gejala', 'penyebab', 'hm_saat_rusak'),
  });
}

function ubahStatus(item: any, status: string) {
  router.put(untuk(tautan.value.ubahStatus, item.id), { status }, { preserveScroll: true });
}

function hapus(item: any) {
  if (window.confirm(`Hapus perintah kerja ${item.nomor || item.gejala}?`)) {
    router.delete(untuk(tautan.value.hapus, item.id), { preserveScroll: true });
  }
}

function rentang() {
  router.get(window.location.pathname, { dari: props.dari, sampai: props.sampai }, { preserveState: true, replace: true });
}

const warnaStatus: Record<string, string> = {
  dibuka: 'bg-red-100 text-red-700',
  dikerjakan: 'bg-amber-100 text-amber-700',
  selesai: 'bg-emerald-100 text-emerald-700',
  batal: 'bg-stone-100 text-stone-500',
};
</script>

<template>
  <Head :title="judul[props.mode]" />

  <div class="max-w-[1400px] mx-auto space-y-5">
    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-[.16em] text-cam-lime-deep">Engineering · Reliability</p>
        <h2 class="text-2xl font-extrabold tracking-tight text-stone-800">{{ judul[props.mode] }}</h2>
        <p class="text-[12px] text-stone-500 mt-1">Alat diambil dari registri Keselamatan Operasi; di sini dicatat gangguan, perbaikan, dan biayanya.</p>
      </div>
      <div class="flex gap-2">
        <input v-model="props.dari" type="date" class="rounded-lg border-stone-200 text-[11px]">
        <input v-model="props.sampai" type="date" class="rounded-lg border-stone-200 text-[11px]">
        <button class="eq-btn-lain" type="button" @click="rentang">Terapkan</button>
      </div>
    </section>

    <nav class="flex flex-wrap gap-2">
      <Link v-for="i in [['dashboard','Ringkasan',tautan.dashboard],['order','Perintah Kerja',tautan.order],['armada','Per Alat',tautan.armada]]"
            :key="i[0]" :href="i[2] as string" class="rounded-full px-4 py-2 text-[11px] font-bold"
            :class="props.mode === i[0] ? 'bg-cam-ink text-white' : 'bg-white text-stone-500 border border-stone-200'">{{ i[1] }}</Link>
    </nav>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
      <article v-for="c in [
        { l: 'Ketersediaan', v: persen(props.keandalan?.ketersediaan), c: Number(props.keandalan?.ketersediaan) >= 85 ? 'text-emerald-600' : 'text-red-600' },
        { l: 'MTBF', v: jam(props.keandalan?.mtbf), c: 'text-cam-ink' },
        { l: 'MTTR', v: jam(props.keandalan?.mttr), c: 'text-violet-700' },
        { l: 'Kepatuhan PM', v: persen(props.pm?.persen), c: Number(props.pm?.persen) >= 90 ? 'text-emerald-600' : 'text-amber-600' },
        { l: 'Tunggakan', v: `${props.tunggakan?.jumlah || 0} WO`, c: props.tunggakan?.kritis ? 'text-red-600' : 'text-stone-700' },
      ]" :key="c.l" class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
        <p class="text-[10px] uppercase tracking-wide font-bold text-stone-400">{{ c.l }}</p>
        <p class="mt-2 text-xl font-extrabold" :class="c.c">{{ c.v }}</p>
      </article>
    </section>

    <template v-if="props.mode === 'dashboard'">
      <section class="grid gap-5 lg:grid-cols-2">
        <!--
          Waktu menunggu dipisahkan dari waktu mengerjakan. MTTR panjang
          berarti pekerjaannya sulit; menunggu panjang berarti
          persiapannya yang kurang. Digabung, bengkel disalahkan atas
          gudang yang kosong.
        -->
        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Ke mana waktu henti pergi</h3>
          <p class="text-[11px] text-stone-400 mt-1">Total {{ jam(props.keandalan?.jamHenti) }} pada periode ini.</p>

          <div class="mt-4 flex h-3 rounded-full overflow-hidden bg-stone-100">
            <div class="bg-violet-500" :style="{ width: `${100 - Number(props.keandalan?.porsiMenunggu || 0)}%` }"></div>
            <div class="bg-amber-400" :style="{ width: `${Number(props.keandalan?.porsiMenunggu || 0)}%` }"></div>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400"><span class="inline-block w-2 h-2 rounded-sm bg-violet-500 mr-1"></span>Dikerjakan</small>
              <b class="text-[15px]">{{ jam(props.keandalan?.jamPerbaikan) }}</b>
            </div>
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400"><span class="inline-block w-2 h-2 rounded-sm bg-amber-400 mr-1"></span>Menunggu</small>
              <b class="text-[15px]" :class="Number(props.keandalan?.porsiMenunggu) > 40 ? 'text-amber-700' : ''">{{ jam(props.keandalan?.jamMenunggu) }}</b>
            </div>
          </div>

          <p v-if="Number(props.keandalan?.porsiMenunggu) > 40" class="mt-3 rounded-xl bg-amber-50 text-amber-800 p-3 text-[11px]">
            <b>{{ persen(props.keandalan?.porsiMenunggu) }}</b> waktu henti habis menunggu, bukan mengerjakan.
            Telusuri ketersediaan suku cadang dan penjadwalan montir sebelum menambah kapasitas bengkel.
          </p>
        </div>

        <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="font-bold text-[14px]">Biaya pemeliharaan</h3>
          <p class="text-[11px] text-stone-400 mt-1">Termasuk suku cadang {{ rupiah(props.biaya?.sukuCadang) }}.</p>

          <div class="mt-4 flex items-end gap-2">
            <b class="text-3xl font-extrabold tracking-tight text-stone-800">{{ rupiah(props.biaya?.total) }}</b>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-4">
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400">Per jam jalan</small>
              <b class="text-[15px]">{{ rupiah(props.biaya?.perJam) }}</b>
            </div>
            <div class="rounded-xl bg-stone-50 p-3">
              <small class="block text-[10px] text-stone-400">Per ton produksi</small>
              <b class="text-[15px]">{{ rupiah(props.biaya?.perTon) }}</b>
              <small class="block text-[10px] text-stone-400 mt-1">{{ angka(props.biaya?.tonDasar) }} ton disetujui</small>
            </div>
          </div>

          <div class="mt-4 pt-4 border-t border-stone-100 grid grid-cols-3 gap-3 text-center">
            <div><small class="block text-[10px] text-stone-400">Unit</small><b>{{ props.ringkas?.unit || 0 }}</b></div>
            <div><small class="block text-[10px] text-stone-400">Kegagalan</small><b>{{ props.ringkas?.kegagalan || 0 }}</b></div>
            <div><small class="block text-[10px] text-stone-400">PM tanpa jadwal</small><b :class="props.pm?.tanpaJadwal ? 'text-amber-600' : ''">{{ props.pm?.tanpaJadwal || 0 }}</b></div>
          </div>
        </div>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Tunggakan pekerjaan</h3>
        <div class="grid gap-3 sm:grid-cols-4 mt-4">
          <div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Terbuka</small><b class="text-[15px]">{{ props.tunggakan?.jumlah || 0 }}</b></div>
          <div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Prioritas kritis</small><b class="text-[15px]" :class="props.tunggakan?.kritis ? 'text-red-600' : ''">{{ props.tunggakan?.kritis || 0 }}</b></div>
          <div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Belum mulai</small><b class="text-[15px]">{{ props.tunggakan?.belumMulai || 0 }}</b></div>
          <div class="rounded-xl bg-stone-50 p-3"><small class="block text-[10px] text-stone-400">Tertahan terlama</small><b class="text-[15px]">{{ jam(props.tunggakan?.terlamaJam) }}</b></div>
        </div>
      </section>
    </template>

    <template v-if="props.mode === 'order'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="font-bold text-[14px]">Buka perintah kerja</h3>
        <form class="grid gap-3 md:grid-cols-3 mt-4" @submit.prevent="simpan">
          <select v-model="order.ko_object_id" class="rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih alat (registri KO)</option>
            <option v-for="o in props.objekOpsi || []" :key="o.id" :value="o.id">{{ o.kode }} — {{ o.nama }}</option>
          </select>
          <input v-model="order.nomor" placeholder="Nomor WO (opsional)" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="order.dilaporkan_pada" type="datetime-local" required class="rounded-lg border-stone-200 text-[12px]">
          <select v-model="order.jenis" class="rounded-lg border-stone-200 text-[12px]">
            <option v-for="j in props.opsi?.jenis || []" :key="j" :value="j">{{ label(j) }}</option>
          </select>
          <select v-model="order.prioritas" class="rounded-lg border-stone-200 text-[12px]">
            <option v-for="p in props.opsi?.prioritas || []" :key="p" :value="p">Prioritas {{ label(p) }}</option>
          </select>
          <input v-model.number="order.hm_saat_rusak" type="number" step="any" min="0" placeholder="HM saat rusak" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="order.gejala" required placeholder="Gejala yang teramati" class="rounded-lg border-stone-200 text-[12px] md:col-span-3">
          <textarea v-model="order.penyebab" placeholder="Dugaan penyebab" class="rounded-lg border-stone-200 text-[12px] md:col-span-3"></textarea>
          <button :disabled="order.processing" class="eq-btn-utama md:col-span-3">{{ order.processing ? 'Menyimpan...' : 'Buka perintah kerja' }}</button>
        </form>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
        <div class="px-5 py-4 border-b border-stone-100"><h3 class="font-bold text-[14px]">Perintah kerja periode ini</h3></div>
        <table class="min-w-full text-left text-[12px]">
          <thead><tr class="border-b border-stone-100 text-stone-400">
            <th class="px-5 py-3">Alat / Gejala</th><th class="px-5 py-3">Jenis</th>
            <th class="px-5 py-3">Dilaporkan</th><th class="px-5 py-3 text-right">Henti</th>
            <th class="px-5 py-3 text-right">Menunggu</th><th class="px-5 py-3 text-right">Biaya</th>
            <th class="px-5 py-3">Status</th><th></th>
          </tr></thead>
          <tbody>
            <tr v-for="w in props.orders || []" :key="w.id" class="border-b border-stone-50" :class="w.prioritas === 'kritis' && w.terbuka ? 'bg-red-50/40' : ''">
              <td class="px-5 py-3"><b>{{ w.objek || '—' }}</b><small class="block text-[10px] text-stone-400">{{ w.gejala }}</small></td>
              <td class="px-5 py-3">{{ label(w.jenis) }}<small class="block text-[10px] text-stone-400">{{ label(w.prioritas) }}</small></td>
              <td class="px-5 py-3">{{ w.dilaporkanPada }}</td>
              <td class="px-5 py-3 text-right">{{ angka(w.jamHenti, 1) }}</td>
              <td class="px-5 py-3 text-right" :class="w.jamMenunggu > w.jamPerbaikan ? 'text-amber-700 font-semibold' : ''">{{ angka(w.jamMenunggu, 1) }}</td>
              <td class="px-5 py-3 text-right">{{ rupiah(w.biaya) }}</td>
              <td class="px-5 py-3">
                <select :value="w.status" class="rounded border-stone-200 text-[11px]" @change="ubahStatus(w, ($event.target as HTMLSelectElement).value)">
                  <option v-for="s in props.opsi?.status || []" :key="s" :value="s">{{ label(s) }}</option>
                </select>
              </td>
              <td class="px-5 py-3 text-right"><button v-if="isAdmin" type="button" class="text-red-600 text-[11px]" @click="hapus(w)">Hapus</button></td>
            </tr>
            <tr v-if="!(props.orders || []).length"><td colspan="8" class="px-5 py-10 text-center text-stone-400">Belum ada perintah kerja pada periode ini.</td></tr>
          </tbody>
        </table>
      </section>
    </template>

    <template v-if="props.mode === 'armada'">
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
        <div class="px-5 py-4 border-b border-stone-100">
          <h3 class="font-bold text-[14px]">Keandalan per alat</h3>
          <p class="text-[11px] text-stone-400">Diurutkan menurut jam henti terbanyak.</p>
        </div>
        <table class="min-w-full text-left text-[12px]">
          <thead><tr class="border-b border-stone-100 text-stone-400">
            <th class="px-5 py-3">Alat</th><th class="px-5 py-3">Kritikalitas</th>
            <th class="px-5 py-3 text-right">WO</th><th class="px-5 py-3 text-right">Kegagalan</th>
            <th class="px-5 py-3 text-right">Jam henti</th><th class="px-5 py-3 text-right">Menunggu</th>
            <th class="px-5 py-3 text-right">Biaya</th>
          </tr></thead>
          <tbody>
            <tr v-for="a in props.perAlat || []" :key="a.kode" class="border-b border-stone-50">
              <td class="px-5 py-3"><b>{{ a.kode }}</b><small class="block text-[10px] text-stone-400">{{ a.nama }}</small></td>
              <td class="px-5 py-3">{{ a.kritikalitas || '—' }}</td>
              <td class="px-5 py-3 text-right">{{ a.order }}</td>
              <td class="px-5 py-3 text-right">{{ a.kegagalan }}</td>
              <td class="px-5 py-3 text-right font-semibold">{{ angka(a.jamHenti, 1) }}</td>
              <td class="px-5 py-3 text-right" :class="a.jamMenunggu > a.jamHenti / 2 ? 'text-amber-700' : ''">{{ angka(a.jamMenunggu, 1) }}</td>
              <td class="px-5 py-3 text-right">{{ rupiah(a.biaya) }}</td>
            </tr>
            <tr v-if="!(props.perAlat || []).length"><td colspan="7" class="px-5 py-10 text-center text-stone-400">Belum ada perintah kerja yang tertaut ke alat.</td></tr>
          </tbody>
        </table>
      </section>
    </template>
  </div>
</template>
