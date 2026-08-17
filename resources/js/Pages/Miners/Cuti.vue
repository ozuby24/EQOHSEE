<script setup lang="ts">
/**
 * Cuti tahunan — jatah, pengajuan, dan sisanya.
 *
 * SALDO DITARUH DI ATAS PENGAJUAN, dan itu urutan yang disengaja.
 * Pertanyaan pertama yang dibawa orang ke halaman ini adalah "sisa saya
 * berapa"; daftar pengajuan menjawab pertanyaan kedua. Dasbor cuti yang
 * membuka dengan daftar pengajuan memaksa setiap orang menggulir untuk
 * mencari satu angka yang ia cari.
 *
 * SISA DIPECAH MENJADI DUA. "Terpakai" sudah pasti, "tertahan" masih
 * dapat batal — dan menyatukannya menjadi satu angka membuat orang yang
 * pengajuannya nanti ditolak mengira jatahnya sudah hilang.
 */
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Baris from './Baris.vue';
import { KEADAAN } from '../../Grafik/warna';

const props = usePage<any>().props as any;

const baris   = computed(() => props.baris ?? []);
const saldo   = computed(() => props.saldo ?? []);
const ringkas = computed(() => props.ringkas ?? {});

const buka = reactive({ form: false, jatah: false });

const f = useForm<Record<string, any>>({
  paspor_id: '', jenis: 'Tahunan', mulai: '', selesai: '', jumlah_hari: '',
  alamat_cuti: '', kontak: '', pengganti_id: '', alasan: '',
});

const fJatah = useForm<Record<string, any>>({
  paspor_id: '', tahun: props.tahun, jatah: 12, bawaan: 0, catatan: '',
});

/**
 * Mengusulkan jumlah hari dari selisih tanggal — inklusif kedua ujung.
 *
 * Diusulkan, tidak dipaksakan: yang tahu ada hari libur di tengahnya
 * adalah pengajunya, bukan layar ini.
 */
function usulkanHari() {
  if (!f.mulai || !f.selesai) return;

  const a = new Date(f.mulai).getTime();
  const b = new Date(f.selesai).getTime();
  if (Number.isNaN(a) || Number.isNaN(b) || b < a) return;

  f.jumlah_hari = Math.round((b - a) / 86400000) + 1;
}

function simpan() {
  f.post('/miners/cuti', {
    preserveScroll: true,
    onSuccess: () => { f.reset(); buka.form = false; },
  });
}

function simpanJatah() {
  fJatah.post('/miners/cuti/jatah', {
    preserveScroll: true, onSuccess: () => { buka.jatah = false; },
  });
}

function ajukan(id: number) {
  router.post(`/miners/cuti/${id}/ajukan`, {}, { preserveScroll: true });
}

function tinjau(id: number, aksi: 'setujui' | 'tolak' | 'tarik') {
  let alasan = '';
  if (aksi === 'tolak') {
    alasan = (prompt('Alasan penolakan:') ?? '').trim();
    if (!alasan) return;
  }
  router.post(`/miners/cuti/${id}/tinjau`, { aksi, alasan }, { preserveScroll: true });
}

function hapus(id: number, nama: string) {
  if (confirm(`Hapus pengajuan cuti ${nama}?`)) {
    router.delete(`/miners/cuti/${id}`, { preserveScroll: true });
  }
}

