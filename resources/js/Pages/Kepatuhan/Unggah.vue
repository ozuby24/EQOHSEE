<script setup lang="ts">
/**
 * Unggah & Rangkum.
 *
 * Kiri: naskah peraturannya — berkas .pdf/.docx/.txt, atau ditempel
 * langsung bila berkasnya hasil pindaian. Kanan: butir-butir yang
 * ditemukan, untuk DIPERIKSA, disunting, dan dicentang sebelum
 * disimpan.
 *
 * Tidak ada jalan pintas dari kiri langsung ke register. Pemecahan
 * pasalnya deterministik dan dapat dipercaya, tetapi rangkuman
 * kalimatnya tidak — dan pasal yang belum pernah dibaca siapa pun tidak
 * boleh ikut menentukan angka pemenuhan. Karena itu hasilnya tersimpan
 * berstatus Draf, dan draf tidak dihitung di mana pun sampai seseorang
 * menaikkannya.
 */
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import type { HalamanKepatuhanUnggah } from '../../types';
import PindahKepatuhan from './Pindah.vue';

const props = defineProps<HalamanKepatuhanUnggah>();
const page = usePage();

interface Usul { penunjuk: string; rangkuman: string; penerapan: string; ikut: boolean }

const hasil = ref<Usul[]>([]);
const catatan = ref<string | null>(null);
const dariAi = ref(false);

/* Hasil pembacaan datang lewat flash, bukan sebagai prop tetap:
   ia milik satu kiriman, dan prop tetap akan memunculkannya lagi
   setiap kali halaman ini dibuka kembali. */
watch(() => (page.props.flash as Record<string, unknown> | undefined)?.rangkuman, (r) => {
  if (!r) return;

  const d = r as { butir: Array<Omit<Usul, 'ikut'>>; catatan: string | null; ai: boolean };

  hasil.value  = d.butir.map((b) => ({ ...b, ikut: true }));
  catatan.value = d.catatan;
  dariAi.value  = d.ai;
}, { immediate: true });

const baca = useForm({ teks: '', kegiatan: '', berkas: null as File | null });

const membaca = ref(false);

function kirimBaca() {
  membaca.value = true;

  baca.post(props.tautan.rangkum, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => { membaca.value = false; },
  });
}

const simpan = useForm({
  sumber: 'Peraturan', jenis: '', nomor: '', judul: '',
  tanggal_terbit: '', instansi: '', aspek: '',
  ruang_lingkup: '', rangkuman: '',
  company_id: '', tahun: String(props.opsi.tahun[1] ?? new Date().getFullYear()),
  dari_ai: false, butir: [] as Array<Omit<Usul, 'ikut'>>,
});

const terpilih = computed(() => hasil.value.filter((b) => b.ikut));

function kirimSimpan() {
  simpan.butir = terpilih.value.map(({ penunjuk, rangkuman, penerapan }) => ({ penunjuk, rangkuman, penerapan }));
  simpan.dari_ai = dariAi.value;
  simpan.post(props.tautan.simpan);
}

