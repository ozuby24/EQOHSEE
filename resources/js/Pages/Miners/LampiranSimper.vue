<script setup lang="ts">
/**
 * "Attachment Simper" — satu baris per unit yang diujikan.
 *
 * MENGAPA TABEL, DAN MENGAPA PER UNIT.
 *
 * SIMPER dinilai per unit, bukan per orang. Seorang operator lulus
 * untuk Excavator PC 200 dan belum lulus untuk PC 500; keduanya satu
 * kartu, dua baris, dan dua berkas uji yang sama sekali berlainan.
 * Susunan kartu-per-unit akan membuat layar sepanjang lima layar untuk
 * pengemudi yang membawa lima unit — dan yang dicari pembacanya selalu
 * satu baris: unit mana yang lembar praktiknya belum masuk.
 *
 * Susunan kolomnya mengikuti D'Best persis, sampai urutannya:
 * Authority, Unit, Type/Merk, Nilai P2H, Nilai Praktek, lalu keempat
 * berkasnya. Yang diubah hanya wujud berkasnya — di sana ikon folder
 * yang selalu ada meski berkasnya tidak, di sini tombol yang hanya ada
 * bila berkasnya sungguh ada, dan tanda "—" bila belum.
 *
 * ── BERKAS DIUNGGAH SAAT BARISNYA DISUSUN ──
 *
 * Keempatnya diunggah lebih dahulu, lalu jalurnya ikut terkirim
 * bersama baris unitnya. Itu memang dua langkah, dan sengaja: unggahan
 * yang menumpang pada penyimpanan barisnya akan hilang seluruhnya
 * ketika validasi baris itu gagal — empat berkas yang harus dipilih
 * ulang karena satu nilai salah ketik.
 */
import { computed, reactive, ref } from 'vue';
import { KEADAAN } from '../../Grafik/warna';
import { unggahLampiran } from '../../unggah';

type Berkas = { kolom: string; label: string; wajib: boolean; ada: boolean; url: string | null };

const props = defineProps<{
  unit: Array<{
    id: number; authority: string | null; unit: string; typeMerk: string | null;
    nilaiP2h: number | null; nilaiPraktek: number | null; lulus: boolean;
    berkas: Berkas[]; berkasKurang: string[];
  }>;

  /** Katalog berkas uji, dipakai menyusun medan unggah baris baru. */
  jenisBerkas: Berkas[];

  opsiAuthority: string[];
  opsiUnit: Array<{ id: number; unit: string; kategori?: string | null }>;

  bisaUbah?: boolean;
  bisaHapus?: boolean;
  sibuk?: boolean;
}>();

const emit = defineEmits<{
  (e: 'tambah', isi: Record<string, any>): void;
  (e: 'hapus', id: number, nama: string): void;
}>();

const bukaForm = ref(false);

const kosong = () => ({
  ko_unit_master_id: '' as string | number,
  authority: '',
  jenis_unit: '',
  type_merk: '',
  nilai_p2h: '' as string | number,
  nilai_praktek: '' as string | number,
  berkas_rambu: '',
  berkas_teori: '',
  hasil_praktek: '',
  evaluasi: '',
});

const isi = reactive<Record<string, any>>(kosong());

const naik  = ref<Record<string, boolean>>({});
const galat = ref<Record<string, string>>({});
const nama  = ref<Record<string, string>>({});

async function pilih(kolom: string, ev: Event) {
  const f = (ev.target as HTMLInputElement).files?.[0];
  if (!f) return;

  naik.value[kolom] = true;
  delete galat.value[kolom];

  try {
    const j = await unggahLampiran(kolom, f);
    isi[kolom] = j.jalur;
    nama.value[kolom] = j.nama;
  } catch (e: any) {
    galat.value[kolom] = e?.message ?? 'Gagal mengunggah berkas.';
  } finally {
    naik.value[kolom] = false;
  }
}

/**
 * Sebutan unit yang akan tersimpan, dipakai menahan baris tanpa nama.
 *
 * Master unit dipakai bila dipilih; kalau tidak, teks bebas. Unit sewa
 * dan unit subkontraktor kerap belum terdaftar, dan menolak barisnya
 * berarti orang yang sudah diuji tidak dapat dicatat sama sekali.
 */
const sebutanUnit = computed(() => {
  const m = props.opsiUnit.find((u) => String(u.id) === String(isi.ko_unit_master_id));
  return m?.unit || String(isi.jenis_unit || '').trim();
});

