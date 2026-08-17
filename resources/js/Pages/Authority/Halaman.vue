<script setup lang="ts">
/**
 * Authority — berkas kelayakan kerja.
 *
 * Susunannya mengikuti pertanyaan gerbang, bukan bentuk tabelnya:
 * berapa orang yang HARI INI tidak boleh bekerja, dan kenapa. Itu
 * pertanyaan yang dibawa orang ke halaman ini; sisanya — daftar
 * sertifikat, riwayat MCU — adalah rinciannya.
 *
 * Sebabnya selalu ditulis. "Tidak layak" tanpa sebab memaksa pengawas
 * membuka tiga halaman untuk mencarinya sendiri, di gerbang, sambil
 * antrean memanjang.
 */
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import KartuGrafik from '../../Grafik/KartuGrafik.vue';
import Batang from '../../Grafik/Batang.vue';
import Donat from '../../Grafik/Donat.vue';
import { KEADAAN } from '../../Grafik/warna';

const props = usePage<any>().props as any;

/** Warna keadaan masa berlaku — dipesan maknanya, tidak dipakai lain. */
const WARNA: Record<string, string> = {
  aman:            KEADAAN.baik,
  perhatian:       KEADAAN.ingat,
  segera:          KEADAAN.serius,
  kritis:          KEADAAN.gawat,
  'tak-bertanggal': KEADAAN.netral,
};

const LABEL: Record<string, string> = {
  aman: 'Aman', perhatian: 'Perhatian', segera: 'Segera',
  kritis: 'Kritis', 'tak-bertanggal': 'Tanpa tanggal',
};

/* Ambangnya ditulis di layar, bukan dihafal pembacanya. */
const AMBANG = 'Aman >180 hari · Perhatian 91–180 · Segera 31–90 · Kritis ≤30 atau lewat';

const orang   = computed(() => props.orang ?? []);
const ringkas = computed(() => props.ringkas ?? {});

const saring = reactive({
  q: props.saring?.q ?? '',
  klas: props.saring?.klas ?? '',
  keadaan: props.saring?.keadaan ?? '',
});

function cari() {
  router.get('/authority', saring, { preserveState: true, preserveScroll: true });
}

/* ── ringkasan sebagai grafik ── */

const sebaranKeadaan = computed(() => {
  const per = ringkas.value?.perKeadaan ?? {};
  const gabung: Record<string, number> = {};

  for (const jenis of ['sertifikat', 'mcu', 'kartu']) {
    for (const [k, n] of Object.entries((per[jenis] ?? {}) as Record<string, number>)) {
      gabung[k] = (gabung[k] ?? 0) + Number(n);
    }
  }

  return ['kritis', 'segera', 'perhatian', 'aman', 'tak-bertanggal']
    .filter(k => (gabung[k] ?? 0) > 0)
    .map(k => ({ label: LABEL[k], nilai: gabung[k], warna: WARNA[k] }));
});

/**
 * Tiga jenis berkas berdampingan pada satu sumbu — berapa yang KRITIS
 * pada masing-masing. Itu yang menentukan ke mana tenaga diarahkan
 * lebih dulu; jumlah totalnya tidak.
 */
const kritisPerJenis = computed(() => {
  const per = ringkas.value?.perKeadaan ?? {};

  return [
    ['Sertifikat kompetensi', per.sertifikat],
    ['MCU', per.mcu],
    ['Kartu masuk', per.kartu],
  ].map(([label, d]: any) => ({
    label,
    nilai: Number(d?.kritis ?? 0) + Number(d?.segera ?? 0),
    keadaan: (Number(d?.kritis ?? 0) > 0 ? 'gawat' : 'ingat') as 'gawat' | 'ingat',
  }));
});

const takLayak = computed(() => orang.value.filter((o: any) => !o.layak));

/* ── formulir ── */

const buka = ref<string | null>(null);

const fOrang = useForm<Record<string, any>>({
  nama: '', nik: '', jabatan: '', departemen: '', klasifikasi: '',
  nomor_register: '', tgl_bergabung: '', status: 'aktif', catatan: '',
});

const fSertifikat = useForm<Record<string, any>>({
  kompetensi_jenis_id: '', nama: '', lembaga: '', nomor: '',
  tgl_terbit: '', tgl_expired: '', catatan: '',
});

const fMcu = useForm<Record<string, any>>({
  tgl_periksa: '', tgl_expired: '', penyelenggara: '',
  jenis: 'Berkala', hasil: 'Fit', pembatasan: '',
});

