<script setup lang="ts">
/**
 * Authority Passport — susunan D'Best: "Detail" lalu "Attachment
 * Sertifikasi".
 *
 * MENGAPA SUSUNANNYA BERUBAH.
 *
 * Sebelum ini sertifikat kompetensi tergambar sebagai satu daftar
 * datar dengan formulir enam medan menempel di bawahnya. Yang hilang
 * di situ bukan kerapian melainkan JAWABAN atas pertanyaan yang dibawa
 * orang saat membukanya: "berkas siapa ini, dan apa yang masih kurang
 * dari saya". Identitas orangnya hanya ada di kepala halaman, jauh di
 * atas; kelengkapannya tidak disebut sama sekali.
 *
 * Dua kartu menjawab keduanya sekaligus. Kartu kiri menyebut siapa —
 * seluruhnya dibaca, sebab tiap medannya punya sumber sendiri. Kartu
 * kanan satu-satunya tempat ada isian, dan isinya persis pertanyaan
 * kedua.
 *
 * ── BERKASNYA DIUNGGAH, BUKAN DINAMAI ──
 *
 * Kolom `berkas` sudah ada di tabel ini sejak ia lahir, dan selama itu
 * tidak pernah dapat diisi: formulirnya tidak punya medan, aturan
 * validasinya tidak menyebutkannya. Yang tersimpan karena itu hanya
 * NAMA sertifikatnya — dan nama sertifikat tidak dapat dibedakan dari
 * sertifikat yang tidak pernah ada. Sertifikat kompetensi adalah dasar
 * seseorang boleh menjadi pengawas operasional; auditor yang
 * memintanya meminta lembarnya, bukan namanya.
 */
import { computed, reactive, ref } from 'vue';
import { KEADAAN } from '../../Grafik/warna';
import { unggahLampiran } from '../../unggah';
import KartuDetail from './KartuDetail.vue';

type Berkas = { kolom: string; label: string; wajib: boolean; ada: boolean; url: string | null };

const props = defineProps<{
  profil: Array<{ label: string; nilai: string | null }>;

  sertifikat: Array<{
    id: number; nama: string; lembaga: string | null; nomor: string | null;
    tglTerbit: string | null; tglExpired: string | null;
    keadaan: string; keterangan: string; dariLms: boolean; berkas: string | null;
  }>;

  /** Katalog berkas sertifikat — satu medan, tetapi datang dari katalog. */
  jenisBerkas: Berkas[];

  opsiKompetensi: Array<{ id: number; nama: string; lembaga?: string | null }>;
  warna: Record<string, string>;

  sibuk?: boolean;
}>();

const emit = defineEmits<{
  (e: 'tambah', isi: Record<string, any>): void;
  (e: 'hapus', id: number, nama: string): void;
}>();

const bukaForm = ref(false);

const kosong = () => ({
  kompetensi_jenis_id: '' as string | number,
  nama: '',
  lembaga: '',
  nomor: '',
  tgl_terbit: '',
  tgl_expired: '',
  berkas: '',
});

const isi = reactive<Record<string, any>>(kosong());

const naik  = ref<Record<string, boolean>>({});
const galat = ref<Record<string, string>>({});
const namaBerkas = ref<Record<string, string>>({});

/**
 * Memilih jenis kompetensi mengisi nama dan lembaganya.
 *
 * Mengisi, bukan mengunci. Nama yang sudah tertulis di lembarnya tidak
 * selalu sama persis dengan nama di master, dan mengunci medannya
 * membuat sertifikat yang bunyinya berbeda tidak dapat dicatat apa
 * adanya.
 */
function pilihJenis() {
  const j = props.opsiKompetensi.find((x: any) => String(x.id) === String(isi.kompetensi_jenis_id));
  if (j) { isi.nama = j.nama; isi.lembaga = j.lembaga ?? ''; }
}