const bolehSimpan = computed(() => sebutanUnit.value.length > 0
  && !Object.values(naik.value).some(Boolean));

function simpan() {
  if (!bolehSimpan.value) return;

  const kirim: Record<string, any> = {};

  /* Yang kosong dikirim sebagai null, bukan sebagai "". Kolom nilai
     bertipe integer: string kosong menjadi 0 di basis data, dan nol
     berarti DIUJI LALU GAGAL — berbeda dari belum diuji. */
  for (const [k, v] of Object.entries(isi)) {
    kirim[k] = v === '' || v === null ? null : v;
  }

  emit('tambah', kirim);

  Object.assign(isi, kosong());
  nama.value = {};
  galat.value = {};
  bukaForm.value = false;
}

function warnaNilai(n: number | null): string {
  if (n === null || n === undefined) return '#A8A29E';
  return n >= 70 ? KEADAAN.baik : KEADAAN.gawat;
}
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
    <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <h3 class="text-[13.5px] font-bold text-cam-ink">Attachment Simper</h3>
        <p class="text-[11px] text-stone-500 mt-0.5">
          Satu baris per unit yang diujikan. Bertanda
          <span class="text-red-600 font-bold">*</span> wajib sebelum kartunya dapat diajukan.
        </p>
      </div>

      <span v-if="bisaUbah" class="inline-flex shrink-0">
        <button type="button" class="eq-btn-utama" @click="bukaForm = !bukaForm">
          {{ bukaForm ? 'Tutup' : '+ Tambah unit' }}
        </button>
      </span>
    </header>

    <!-- ══════════ daftar unit ══════════ -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-left text-[11.5px]">
        <thead>
          <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
            <th class="px-3 py-2 font-semibold w-10">No</th>
            <th class="px-3 py-2 font-semibold">Authority</th>
            <th class="px-3 py-2 font-semibold">Unit</th>
            <th class="px-3 py-2 font-semibold">Type/Merk</th>
            <th class="px-3 py-2 font-semibold text-right whitespace-nowrap">Nilai P2H</th>
            <th class="px-3 py-2 font-semibold text-right whitespace-nowrap">Nilai Praktek</th>
            <th v-for="b in jenisBerkas" :key="b.kolom" class="px-3 py-2 font-semibold whitespace-nowrap">
              {{ b.label }}<span v-if="b.wajib" class="text-red-600">*</span>
            </th>
            <th class="px-3 py-2 font-semibold">Status</th>
            <th v-if="bisaHapus" class="px-3 py-2 font-semibold w-10"></th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(u, i) in unit" :key="u.id" class="border-b border-stone-100 align-top">
            <td class="px-3 py-2.5 num text-stone-400">{{ i + 1 }}</td>
            <td class="px-3 py-2.5 num font-bold text-cam-ink">{{ u.authority || '—' }}</td>
            <td class="px-3 py-2.5 font-semibold text-cam-ink whitespace-nowrap">{{ u.unit }}</td>
            <td class="px-3 py-2.5 text-stone-500">{{ u.typeMerk || '—' }}</td>
            <td class="px-3 py-2.5 num text-right font-semibold" :style="{ color: warnaNilai(u.nilaiP2h) }">
              {{ u.nilaiP2h ?? '—' }}
            </td>
            <td class="px-3 py-2.5 num text-right font-semibold" :style="{ color: warnaNilai(u.nilaiPraktek) }">
              {{ u.nilaiPraktek ?? '—' }}
            </td>

            <!-- Tombol buka hanya bila berkasnya sungguh ada. Ikon yang
                 selalu tergambar membuat baris tanpa berkas tampak sama
                 persis dengan baris yang lengkap. -->
            <td v-for="b in u.berkas" :key="b.kolom" class="px-3 py-2.5 whitespace-nowrap">
              <a v-if="b.url" :href="b.url" target="_blank" rel="noopener"
                 class="inline-block rounded-md border border-stone-200 px-2 py-1 text-[10.5px] font-bold text-cam-ink hover:bg-stone-50">
                Buka
              </a>
              <span v-else class="text-[10.5px]" :class="b.wajib ? 'text-amber-700 font-semibold' : 'text-stone-300'">
                {{ b.wajib ? 'belum ada' : '—' }}
              </span>
            </td>

            <td class="px-3 py-2.5">
              <span class="inline-block rounded px-1.5 py-0.5 text-[10px] font-bold whitespace-nowrap"
                    :style="u.lulus
                      ? { background: '#DCFCE7', color: '#15803D' }
                      : { background: '#FEF3C7', color: '#92400E' }">
                {{ u.lulus ? 'Lulus' : 'Belum lulus' }}
              </span>
              <p v-if="u.berkasKurang?.length" class="text-[10px] text-amber-700 mt-1">
                kurang: {{ u.berkasKurang.join(', ') }}
              </p>
            </td>

            <td v-if="bisaHapus" class="px-3 py-2.5">
              <button type="button" class="text-red-600 text-[11px] font-semibold"
                      @click="emit('hapus', u.id, u.unit)">Hapus</button>
            </td>
          </tr>

          <tr v-if="!unit.length">
            <td :colspan="7 + jenisBerkas.length + (bisaHapus ? 1 : 0)"
                class="px-3 py-6 text-center text-[12px] text-stone-400">
              Belum ada unit yang diujikan.
              <span v-if="bisaUbah">Tekan “+ Tambah unit” untuk mencatat yang pertama.</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══════════ formulir baris baru ══════════ -->
    <form v-if="bisaUbah && bukaForm" class="px-5 py-4 border-t border-stone-100 grid gap-3"
          @submit.prevent="simpan">

      <div class="grid gap-3 md:grid-cols-3">
        <label class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">
            Authority<span class="text-red-600">*</span>
          </span>
          <select v-model="isi.authority" class="rounded-lg border-stone-200 text-[12px]">
            <option value="">—</option>
            <option v-for="a in opsiAuthority" :key="a" :value="a">{{ a }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">
            Unit<span class="text-red-600">*</span>
          </span>
          <select v-model="isi.ko_unit_master_id" class="rounded-lg border-stone-200 text-[12px]">
            <option value="">Pilih dari master unit…</option>
            <option v-for="u in opsiUnit" :key="u.id" :value="u.id">{{ u.unit }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">Type Unit</span>
          <input v-model="isi.jenis_unit" class="rounded-lg border-stone-200 text-[12px]"
                 placeholder="Bila unitnya belum terdaftar">
        </label>
      </div>

      <div class="grid gap-3 md:grid-cols-3">
        <label class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">Type/Merk</span>
          <input v-model="isi.type_merk" class="rounded-lg border-stone-200 text-[12px]"
                 placeholder="Komatsu PC 200">
        </label>

        <label class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">Nilai P2H</span>
          <input v-model="isi.nilai_p2h" type="number" min="0" max="100"
                 class="rounded-lg border-stone-200 text-[12px]" placeholder="0–100">
        </label>

        <label class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">Nilai Praktek</span>
          <input v-model="isi.nilai_praktek" type="number" min="0" max="100"
                 class="rounded-lg border-stone-200 text-[12px]" placeholder="0–100">
        </label>
      </div>

      <div class="grid gap-3 md:grid-cols-4">
        <label v-for="b in jenisBerkas" :key="b.kolom" class="grid gap-1">
          <span class="text-[10px] uppercase tracking-wide text-stone-400">
            {{ b.label }}<span v-if="b.wajib" class="text-red-600">*</span>
          </span>

          <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                 :disabled="naik[b.kolom]"
                 class="w-full rounded-lg border-stone-200 text-[11.5px]"
                 :aria-label="b.label"
                 @change="pilih(b.kolom, $event)">

          <span v-if="naik[b.kolom]" class="text-[10.5px] text-stone-400">Mengunggah…</span>
          <span v-else-if="galat[b.kolom]" class="text-[10.5px] text-red-600">{{ galat[b.kolom] }}</span>
          <span v-else-if="nama[b.kolom]" class="text-[10.5px] truncate" :style="{ color: KEADAAN.baik }"
                :title="nama[b.kolom]">
            {{ nama[b.kolom] }}
          </span>
        </label>
      </div>

      <div class="flex items-center gap-3">
        <span class="inline-flex">
          <button class="eq-btn-utama" :disabled="!bolehSimpan || sibuk">Simpan unit</button>
        </span>
        <p v-if="!sebutanUnit" class="text-[11px] text-stone-400">
          Pilih unit dari master atau ketik Type Unit-nya lebih dahulu.
        </p>
      </div>
    </form>
  </section>
</template>