/** Merah hanya ketika sisanya benar-benar menipis. */
function warnaSisa(s: number, total: number): string {
  if (s <= 0)            return KEADAAN.gawat;
  if (s <= total * 0.25) return KEADAAN.ingat;

  return KEADAAN.baik;
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ props.subjudul }} · tahun {{ props.tahun }}</p>
      </div>
      <div class="flex gap-2">
        <Link href="/miners/dasbor" class="eq-btn-lain">Ringkasan</Link>
        <button type="button" class="eq-btn-lain" @click="buka.jatah = !buka.jatah">
          {{ buka.jatah ? 'Batal' : 'Atur jatah' }}
        </button>
        <button type="button" class="eq-btn-utama" @click="buka.form = !buka.form">
          {{ buka.form ? 'Batal' : 'Ajukan cuti' }}
        </button>
      </div>
    </section>

    <form v-if="buka.jatah"
          class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-5"
          @submit.prevent="simpanJatah">
      <select v-model="fJatah.paspor_id" required class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
        <option value="">Pilih pekerja…</option>
        <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">{{ o.nama }}</option>
      </select>
      <input v-model="fJatah.tahun" type="number" title="Tahun" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="fJatah.jatah" type="number" min="0" title="Jatah hari"
             placeholder="Jatah" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="fJatah.bawaan" type="number" min="0" title="Bawaan tahun lalu"
             placeholder="Bawaan" class="rounded-lg border-stone-200 text-[12px]">
      <button class="eq-btn-utama md:col-start-5" :disabled="fJatah.processing">Tetapkan</button>
    </form>

    <form v-if="buka.form"
          class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
          @submit.prevent="simpan">
      <select v-model="f.paspor_id" required class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
        <option value="">Pilih pekerja…</option>
        <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">
          {{ o.nama }}<span v-if="o.jabatan"> — {{ o.jabatan }}</span>
        </option>
      </select>
      <select v-model="f.jenis" class="rounded-lg border-stone-200 text-[12px]">
        <option v-for="j in (props.opsi?.jenisCuti ?? [])" :key="j">{{ j }}</option>
      </select>
      <input v-model="f.jumlah_hari" type="number" min="1" placeholder="Jumlah hari"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.mulai" type="date" required title="Mulai" @change="usulkanHari"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.selesai" type="date" required title="Selesai" @change="usulkanHari"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.alamat_cuti" placeholder="Alamat selama cuti" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.kontak" placeholder="Kontak darurat" class="rounded-lg border-stone-200 text-[12px]">
      <select v-model="f.pengganti_id" class="rounded-lg border-stone-200 text-[12px]">
        <option value="">Tanpa pengganti</option>
        <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">{{ o.nama }}</option>
      </select>
      <input v-model="f.alasan" placeholder="Alasan" class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
      <button class="eq-btn-utama" :disabled="f.processing">Simpan draf</button>

      <p v-for="(e, k) in f.errors" :key="k" class="text-[11px] text-red-600 md:col-span-4">{{ e }}</p>
    </form>

    <section class="grid gap-3 sm:grid-cols-3">
      <div v-for="k in [
             ['Sedang cuti', ringkas.sedangCuti ?? 0, KEADAAN.serius],
             ['Menunggu tinjauan', ringkas.menunggu ?? 0, KEADAAN.ingat],
             ['Hari terpakai tahun ini', ringkas.hariTerpakai ?? 0, KEADAAN.netral],
           ]" :key="k[0] as string"
           class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num"
           :style="{ color: Number(k[1]) ? (k[2] as string) : KEADAAN.netral }">{{ k[1] }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">{{ k[0] }}</p>
      </div>
    </section>

    <!-- saldo: pertanyaan pertama yang dibawa orang ke halaman ini -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <div class="p-5 pb-3">
        <h3 class="text-[14px] font-bold text-cam-ink">Sisa jatah {{ props.tahun }}</h3>
        <p class="text-[11.5px] text-stone-500 mt-0.5">
          "Tertahan" adalah cuti yang masih menunggu tinjauan — sudah dipotong dari sisa,
          tetapi masih dapat kembali bila ditolak.
        </p>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-y border-stone-100">
              <th class="px-5 py-2.5">Nama</th><th class="px-5 py-2.5">Jatah</th>
              <th class="px-5 py-2.5">Bawaan</th><th class="px-5 py-2.5">Terpakai</th>
              <th class="px-5 py-2.5">Tertahan</th><th class="px-5 py-2.5">Sisa</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in saldo" :key="s.id" class="border-b border-stone-50">
              <td class="px-5 py-2.5 font-semibold">
                <Link :href="`/miners/${s.id}`" class="text-cam-lime-deep">{{ s.nama }}</Link>
                <small class="block text-[10px] text-stone-400">{{ s.jabatan || '—' }}</small>
              </td>
              <td class="px-5 py-2.5 num">
                {{ s.jatah }}
                <!-- Jatah baku yang belum pernah ditetapkan siapa pun tidak
                     boleh terlihat sama dengan yang sudah disepakati. -->
                <small v-if="!s.diatur" class="text-[10px] text-stone-400"> baku</small>
              </td>
              <td class="px-5 py-2.5 num">{{ s.bawaan }}</td>
              <td class="px-5 py-2.5 num">{{ s.terpakai }}</td>
              <td class="px-5 py-2.5 num" :style="{ color: s.tertahan ? KEADAAN.ingat : undefined }">
                {{ s.tertahan }}
              </td>
              <td class="px-5 py-2.5 num font-bold" :style="{ color: warnaSisa(s.sisa, s.total) }">
                {{ s.sisa }}
              </td>
            </tr>
            <tr v-if="!saldo.length">
              <td colspan="6" class="px-5 py-10 text-center text-stone-400">Belum ada pekerja terdaftar.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Pengajuan cuti {{ props.tahun }}</h3>

      <ul v-if="baris.length" class="divide-y divide-stone-100">
        <Baris v-for="b in baris" :key="b.id"
               :status="b.status" :status-label="b.statusLabel"
               :dapat-diubah="b.dapatDiubah" :dapat-ditinjau="b.dapatDitinjau"
               :alasan-tolak="b.alasanTolak"
               :aktif="b.sedangCuti" aktif-label="Sedang cuti"
               @ajukan="ajukan(b.id)" @tinjau="a => tinjau(b.id, a)"
               @hapus="hapus(b.id, b.nama)">
          <p class="text-[12.5px] font-semibold text-cam-ink">
            <Link :href="`/miners/${b.pasporId}`" class="hover:text-cam-lime-deep">{{ b.nama }}</Link>
            <span class="text-[11px] font-normal text-stone-400">
              · {{ b.jenis }}
              <!-- Yang tidak memotong jatah dikatakan, supaya tidak
                   disangka lupa dihitung. -->
              <span v-if="!b.memotong">· tidak memotong jatah</span>
            </span>
          </p>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            {{ b.mulai }} → {{ b.selesai }} · {{ b.hari }} hari
            <span v-if="b.pengganti"> · digantikan {{ b.pengganti }}</span>
          </p>
          <p v-if="b.alasan" class="text-[11px] text-stone-400 mt-0.5">{{ b.alasan }}</p>
        </Baris>
      </ul>
      <p v-else class="text-[12px] py-8 text-center text-stone-400">
        Belum ada pengajuan cuti tahun {{ props.tahun }}.
      </p>
    </section>
  </div>
</template>
