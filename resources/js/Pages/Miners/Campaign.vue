<script setup lang="ts">
/**
 * Campaign keselamatan — poster, artikel, video, toolbox.
 *
 * JANGKAUAN YANG BELUM DIISI TIDAK DIGAMBAR SEBAGAI NOL. Keduanya
 * terlihat sama pada angka, dan artinya berlawanan: nol berarti
 * campaign yang tidak sampai ke siapa pun, kosong berarti belum ada
 * yang menghitung. Menyamakannya membuat pencatatan yang tertinggal
 * terbaca sebagai program yang gagal — dan yang diambil sebagai
 * kesimpulan biasanya yang kedua.
 */
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Baris from './Baris.vue';
import { KEADAAN } from '../../Grafik/warna';

const props = usePage<any>().props as any;

const baris   = computed(() => props.baris ?? []);
const tayang  = computed(() => baris.value.filter((b: any) => b.tayang));
const ringkas = computed(() => props.ringkas ?? {});

const buka = reactive({ form: false });

const f = useForm<Record<string, any>>({
  judul: '', jenis: 'Poster', tema: '', mulai: '', selesai: '',
  sasaran: '', ringkasan: '', isi: '',
});

function simpan() {
  f.post('/miners/campaign', {
    preserveScroll: true,
    onSuccess: () => { f.reset(); buka.form = false; },
  });
}

function ajukan(id: number) {
  router.post(`/miners/campaign/${id}/ajukan`, {}, { preserveScroll: true });
}

function tinjau(id: number, aksi: 'setujui' | 'tolak' | 'tarik') {
  let alasan = '';
  if (aksi === 'tolak') {
    alasan = (prompt('Alasan penolakan:') ?? '').trim();
    if (!alasan) return;
  }
  router.post(`/miners/campaign/${id}/tinjau`, { aksi, alasan }, { preserveScroll: true });
}

function hapus(id: number, judul: string) {
  if (confirm(`Hapus campaign "${judul}"?`)) {
    router.delete(`/miners/campaign/${id}`, { preserveScroll: true });
  }
}

function catatJangkauan(id: number, kini: number | null) {
  const n = prompt('Berapa orang yang menerima campaign ini?', String(kini ?? ''));
  if (n === null || n.trim() === '') return;

  router.post(`/miners/campaign/${id}/jangkauan`, { jangkauan: Number(n) }, { preserveScroll: true });
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
          {{ buka.form ? 'Batal' : 'Campaign baru' }}
        </button>
      </div>
    </section>

    <form v-if="buka.form"
          class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
          @submit.prevent="simpan">
      <input v-model="f.judul" required placeholder="Judul campaign"
             class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
      <select v-model="f.jenis" class="rounded-lg border-stone-200 text-[12px]">
        <option v-for="j in (props.opsi?.jenisCampaign ?? [])" :key="j">{{ j }}</option>
      </select>
      <input v-model="f.tema" placeholder="Tema" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.mulai" type="date" required title="Mulai tayang"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.selesai" type="date" title="Selesai (kosongkan bila terus berjalan)"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.sasaran" placeholder="Sasaran (departemen / area)"
             class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
      <textarea v-model="f.ringkasan" rows="2" placeholder="Ringkasan"
                class="rounded-lg border-stone-200 text-[12px] md:col-span-3"></textarea>
      <button class="eq-btn-utama self-start" :disabled="f.processing">Simpan draf</button>

      <p v-for="(e, k) in f.errors" :key="k" class="text-[11px] text-red-600 md:col-span-4">{{ e }}</p>
    </form>

    <section class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num"
           :style="{ color: tayang.length ? KEADAAN.baik : KEADAAN.netral }">{{ tayang.length }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">Sedang tayang</p>
      </div>
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num"
           :style="{ color: (ringkas.menunggu ?? 0) ? KEADAAN.ingat : KEADAAN.netral }">
          {{ ringkas.menunggu ?? 0 }}
        </p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">Menunggu tinjauan</p>
      </div>
      <div class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num text-cam-ink">{{ ringkas.jangkauan ?? 0 }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">
          Orang terjangkau
          <!-- Yang belum dihitung disebut jumlahnya, bukan dibulatkan
               diam-diam menjadi nol. -->
          <span v-if="ringkas.tanpaJangkauan" :style="{ color: KEADAAN.ingat }">
            · {{ ringkas.tanpaJangkauan }} campaign belum dihitung
          </span>
        </p>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="text-[14px] font-bold text-cam-ink mb-1">Seluruh campaign</h3>

      <ul v-if="baris.length" class="divide-y divide-stone-100">
        <Baris v-for="b in baris" :key="b.id"
               :status="b.status" :status-label="b.statusLabel"
               :dapat-diubah="b.dapatDiubah" :dapat-ditinjau="b.dapatDitinjau"
               :alasan-tolak="b.alasanTolak"
               :aktif="b.tayang" aktif-label="Sedang tayang"
               @ajukan="ajukan(b.id)" @tinjau="a => tinjau(b.id, a)"
               @hapus="hapus(b.id, b.judul)">
          <p class="text-[12.5px] font-semibold text-cam-ink">
            {{ b.judul }}
            <span class="text-[11px] font-normal text-stone-400">
              · {{ b.jenis }}<span v-if="b.tema"> · {{ b.tema }}</span>
            </span>
          </p>
          <p class="text-[11.5px] text-stone-500 mt-0.5">
            {{ b.mulai }} → {{ b.selesai || 'terus berjalan' }}
            <span v-if="b.sasaran"> · {{ b.sasaran }}</span>
          </p>
          <p v-if="b.ringkasan" class="text-[11px] text-stone-400 mt-0.5">{{ b.ringkasan }}</p>
          <p class="text-[11px] mt-0.5"
             :style="{ color: b.jangkauan === null ? KEADAAN.ingat : KEADAAN.netral }">
            {{ b.jangkauan === null ? 'Jangkauan belum dihitung' : `Menjangkau ${b.jangkauan} orang` }}
          </p>

          <template #aksi>
            <button v-if="b.status === 'disetujui'" type="button"
                    class="text-[11px] font-semibold text-cam-lime-deep"
                    @click="catatJangkauan(b.id, b.jangkauan)">Catat jangkauan</button>
          </template>
        </Baris>
      </ul>
      <p v-else class="text-[12px] py-8 text-center text-stone-400">Belum ada campaign.</p>
    </section>
  </div>
</template>