function buang() {
  hasil.value = [];
  catatan.value = null;
  router.reload({ only: [] });
}

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head title="Unggah &amp; Rangkum Peraturan" />

  <div class="space-y-5">
    <PindahKepatuhan :tautan="tautan" kini="unggah" />

    <div class="grid gap-5 xl:grid-cols-2 items-start">
      <!-- 1. Naskahnya -->
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[14px] font-extrabold text-cam-ink">1. Naskah Peraturan</h3>

        <p v-if="!ai" class="mt-2 rounded-xl border border-stone-200 bg-stone-50 px-3 py-2 text-[11.5px] text-stone-600 leading-relaxed">
          Kunci AI belum dipasang pada pemasangan ini. Pemecahan pasalnya tetap berjalan penuh —
          yang tidak ada hanya usulan rangkuman kalimat dan bentuk penerapannya, yang tetap dapat
          diisi sendiri di kolom sebelah.
        </p>

        <div class="mt-4 space-y-4">
          <div>
            <label :class="label" for="berkas">Unggah berkas</label>
            <input id="berkas" type="file" accept=".pdf,.docx,.txt,.md" :class="isian"
                   @change="baca.berkas = (($event.target as HTMLInputElement).files?.[0] ?? null)">
            <p class="text-[11px] text-stone-400 mt-1 leading-relaxed">
              Format .pdf, .docx, atau .txt — maksimal 20 MB. PDF hasil pindaian tidak dapat dibaca
              karena isinya gambar, bukan teks; untuk berkas semacam itu pakai kotak di bawah.
            </p>
            <p v-if="baca.errors.berkas" class="text-[11px] text-red-600 mt-1">{{ baca.errors.berkas }}</p>
          </div>

          <div>
            <label :class="label" for="teks">Atau tempel teks peraturan</label>
            <textarea id="teks" v-model="baca.teks" rows="8" :class="isian"
                      placeholder="Tempel isi peraturan di sini bila berkasnya tidak dapat dibaca otomatis"></textarea>
            <p class="text-[11px] text-stone-400 mt-1">
              Bila kotak ini diisi, isinya yang dipakai — hasil bacaan berkas tidak menimpanya.
            </p>
          </div>

          <div>
            <label :class="label" for="kegiatan">Keterangan kegiatan perusahaan</label>
            <input id="kegiatan" v-model="baca.kegiatan" :class="isian"
                   placeholder="mis. tambang batubara terbuka, ada workshop, hauling road, dan kantin">
            <p class="text-[11px] text-stone-400 mt-1">
              Membantu menyusun usulan penerapan yang masuk akal. Boleh dikosongkan.
            </p>
          </div>

          <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                  :disabled="membaca" @click="kirimBaca">
            {{ membaca ? 'Membaca…' : '✦ Baca & Rangkum' }}
          </button>

          <p class="text-[11px] text-stone-400">
            Peraturan yang panjang bisa memerlukan setengah menit — tunggu sampai hasilnya muncul di kanan.
          </p>
        </div>
      </section>

      <!-- 2. Periksa & simpan -->
      <section class="bg-white rounded-2xl shadow-card border border-stone-100 p-5">
        <h3 class="text-[14px] font-extrabold text-cam-ink">2. Periksa &amp; Simpan</h3>

        <p v-if="catatan" class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[11.5px] text-amber-900 leading-relaxed">
          {{ catatan }}
        </p>

        <p v-if="!hasil.length" class="eq-kosong eq-kosong-kecil">
          <strong>Belum ada hasil.</strong>
          <span class="block halus">Unggah berkasnya atau tempel naskahnya di kolom kiri, lalu tekan Baca &amp; Rangkum.</span>
        </p>

        <template v-else>
          <div class="grid gap-3 sm:grid-cols-[180px_1fr] mt-4">
            <div>
              <label :class="label" for="s-jenis">Jenis</label>
              <select id="s-jenis" v-model="simpan.jenis" :class="isian">
                <option value="">— pilih —</option>
                <option v-for="j in opsi.jenis" :key="j" :value="j">{{ j }}</option>
              </select>
            </div>
            <div>
              <label :class="label" for="s-nomor">Nomor <span class="text-red-500">*</span></label>
              <input id="s-nomor" v-model="simpan.nomor" :class="isian"
                     placeholder="mis. Permen ESDM Nomor 26 Tahun 2018">
              <p v-if="simpan.errors.nomor" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.nomor }}</p>
            </div>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-judul">Judul <span class="text-red-500">*</span></label>
            <textarea id="s-judul" v-model="simpan.judul" rows="2" :class="isian"></textarea>
            <p v-if="simpan.errors.judul" class="text-[11px] text-red-600 mt-1">{{ simpan.errors.judul }}</p>
          </div>

          <div class="grid gap-3 sm:grid-cols-3 mt-3">
            <div>
              <label :class="label" for="s-terbit">Tanggal terbit</label>
              <input id="s-terbit" v-model="simpan.tanggal_terbit" type="date" :class="isian">
            </div>
            <div>
              <label :class="label" for="s-instansi">Instansi</label>
              <input id="s-instansi" v-model="simpan.instansi" :class="isian">
            </div>
            <div>
              <label :class="label" for="s-aspek">Aspek</label>
              <select id="s-aspek" v-model="simpan.aspek" :class="isian">
                <option v-for="a in opsi.aspek" :key="a.nilai" :value="a.nilai">{{ a.nama }}</option>
              </select>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2 mt-3">
            <div>
              <label :class="label" for="s-perusahaan">Perusahaan</label>
              <select id="s-perusahaan" v-model="simpan.company_id" :class="isian">
                <option value="">Berlaku umum</option>
                <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
              </select>
            </div>
            <div>
              <label :class="label" for="s-tahun">Tahun evaluasi</label>
              <select id="s-tahun" v-model="simpan.tahun" :class="isian">
                <option v-for="t in opsi.tahun" :key="t" :value="String(t)">{{ t }}</option>
              </select>
            </div>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-lingkup">Ruang lingkup</label>
            <textarea id="s-lingkup" v-model="simpan.ruang_lingkup" rows="2" :class="isian"></textarea>
          </div>

          <div class="mt-3">
            <label :class="label" for="s-rangkum">Rangkuman peraturan</label>
            <textarea id="s-rangkum" v-model="simpan.rangkuman" rows="3" :class="isian"></textarea>
          </div>

          <div class="flex items-center justify-between gap-2 mt-5 mb-2">
            <p class="text-[12px] font-bold text-cam-ink">
              Pasal / Ayat yang Ditemukan
              <span class="font-normal text-stone-400">— {{ hasil.length }} butir, centang yang ikut disimpan</span>
            </p>
            <button type="button" class="eq-btn-mini"
                    @click="hasil.forEach((b) => (b.ikut = terpilih.length !== hasil.length))">
              {{ terpilih.length === hasil.length ? 'Lepas semua' : 'Centang semua' }}
            </button>
          </div>

          <div class="kpt-usul">
            <table>
              <thead>
                <tr>
                  <th class="kpt-k-ikut">Ikut</th>
                  <th class="kpt-k-tunjuk">Pasal; Ayat</th>
                  <th>Rangkuman Isi</th>
                  <th>Saran Penerapan</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(b, i) in hasil" :key="i" :class="{ 'is-lepas': !b.ikut }">
                  <td class="kpt-k-ikut">
                    <input v-model="b.ikut" type="checkbox" class="ring-focus"
                           :aria-label="`Ikut simpan ${b.penunjuk}`">
                  </td>
                  <td class="kpt-k-tunjuk">
                    <input v-model="b.penunjuk" class="kpt-sel" :aria-label="`Penunjuk butir ${i + 1}`">
                  </td>
                  <td>
                    <textarea v-model="b.rangkuman" rows="3" class="kpt-sel"
                              :aria-label="`Rangkuman butir ${i + 1}`"></textarea>
                  </td>
                  <td>
                    <textarea v-model="b.penerapan" rows="3" class="kpt-sel"
                              :aria-label="`Saran penerapan butir ${i + 1}`"></textarea>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <p class="text-[11px] text-stone-500 mt-2 leading-relaxed">
            Kolom <b>Saran Penerapan</b> berisi bentuk pemenuhan yang lazim, <b>bukan</b> pernyataan bahwa hal
            itu sudah dikerjakan. Sunting seperlunya, atau kosongkan.
          </p>

          <div class="flex flex-wrap items-center gap-2 mt-4">
            <button type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                    :disabled="simpan.processing || !terpilih.length" @click="kirimSimpan">
              {{ simpan.processing ? 'Menyimpan…' : `Simpan ${terpilih.length} Butir ke Register` }}
            </button>
            <button type="button" class="eq-btn-mini" @click="buang">Buang hasil ini</button>
          </div>

          <p class="text-[11px] text-stone-500 mt-2 leading-relaxed">
            Tersimpan berstatus <b>Draf</b> dan seluruh butirnya <b>belum dinilai</b> — bukan N/A.
            Selama masih draf, isinya tidak ikut dihitung di dasbor. Periksa di halaman penilaiannya,
            lalu jadikan Tetap.
          </p>
        </template>
      </section>
    </div>
  </div>
</template>

<style scoped>
.kpt-usul { border: 1px solid #E7E5E4; border-radius: .9rem; overflow: auto; max-height: 26rem; }
.kpt-usul table { width: 100%; border-collapse: collapse; font-size: 11.5px; }

.kpt-usul thead th {
  position: sticky; top: 0; z-index: 1;
  background: #FAFAF9; border-bottom: 1px solid #E7E5E4;
  padding: .45rem .5rem; text-align: left;
  font-size: 9.5px; font-weight: 800; letter-spacing: .04em;
  text-transform: uppercase; color: #78716C; white-space: normal;
}

.kpt-usul tbody td { border-bottom: 1px solid #F5F5F4; padding: .35rem .5rem; vertical-align: top; }
.kpt-usul tbody tr.is-lepas { opacity: .45; }

.kpt-k-ikut   { width: 8%; text-align: center; }
.kpt-k-tunjuk { width: 22%; }

.kpt-sel {
  width: 100%; border: 1px solid #E7E5E4; border-radius: .45rem;
  padding: .25rem .4rem; font-size: 11.5px; line-height: 1.35; resize: vertical;
}
.kpt-sel:focus { outline: 2px solid #F57C00; outline-offset: 1px; border-color: transparent; }
</style>
