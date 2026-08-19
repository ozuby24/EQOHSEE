<script setup lang="ts">
/**
 * Field break — giliran pulang pada pola kerja rotasi.
 *
 * Pertanyaan yang dijawab halaman ini bukan "berapa banyak field break
 * tahun ini" melainkan "siapa yang HARI INI tidak ada di lokasi" —
 * angka yang dipakai membagi pekerjaan besok pagi. Karena itu yang
 * sedang berlangsung diletakkan di atas, terpisah dari daftar
 * lengkapnya.
 *
 * FIELD BREAK BUKAN KETIDAKLAYAKAN, dan itu dikatakan di layar. Orang
 * yang sedang pulang tetap memenuhi syarat masuk; ia hanya sedang tidak
 * di sini. Tanpa kalimat itu, angka "6 orang pergi" pada layar
 * keselamatan mudah terbaca sebagai enam masalah.
 */
import { reactive, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Baris from './Baris.vue';
import { KEADAAN } from '../../Grafik/warna';
import Dialog from '../../Components/Dialog.vue';
import { useDialog } from '../../dialog';
const { dialog, tanya, minta, batal, lanjut } = useDialog();


const props = usePage<any>().props as any;

const baris   = computed(() => props.baris ?? []);
const pergi   = computed(() => baris.value.filter((b: any) => b.sedangPergi));
const ringkas = computed(() => props.ringkas ?? {});

const buka = reactive({ form: false });

const f = useForm<Record<string, any>>({
  paspor_id: '', pola: '8:2', jenis: 'Roster', mulai: '', selesai: '',
  lokasi_tujuan: '', pengganti_id: '', catatan: '',
});

function simpan() {
  f.post('/miners/field-break', {
    preserveScroll: true,
    onSuccess: () => { f.reset(); buka.form = false; },
  });
}

function ajukan(id: number) {
  router.post(`/miners/field-break/${id}/ajukan`, {}, { preserveScroll: true });
}

async function tinjau(id: number, aksi: 'setujui' | 'tolak' | 'tarik') {
  let alasan = '';
  if (aksi === 'tolak') {
    alasan = (await minta({ judul: 'Tolak pengajuan field break?', label: 'Alasan penolakan',
      jenis: 'panjang', min: 5, labelAksi: 'Tolak', nada: 'bahaya' }) ?? '').trim();
    if (!alasan) return;
  }
  router.post(`/miners/field-break/${id}/tinjau`, { aksi, alasan }, { preserveScroll: true });
}

async function hapus(id: number, nama: string) {
  if (await tanya(`Hapus field break ${nama}?`)) {
    router.delete(`/miners/field-break/${id}`, { preserveScroll: true });
  }
}

/** Mencatat kepulangan yang sebenarnya — boleh setelah disetujui. */
async function kembali(id: number, selesai: string) {
  const t = await minta({ judul: 'Catat kembali dari field break', label: 'Tanggal kembali sebenarnya',
    jenis: 'tanggal', nilai: selesai, labelAksi: 'Simpan' });
  if (!t) return;

  router.post(`/miners/field-break/${id}/kembali`, { kembali_aktual: t }, { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <div class="flex gap-2">
        <Link href="/miners/dasbor" class="eq-btn-lain">Ringkasan</Link>
        <button type="button" class="eq-btn-utama" @click="buka.form = !buka.form">
          {{ buka.form ? 'Batal' : 'Jadwalkan field break' }}
        </button>
      </div>
    </section>

    <form v-if="buka.form"
          class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
          @submit.prevent="simpan">
      <select v-model="f.paspor_id" required class="rounded-lg border-stone-200 text-[12px] md:col-span-2" aria-label="Paspor">
        <option value="">Pilih pekerja…</option>
        <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">
          {{ o.nama }}<span v-if="o.jabatan"> — {{ o.jabatan }}</span>
        </option>
      </select>
      <input v-model="f.pola" placeholder="Pola roster (8:2)" class="rounded-lg border-stone-200 text-[12px]">
      <select v-model="f.jenis" class="rounded-lg border-stone-200 text-[12px]" aria-label="Jenis">
        <option v-for="j in (props.opsi?.jenisFieldBreak ?? [])" :key="j">{{ j }}</option>
      </select>
      <input v-model="f.mulai" type="date" required title="Mulai" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.selesai" type="date" required title="Selesai" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.lokasi_tujuan" placeholder="Kota tujuan" class="rounded-lg border-stone-200 text-[12px]">
      <select v-model="f.pengganti_id" class="rounded-lg border-stone-200 text-[12px]" aria-label="Pengganti">
        <option value="">Tanpa pengganti</option>
        <option v-for="o in (props.opsi?.orang ?? [])" :key="o.id" :value="o.id">{{ o.nama }}</option>
      </select>
      <input v-model="f.catatan" placeholder="Catatan" class="rounded-lg border-stone-200 text-[12px] md:col-span-3">
      <button class="eq-btn-utama" :disabled="f.processing">Simpan draf</button>

      <p v-for="(e, k) in f.errors" :key="k" class="text-[11px] text-red-600 md:col-span-4">{{ e }}</p>
    </form>

    <!-- siapa yang tidak ada di lokasi hari ini -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <div class="flex items-start justify-between gap-3 mb-3">
        <div>
          <h3 class="text-[14px] font-bold text-cam-ink">Tidak ada di lokasi hari ini</h3>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            Mereka tetap memenuhi syarat masuk — sedang tidak di sini, bukan bermasalah.
          </p>
        </div>
        <span class="text-[22px] font-bold leading-none num shrink-0"
              :style="{ color: pergi.length ? KEADAAN.serius : KEADAAN.netral }">
          {{ pergi.length }}
        </span>
      </div>

      <ul v-if="pergi.length" class="divide-y divide-stone-100">
        <li v-for="b in pergi" :key="b.id" class="py-2.5 flex flex-wrap items-center gap-3">
          <Link :href="`/miners/${b.pasporId}`"
                class="text-[12.5px] font-semibold text-cam-ink hover:text-cam-lime-deep truncate w-44">
            {{ b.nama }}
          </Link>
          <span class="text-[11.5px] text-stone-500">{{ b.jenis }}<span v-if="b.pola"> · {{ b.pola }}</span></span>
          <span class="text-[11.5px] text-stone-500 truncate">{{ b.lokasi || '—' }}</span>
          <span class="text-[11.5px] ml-auto" :style="{ color: KEADAAN.serius }">
            kembali {{ b.kembali || b.selesai }}
          </span>
          <span v-if="b.pengganti" class="text-[11px] text-stone-400">
            digantikan {{ b.pengganti }}
          </span>
        </li>
      </ul>
      <p v-else class="text-[12px] py-4 text-center text-stone-400">
        Seluruh pekerja sedang berada di lokasi.
      </p>
    </section>

    <section class="grid gap-3 sm:grid-cols-3">
      <div v-for="k in [
             ['Sedang pergi', ringkas.sedangPergi ?? 0, KEADAAN.serius],
             ['Menunggu tinjauan', ringkas.menunggu ?? 0, KEADAAN.ingat],
             ['Kembali terlambat', ringkas.telat ?? 0, KEADAAN.gawat],
           ]" :key="k[0] as string"
           class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num"
           :style="{ color: Number(k[1]) ? (k[2] as string) : KEADAAN.netral }">{{ k[1] }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">{{ k[0] }}</p>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Seluruh jadwal</h3>

      <ul v-if="baris.length" class="divide-y divide-stone-100">
        <Baris v-for="b in baris" :key="b.id" tag="li"
               :status="b.status" :status-label="b.statusLabel"
               :dapat-diubah="b.dapatDiubah" :dapat-ditinjau="b.dapatDitinjau"
               :alasan-tolak="b.alasanTolak"
               :aktif="b.sedangPergi" aktif-label="Sedang pergi"
               @ajukan="ajukan(b.id)" @tinjau="a => tinjau(b.id, a)"
               @hapus="hapus(b.id, b.nama)">
          <p class="text-[12.5px] font-semibold text-cam-ink">
            <Link :href="`/miners/${b.pasporId}`" class="hover:text-cam-lime-deep">{{ b.nama }}</Link>
            <span class="text-[11px] font-normal text-stone-400">
              · {{ b.jenis }}<span v-if="b.pola"> · {{ b.pola }}</span>
            </span>
          </p>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            {{ b.mulai }} → {{ b.selesai }} · {{ b.hari }} hari
            <span v-if="b.lokasi"> · {{ b.lokasi }}</span>
            <span v-if="b.pengganti"> · digantikan {{ b.pengganti }}</span>
          </p>
          <p v-if="b.kembali" class="text-[11px] mt-0.5"
             :style="{ color: b.telat ? KEADAAN.gawat : KEADAAN.baik }">
            Kembali {{ b.kembali }}<span v-if="b.telat"> — terlambat {{ b.telat }} hari</span>
          </p>

          <template #aksi>
            <button v-if="b.status === 'disetujui' && !b.kembali" type="button"
                    class="text-[11px] font-semibold text-cam-lime-deep"
                    @click="kembali(b.id, b.selesai)">Catat kembali</button>
          </template>
        </Baris>
      </ul>
      <p v-else class="text-[12px] py-8 text-center text-stone-400">Belum ada jadwal field break.</p>
    </section>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>