async function pilihBerkas(kolom: string, ev: Event) {
  const f = (ev.target as HTMLInputElement).files?.[0];
  if (!f) return;

  naik.value[kolom] = true;
  delete galat.value[kolom];

  try {
    const j = await unggahLampiran(kolom, f);
    isi[kolom] = j.jalur;
    namaBerkas.value[kolom] = j.nama;
  } catch (e: any) {
    galat.value[kolom] = e?.message ?? 'Gagal mengunggah berkas.';
  } finally {
    naik.value[kolom] = false;
  }
}

const bolehSimpan = computed(() => String(isi.nama || '').trim().length > 0
  && !Object.values(naik.value).some(Boolean));

function simpan() {
  if (!bolehSimpan.value) return;

  const kirim: Record<string, any> = {};
  for (const [k, v] of Object.entries(isi)) kirim[k] = v === '' ? null : v;

  emit('tambah', kirim);

  Object.assign(isi, kosong());
  namaBerkas.value = {};
  galat.value = {};
  bukaForm.value = false;
}

/** Sertifikat tanpa lembarnya — disebut di muka, bukan saat diaudit. */
const tanpaBerkas = computed(() => props.sertifikat.filter((s) => !s.berkas).length);
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-[.85fr_1.15fr] items-start">

    <!-- ══════════ DETAIL ══════════ -->
    <KartuDetail judul="Detail Authority" :profil="profil" />

    <!-- ══════════ ATTACHMENT SERTIFIKASI ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
          <h3 class="text-[13.5px] font-bold text-cam-ink">Attachment Sertifikasi</h3>
          <p class="text-[11px] text-stone-500 mt-0.5">
            Kompetensi yang dipegang orang ini, beserta lembarnya.
          </p>
        </div>

        <span class="inline-flex shrink-0">
          <button type="button" class="eq-btn-utama" @click="bukaForm = !bukaForm">
            {{ bukaForm ? 'Tutup' : '+ Tambah sertifikat' }}
          </button>
        </span>
      </header>

      <p v-if="tanpaBerkas" class="mx-5 mt-4 rounded-xl px-3.5 py-2.5 text-[11.5px]"
         style="background:#FEF3C7;color:#92400E">
        <b>{{ tanpaBerkas }}</b> sertifikat belum ada lembarnya — yang tercatat baru namanya.
      </p>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[11.5px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
              <th class="px-3 py-2 font-semibold w-10">No</th>
              <th class="px-3 py-2 font-semibold">Sertifikasi</th>
              <th class="px-3 py-2 font-semibold">Lembaga</th>
              <th class="px-3 py-2 font-semibold">Nomor</th>
              <th class="px-3 py-2 font-semibold whitespace-nowrap">File</th>
              <th class="px-3 py-2 font-semibold whitespace-nowrap">Expired</th>
              <th class="px-3 py-2 font-semibold w-10"></th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="(s, i) in sertifikat" :key="s.id" class="border-b border-stone-100">
              <td class="px-3 py-2.5 num text-stone-400">{{ i + 1 }}</td>

              <td class="px-3 py-2.5">
                <span class="font-semibold text-cam-ink">{{ s.nama }}</span>
                <span v-if="s.dariLms" class="text-[10px] text-stone-400"> · dari LMS</span>
              </td>

              <td class="px-3 py-2.5 text-stone-500">{{ s.lembaga || '—' }}</td>
              <td class="px-3 py-2.5 num text-stone-500">{{ s.nomor || '—' }}</td>

              <td class="px-3 py-2.5 whitespace-nowrap">
                <a v-if="s.berkas" :href="s.berkas" target="_blank" rel="noopener"
                   class="inline-block rounded-md border border-stone-200 px-2 py-1 text-[10.5px] font-bold text-cam-ink hover:bg-stone-50">
                  Buka
                </a>
                <span v-else class="text-[10.5px] text-amber-700 font-semibold">belum ada</span>
              </td>

              <td class="px-3 py-2.5 whitespace-nowrap">
                <span class="font-semibold" :style="{ color: warna[s.keadaan] }">{{ s.keterangan }}</span>
                <span v-if="s.tglExpired" class="text-[10px] text-stone-400 block num">{{ s.tglExpired }}</span>
              </td>

              <td class="px-3 py-2.5">
                <button type="button" class="text-red-600 text-[11px] font-semibold"
                        @click="emit('hapus', s.id, s.nama)">Hapus</button>
              </td>
            </tr>

            <tr v-if="!sertifikat.length">
              <td colspan="7" class="px-3 py-6 text-center text-[12px] text-stone-400">
                Belum ada sertifikat tercatat.
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ══════════ formulir sertifikat baru ══════════ -->
      <form v-if="bukaForm" class="px-5 py-4 border-t border-stone-100 grid gap-3"
            @submit.prevent="simpan">

        <div class="grid gap-3 md:grid-cols-2">
          <label class="grid gap-1">
            <span class="text-[10px] uppercase tracking-wide text-stone-400">
              Nama Sertifikasi<span class="text-red-600">*</span>
            </span>
            <select v-model="isi.kompetensi_jenis_id" class="rounded-lg border-stone-200 text-[12px]"
                    @change="pilihJenis">
              <option value="">Pilih jenis kompetensi…</option>
              <option v-for="j in opsiKompetensi" :key="j.id" :value="j.id">
                {{ j.nama }}<template v-if="j.lembaga"> — {{ j.lembaga }}</template>
              </option>
            </select>
            <input v-model="isi.nama" class="rounded-lg border-stone-200 text-[12px]"
                   placeholder="atau ketik namanya sendiri" aria-label="Nama sertifikasi">
          </label>

          <label class="grid gap-1">
            <span class="text-[10px] uppercase tracking-wide text-stone-400">
              Expired Sertifikat<span class="text-red-600">*</span>
            </span>
            <input v-model="isi.tgl_expired" type="date" class="rounded-lg border-stone-200 text-[12px]">
            <span class="text-[10.5px] text-stone-400">
              Dibiarkan kosong, sertifikatnya terbaca “tanpa tanggal” — bukan “masih berlaku”.
            </span>
          </label>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <label class="grid gap-1">
            <span class="text-[10px] uppercase tracking-wide text-stone-400">Lembaga</span>
            <input v-model="isi.lembaga" class="rounded-lg border-stone-200 text-[12px]">
          </label>

          <label class="grid gap-1">
            <span class="text-[10px] uppercase tracking-wide text-stone-400">No. Sertifikat</span>
            <input v-model="isi.nomor" class="rounded-lg border-stone-200 text-[12px]">
          </label>

          <label class="grid gap-1">
            <span class="text-[10px] uppercase tracking-wide text-stone-400">Tanggal terbit</span>
            <input v-model="isi.tgl_terbit" type="date" class="rounded-lg border-stone-200 text-[12px]">
          </label>
        </div>

        <label v-for="b in jenisBerkas" :key="b.kolom" class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">
            {{ b.label }}<span v-if="b.wajib" class="text-red-600">*</span>
          </span>

          <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                 :disabled="naik[b.kolom]"
                 class="w-full rounded-lg border-stone-200 text-[11.5px]"
                 :aria-label="b.label"
                 @change="pilihBerkas(b.kolom, $event)">

          <span v-if="naik[b.kolom]" class="text-[10.5px] text-stone-400">Mengunggah…</span>
          <span v-else-if="galat[b.kolom]" class="text-[10.5px] text-red-600">{{ galat[b.kolom] }}</span>
          <span v-else-if="namaBerkas[b.kolom]" class="text-[10.5px] truncate"
                :style="{ color: KEADAAN.baik }" :title="namaBerkas[b.kolom]">
            {{ namaBerkas[b.kolom] }}
          </span>
        </label>

        <div class="flex items-center gap-3">
          <span class="inline-flex">
            <button class="eq-btn-utama" :disabled="!bolehSimpan || sibuk">Simpan sertifikat</button>
          </span>
          <p v-if="!String(isi.nama || '').trim()" class="text-[11px] text-stone-400">
            Pilih jenis kompetensinya atau ketik namanya lebih dahulu.
          </p>
        </div>
      </form>
    </section>
  </div>
</template>