const fKartu = useForm<Record<string, any>>({
  jenis: 'ID Card', nomor: '', tgl_terbit: '', tgl_expired: '',
  golongan: '', area: '', catatan: '',
});

/**
 * Memilih jenis kompetensi ikut mengisi nama dan lembaganya.
 *
 * Nama disalin, bukan hanya dirujuk: jenis yang kemudian diganti
 * namanya tidak boleh mengubah bunyi sertifikat yang sudah tercetak
 * dan sudah diperiksa inspektur.
 */
function pilihJenis() {
  const j = (props.opsi?.kompetensi ?? []).find(
    (x: any) => String(x.id) === String(fSertifikat.kompetensi_jenis_id));

  if (j) { fSertifikat.nama = j.nama; fSertifikat.lembaga = j.lembaga ?? ''; }
}

const id = computed(() => props.p?.id);

function simpanOrang() {
  fOrang.post('/authority', { preserveScroll: true, onSuccess: () => { fOrang.reset(); buka.value = null; } });
}
function simpanSertifikat() {
  fSertifikat.post(`/authority/${id.value}/sertifikat`, { preserveScroll: true, onSuccess: () => fSertifikat.reset() });
}
function simpanMcu() {
  fMcu.post(`/authority/${id.value}/mcu`, { preserveScroll: true, onSuccess: () => fMcu.reset() });
}
function simpanKartu() {
  fKartu.post(`/authority/${id.value}/kartu`, { preserveScroll: true, onSuccess: () => fKartu.reset() });
}
function hapus(jalur: string, apa: string) {
  if (confirm(`Hapus ${apa}?`)) router.delete(jalur, { preserveScroll: true });
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

      <Link v-if="props.mode === 'rincian'" href="/authority" class="eq-btn-lain">
        Kembali ke daftar
      </Link>
      <button v-else type="button" class="eq-btn-utama"
              @click="buka = buka === 'orang' ? null : 'orang'">
        {{ buka === 'orang' ? 'Batal' : 'Tambah orang' }}
      </button>
    </section>

    <!-- ══════════ DAFTAR ══════════ -->
    <template v-if="props.mode === 'daftar'">

      <form v-if="buka === 'orang'"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
            @submit.prevent="simpanOrang">
        <input v-model="fOrang.nama" required placeholder="Nama lengkap"
               class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
        <input v-model="fOrang.nik" placeholder="NIK" class="rounded-lg border-stone-200 text-[12px]">
        <input v-model="fOrang.jabatan" placeholder="Jabatan" class="rounded-lg border-stone-200 text-[12px]">
        <input v-model="fOrang.departemen" placeholder="Departemen" class="rounded-lg border-stone-200 text-[12px]">
        <select v-model="fOrang.klasifikasi" class="rounded-lg border-stone-200 text-[12px]">
          <option value="">Tanpa klasifikasi</option>
          <option v-for="(l, k) in (props.opsi?.klasifikasi ?? {})" :key="k" :value="k">{{ k }} — {{ l }}</option>
        </select>
        <input v-model="fOrang.tgl_bergabung" type="date" class="rounded-lg border-stone-200 text-[12px]">
        <button class="eq-btn-utama" :disabled="fOrang.processing">Simpan</button>
        <p v-if="fOrang.errors.nama" class="text-[11px] text-red-600 md:col-span-4">{{ fOrang.errors.nama }}</p>
      </form>

      <!-- yang tidak boleh bekerja hari ini, paling atas -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div>
            <h3 class="text-[14px] font-bold text-cam-ink">Tidak boleh bekerja hari ini</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              MCU atau kartu masuk kadaluarsa, belum ada, atau hasil MCU menyatakan tidak layak.
            </p>
          </div>
          <span class="text-[11px] font-bold shrink-0 num"
                :style="{ color: takLayak.length ? KEADAAN.gawat : KEADAAN.baik }">
            {{ takLayak.length }} dari {{ ringkas.orang ?? 0 }} orang
          </span>
        </div>

        <ul v-if="takLayak.length" class="divide-y divide-stone-100">
          <li v-for="o in takLayak" :key="o.id" class="py-2.5 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: KEADAAN.gawat }"></span>
            <Link :href="`/authority/${o.id}`"
                  class="text-[12.5px] font-semibold text-cam-ink hover:text-cam-lime-deep min-w-0 truncate w-44">
              {{ o.nama }}
            </Link>
            <span class="text-[11.5px] text-stone-500 min-w-0 flex-1 truncate">{{ o.jabatan || '—' }}</span>
            <!-- Sebabnya, bukan cuma vonisnya. -->
            <span class="text-[11.5px] font-semibold shrink-0 text-right"
                  :style="{ color: KEADAAN.gawat }">{{ o.sebab.join(' · ') }}</span>
          </li>
        </ul>

        <p v-else class="text-[12px] py-4 text-center" :style="{ color: KEADAAN.baik }">
          Seluruh {{ ringkas.orang ?? 0 }} orang memenuhi syarat masuk hari ini.
        </p>
      </section>

      <section class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
        <KartuGrafik judul="Masa berlaku seluruh berkas" :catatan="AMBANG"
                     :angka="`${ringkas.sertifikat ?? 0} sertifikat`">
          <Donat :bagian="sebaranKeadaan" :tengah="String(ringkas.orang ?? 0)" tengah-label="orang" />

          <template #tabel>
            <table>
              <thead><tr><th>Keadaan</th><th>Sertifikat</th><th>MCU</th><th>Kartu</th></tr></thead>
              <tbody>
                <tr v-for="k in ['kritis','segera','perhatian','aman','tak-bertanggal']" :key="k">
                  <td>{{ LABEL[k] }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.sertifikat?.[k] ?? 0 }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.mcu?.[k] ?? 0 }}</td>
                  <td class="num">{{ ringkas.perKeadaan?.kartu?.[k] ?? 0 }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>

        <KartuGrafik judul="Yang perlu diperpanjang"
                     catatan="Kritis dan segera digabung — keduanya sudah harus punya tanggal, bukan niat."
                     angka="berkas">
          <Batang :baris="kritisPerJenis" apa-adanya />

          <template #tabel>
            <table>
              <thead><tr><th>Klasifikasi</th><th>Orang</th></tr></thead>
              <tbody>
                <tr v-for="k in (ringkas.perKlasifikasi ?? [])" :key="k.kode">
                  <td>{{ k.kode }} — {{ k.label }}</td>
                  <td class="num">{{ k.orang }}</td>
                </tr>
              </tbody>
            </table>
          </template>
        </KartuGrafik>
      </section>

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
        <form class="p-4 grid gap-2 md:grid-cols-5 border-b border-stone-100" @submit.prevent="cari">
          <input v-model="saring.q" placeholder="Cari nama, NIK, jabatan…"
                 class="rounded-lg border-stone-200 text-[12px] md:col-span-2">
          <select v-model="saring.klas" class="rounded-lg border-stone-200 text-[12px]">
            <option value="">Semua klasifikasi</option>
            <option v-for="(l, k) in (props.opsi?.klasifikasi ?? {})" :key="k" :value="k">{{ k }} — {{ l }}</option>
          </select>
          <select v-model="saring.keadaan" class="rounded-lg border-stone-200 text-[12px]">
            <option value="">Semua keadaan</option>
            <option v-for="(l, k) in LABEL" :key="k" :value="k">{{ l }}</option>
          </select>
          <button class="eq-btn-utama">Saring</button>
        </form>

        <div class="overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-100">
              <th class="px-5 py-3">Nama</th><th class="px-5 py-3">Jabatan</th>
              <th class="px-5 py-3">Klas</th><th class="px-5 py-3">Sertifikat</th>
              <th class="px-5 py-3">MCU</th><th class="px-5 py-3">Kartu masuk</th>
              <th class="px-5 py-3">Boleh kerja</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="o in orang" :key="o.id" class="border-b border-stone-50">
              <td class="px-5 py-3 font-semibold">
                <Link :href="`/authority/${o.id}`" class="text-cam-lime-deep">{{ o.nama }}</Link>
                <small class="block text-[10px] text-stone-400">{{ o.nik || '—' }}</small>
              </td>
              <td class="px-5 py-3">{{ o.jabatan || '—' }}</td>
              <td class="px-5 py-3">{{ o.klasifikasi || '—' }}</td>
              <td class="px-5 py-3 num">{{ o.jumlahSertifikat }}</td>
              <td class="px-5 py-3">
                <span v-if="o.mcu" :style="{ color: WARNA[o.mcu.keadaan] }" class="font-semibold">
                  {{ o.mcu.keterangan }}
                </span>
                <span v-else class="text-stone-400">belum ada</span>
              </td>
              <td class="px-5 py-3">
                <span v-if="o.kartu" :style="{ color: WARNA[o.kartu.keadaan] }" class="font-semibold">
                  {{ o.kartu.keterangan }}
                </span>
                <span v-else class="text-stone-400">belum ada</span>
              </td>
              <td class="px-5 py-3">
                <span class="font-bold" :style="{ color: o.layak ? KEADAAN.baik : KEADAAN.gawat }">
                  {{ o.layak ? 'Boleh' : 'Tidak' }}
                </span>
              </td>
            </tr>
            <tr v-if="!orang.length">
              <td colspan="7" class="px-5 py-10 text-center text-stone-400">
                Belum ada orang yang cocok dengan saringan ini.
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </section>
    </template>

    <!-- ══════════ RINCIAN ══════════ -->
    <template v-if="props.mode === 'rincian'">

      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h3 class="text-lg font-bold text-cam-ink">{{ props.p?.nama }}</h3>
            <p class="text-[12.5px] text-stone-500 mt-0.5">
              {{ props.p?.jabatan || 'Jabatan belum diisi' }}
              <span v-if="props.p?.klasLabel"> · {{ props.p.klasifikasi }} — {{ props.p.klasLabel }}</span>
              <span v-if="props.p?.nik"> · NIK {{ props.p.nik }}</span>
            </p>
          </div>

          <div class="text-right">
            <p class="text-[19px] font-bold leading-none"
               :style="{ color: props.p?.layak ? KEADAAN.baik : KEADAAN.gawat }">
              {{ props.p?.layak ? 'Boleh bekerja' : 'Tidak boleh bekerja' }}
            </p>
            <p v-if="!props.p?.layak" class="text-[11.5px] mt-1" :style="{ color: KEADAAN.gawat }">
              {{ props.p?.sebab?.join(' · ') }}
            </p>
          </div>
        </div>
      </section>

      <!-- sertifikat -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <h3 class="text-[14px] font-bold text-cam-ink mb-3">Sertifikat kompetensi</h3>

        <ul v-if="props.sertifikat?.length" class="divide-y divide-stone-100 mb-4">
          <li v-for="s in props.sertifikat" :key="s.id" class="py-2.5 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: WARNA[s.keadaan] }"></span>
            <span class="text-[12.5px] font-semibold text-cam-ink min-w-0 flex-1 truncate">
              {{ s.nama }}
              <span v-if="s.dariLms" class="text-[10px] font-normal text-stone-400">· dari LMS</span>
            </span>
            <span class="text-[11px] text-stone-400 shrink-0 hidden sm:block">{{ s.lembaga || '—' }}</span>
            <span class="text-[11.5px] font-semibold shrink-0 w-32 text-right"
                  :style="{ color: WARNA[s.keadaan] }">{{ s.keterangan }}</span>
            <button type="button" class="text-red-600 text-[11px] shrink-0"
                    @click="hapus(`/authority/${id}/sertifikat/${s.id}`, s.nama)">Hapus</button>
          </li>
        </ul>
        <p v-else class="text-[12px] text-stone-400 py-3">Belum ada sertifikat tercatat.</p>

        <form class="grid gap-2 md:grid-cols-6 pt-3 border-t border-stone-100" @submit.prevent="simpanSertifikat">
          <select v-model="fSertifikat.kompetensi_jenis_id" class="rounded-lg border-stone-200 text-[12px] md:col-span-2"
                  @change="pilihJenis">
            <option value="">Pilih jenis kompetensi…</option>
            <option v-for="j in (props.opsi?.kompetensi ?? [])" :key="j.id" :value="j.id">{{ j.nama }}</option>
          </select>
          <input v-model="fSertifikat.nomor" placeholder="No. sertifikat" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fSertifikat.tgl_terbit" type="date" title="Tanggal terbit" class="rounded-lg border-stone-200 text-[12px]">
          <input v-model="fSertifikat.tgl_expired" type="date" title="Tanggal kadaluarsa" class="rounded-lg border-stone-200 text-[12px]">
          <button class="eq-btn-utama" :disabled="fSertifikat.processing">Tambah</button>
        </form>
      </section>

      <div class="grid gap-4 lg:grid-cols-2">
        <!-- MCU -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="text-[14px] font-bold text-cam-ink mb-1">Pemeriksaan kesehatan</h3>
          <p class="text-[11px] text-stone-500 mb-3">
            Hanya kesimpulan kelayakan kerjanya yang disimpan — rincian medis adalah rekam medis.
          </p>

          <ul v-if="props.mcu?.length" class="divide-y divide-stone-100 mb-4">
            <li v-for="m in props.mcu" :key="m.id" class="py-2.5">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: WARNA[m.keadaan] }"></span>
                <span class="text-[12.5px] font-semibold" :style="{ color: m.hasilLayak ? '#292524' : KEADAAN.gawat }">
                  {{ m.hasil }}
                </span>
                <span class="text-[11px] text-stone-400">{{ m.jenis }} · {{ m.tglPeriksa }}</span>
                <span class="ml-auto text-[11.5px] font-semibold" :style="{ color: WARNA[m.keadaan] }">
                  {{ m.keterangan }}
                </span>
                <button type="button" class="text-red-600 text-[11px]"
                        @click="hapus(`/authority/${id}/mcu/${m.id}`, 'catatan MCU')">Hapus</button>
              </div>
              <p v-if="m.pembatasan" class="text-[11px] text-amber-700 mt-1 ml-3.5">{{ m.pembatasan }}</p>
            </li>
          </ul>
          <p v-else class="text-[12px] text-stone-400 py-3">Belum ada MCU tercatat.</p>

          <form class="grid gap-2 md:grid-cols-3 pt-3 border-t border-stone-100" @submit.prevent="simpanMcu">
            <input v-model="fMcu.tgl_periksa" type="date" required title="Tanggal periksa" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="fMcu.tgl_expired" type="date" title="Berlaku sampai" class="rounded-lg border-stone-200 text-[12px]">
            <select v-model="fMcu.jenis" class="rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in (props.opsi?.jenisMcu ?? [])" :key="j">{{ j }}</option>
            </select>
            <select v-model="fMcu.hasil" class="rounded-lg border-stone-200 text-[12px]">
              <option v-for="h in (props.opsi?.hasilMcu ?? [])" :key="h">{{ h }}</option>
            </select>
            <input v-model="fMcu.pembatasan" placeholder="Pembatasan kerja" class="rounded-lg border-stone-200 text-[12px]">
            <button class="eq-btn-utama" :disabled="fMcu.processing">Catat</button>
          </form>
        </section>

        <!-- kartu masuk -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
          <h3 class="text-[14px] font-bold text-cam-ink mb-1">Kartu masuk tambang</h3>
          <p class="text-[11px] text-stone-500 mb-3">
            ID card, SIMPER, dan mine permit — masing-masing masa berlakunya sendiri.
          </p>

          <ul v-if="props.kartu?.length" class="divide-y divide-stone-100 mb-4">
            <li v-for="k in props.kartu" :key="k.id" class="py-2.5 flex items-center gap-2">
              <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: WARNA[k.keadaan] }"></span>
              <span class="text-[12.5px] font-semibold text-cam-ink">{{ k.jenis }}</span>
              <span class="text-[11px] text-stone-400 min-w-0 truncate">
                {{ k.nomor || '—' }}<span v-if="k.golongan"> · {{ k.golongan }}</span>
              </span>
              <span class="ml-auto text-[11.5px] font-semibold shrink-0" :style="{ color: WARNA[k.keadaan] }">
                {{ k.keterangan }}
              </span>
              <button type="button" class="text-red-600 text-[11px] shrink-0"
                      @click="hapus(`/authority/${id}/kartu/${k.id}`, k.jenis)">Hapus</button>
            </li>
          </ul>
          <p v-else class="text-[12px] text-stone-400 py-3">Belum ada kartu tercatat.</p>

          <form class="grid gap-2 md:grid-cols-3 pt-3 border-t border-stone-100" @submit.prevent="simpanKartu">
            <select v-model="fKartu.jenis" class="rounded-lg border-stone-200 text-[12px]">
              <option v-for="j in (props.opsi?.jenisKartu ?? [])" :key="j">{{ j }}</option>
            </select>
            <input v-model="fKartu.nomor" placeholder="Nomor kartu" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="fKartu.golongan" placeholder="Golongan (SIMPER)" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="fKartu.tgl_terbit" type="date" title="Tanggal terbit" class="rounded-lg border-stone-200 text-[12px]">
            <input v-model="fKartu.tgl_expired" type="date" title="Berlaku sampai" class="rounded-lg border-stone-200 text-[12px]">
            <button class="eq-btn-utama" :disabled="fKartu.processing">Terbitkan</button>
          </form>
        </section>
      </div>
    </template>
  </div>
</template>
