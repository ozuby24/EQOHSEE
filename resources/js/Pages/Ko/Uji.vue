<script setup lang="ts">
/**
 * Uji kelayakan unit SPIP — riwayat, bukan satu tanggal yang ditimpa.
 *
 * Sebelumnya sebuah unit hanya menyimpan satu tanggal sertifikasi.
 * Tiap kali diuji ulang, tanggal itu ditimpa dan uji sebelumnya hilang
 * — dan ketika inspektur meminta bukti bahwa alat angkat sudah diuji
 * tiga tahun berturut-turut, yang dapat ditunjukkan hanya yang
 * terakhir.
 *
 * "LAYAK BERSYARAT" DIGAMBAR BERBEDA dari "Layak", bukan sekadar
 * berbeda warna: syaratnya ditampilkan penuh di barisnya. Syarat yang
 * tersembunyi di balik tombol "lihat rincian" adalah syarat yang tidak
 * dibaca siapa pun di lapangan — dan alatnya tetap berjalan seolah
 * layak penuh.
 */
import { computed, reactive } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { KEADAAN } from '../../Grafik/warna';

const props = usePage<any>().props as any;

const uji     = computed(() => props.uji ?? []);
const ringkas = computed(() => props.ringkasUji ?? {});

const buka = reactive({ form: false });

const WARNA_ALUR: Record<string, string> = {
  draf: KEADAAN.netral, diajukan: KEADAAN.ingat,
  disetujui: KEADAAN.baik, ditolak: KEADAAN.gawat,
};

const WARNA_HASIL: Record<string, string> = {
  'Layak':           KEADAAN.baik,
  'Layak Bersyarat': KEADAAN.ingat,
  'Tidak Layak':     KEADAAN.gawat,
};

const f = useForm<Record<string, any>>({
  ko_object_id: '', nomor: '', merk: '', tipe: '', nomor_seri: '',
  tgl_inspeksi: '', tgl_expired: '', pemeriksa: '', lembaga: '', lokasi_uji: '',
  hasil: 'Layak', syarat: '', temuan: '', rekomendasi: '',
});

/** Memilih unit ikut mengisi merk dan nomor serinya sebagai nilai awal. */
function pilihObjek() {
  const o = (props.objek ?? []).find((x: any) => String(x.id) === String(f.ko_object_id));
  if (!o) return;

  if (!f.merk)       f.merk = o.merk ?? '';
  if (!f.nomor_seri) f.nomor_seri = o.serial_number ?? '';
}

function simpan() {
  f.post('/ko/uji', { preserveScroll: true, onSuccess: () => { f.reset(); buka.form = false; } });
}

function ajukan(id: number) {
  router.post(`/ko/uji/${id}/ajukan`, {}, { preserveScroll: true });
}

function tinjau(id: number, aksi: 'setujui' | 'tolak' | 'tarik') {
  let alasan = '';
  if (aksi === 'tolak') {
    alasan = (prompt('Alasan penolakan:') ?? '').trim();
    if (!alasan) return;
  }
  router.post(`/ko/uji/${id}/tinjau`, { aksi, alasan }, { preserveScroll: true });
}

function hapus(id: number, kode: string) {
  if (confirm(`Hapus uji kelayakan ${kode}?`)) {
    router.delete(`/ko/uji/${id}`, { preserveScroll: true });
  }
}
</script>

<template>
  <Head title="Uji Kelayakan SPIP" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">Uji Kelayakan SPIP</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">
          Riwayat pengujian tiap unit. Uji yang disetujui memperbarui sertifikasi unitnya;
          hasil "Tidak Layak" sengaja tidak memperbarui apa pun.
        </p>
      </div>
      <div class="flex gap-2">
        <Link href="/ko/unit" class="eq-btn-lain">Jenis Unit</Link>
        <Link href="/ko/kelayakan" class="eq-btn-lain">Kelayakan</Link>
        <button v-if="props.bolehUbah" type="button" class="eq-btn-utama"
                @click="buka.form = !buka.form">
          {{ buka.form ? 'Batal' : 'Catat uji' }}
        </button>
      </div>
    </section>

    <form v-if="buka.form"
          class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 grid gap-3 md:grid-cols-4"
          @submit.prevent="simpan">
      <select v-model="f.ko_object_id" required @change="pilihObjek"
              class="rounded-lg border-stone-200 text-[12px] md:col-span-2" aria-label="Objek KO">
        <option value="">Pilih unit…</option>
        <option v-for="o in (props.objek ?? [])" :key="o.id" :value="o.id">
          {{ o.kode }} · {{ o.nama }}
        </option>
      </select>
      <input v-model="f.nomor" placeholder="No. sertifikat uji" class="rounded-lg border-stone-200 text-[12px]">
      <select v-model="f.hasil" class="rounded-lg border-stone-200 text-[12px]" aria-label="Hasil">
        <option v-for="h in ['Layak','Layak Bersyarat','Tidak Layak']" :key="h">{{ h }}</option>
      </select>

      <input v-model="f.merk" placeholder="Merk" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.tipe" placeholder="Tipe" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.nomor_seri" placeholder="Nomor seri" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.lokasi_uji" placeholder="Lokasi uji" class="rounded-lg border-stone-200 text-[12px]">

      <input v-model="f.tgl_inspeksi" type="date" required title="Tanggal inspeksi"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.tgl_expired" type="date" title="Berlaku sampai (kosong = dihitung dari interval jenisnya)"
             class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.pemeriksa" placeholder="Nama pemeriksa" class="rounded-lg border-stone-200 text-[12px]">
      <input v-model="f.lembaga" placeholder="Lembaga penguji" class="rounded-lg border-stone-200 text-[12px]">

      <!-- Syarat hanya muncul ketika ia memang wajib. Medan yang selalu
           tampil tapi jarang berlaku dilewati mata; yang muncul justru
           saat dibutuhkan tidak. -->
      <textarea v-if="f.hasil === 'Layak Bersyarat'" v-model="f.syarat" rows="2"
                placeholder="Syarat yang harus dipenuhi selama unit dioperasikan (wajib)"
                class="rounded-lg border-stone-200 text-[12px] md:col-span-4"></textarea>

      <textarea v-model="f.temuan" rows="2" placeholder="Temuan"
                class="rounded-lg border-stone-200 text-[12px] md:col-span-2"></textarea>
      <textarea v-model="f.rekomendasi" rows="2" placeholder="Rekomendasi"
                class="rounded-lg border-stone-200 text-[12px] md:col-span-2"></textarea>

      <button class="eq-btn-utama" :disabled="f.processing">Simpan draf</button>

      <p v-for="(e, k) in f.errors" :key="k" class="text-[11px] text-red-600 md:col-span-4">{{ e }}</p>
    </form>

    <section class="grid gap-3 sm:grid-cols-4">
      <div v-for="k in [
             ['Total uji', ringkas.total ?? 0, KEADAAN.netral],
             ['Menunggu tinjauan', ringkas.menunggu ?? 0, KEADAAN.ingat],
             ['Layak bersyarat', ringkas.bersyarat ?? 0, KEADAAN.serius],
             ['Tidak layak', ringkas.tidakLolos ?? 0, KEADAAN.gawat],
           ]" :key="k[0] as string"
           class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <p class="text-[26px] font-bold leading-none num"
           :style="{ color: Number(k[1]) ? (k[2] as string) : KEADAAN.netral }">{{ k[1] }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">{{ k[0] }}</p>
      </div>
    </section>

    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <ul v-if="uji.length" class="divide-y divide-stone-100">
        <li v-for="u in uji" :key="u.id" class="py-3">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-[12.5px] font-semibold text-cam-ink">
                <Link :href="`/ko/objek/${u.objekId}`" class="hover:text-cam-lime-deep">
                  {{ u.kode }} · {{ u.namaObjek }}
                </Link>
                <span class="font-bold ml-2" :style="{ color: WARNA_HASIL[u.hasil] }">{{ u.hasil }}</span>
              </p>
              <p class="text-[11.5px] text-stone-500 mt-0.5">
                Diuji {{ u.tglInspeksi }}
                <span v-if="u.tglExpired"> · berlaku sampai {{ u.tglExpired }}</span>
                <span v-if="u.pemeriksa"> · {{ u.pemeriksa }}</span>
                <span v-if="u.lembaga"> · {{ u.lembaga }}</span>
              </p>
              <p v-if="u.nomor || u.merk || u.seri" class="text-[11px] text-stone-400 mt-0.5">
                {{ [u.nomor, u.merk, u.tipe, u.seri].filter(Boolean).join(' · ') }}
              </p>

              <!-- Syaratnya ditampilkan PENUH. Syarat yang tersembunyi
                   di balik tombol tidak dibaca siapa pun di lapangan,
                   dan alatnya berjalan seolah layak penuh. -->
              <p v-if="u.syarat" class="text-[11.5px] mt-1 rounded-lg px-2.5 py-1.5"
                 :style="{ color: KEADAAN.ingat, background: '#FEF6E7' }">
                <b>Syarat:</b> {{ u.syarat }}
              </p>
              <p v-if="u.temuan" class="text-[11px] text-stone-500 mt-1">Temuan: {{ u.temuan }}</p>
              <p v-if="u.rekomendasi" class="text-[11px] text-stone-500">Rekomendasi: {{ u.rekomendasi }}</p>
              <p v-if="u.alasanTolak" class="text-[11px] text-red-600 mt-1">Ditolak: {{ u.alasanTolak }}</p>
            </div>

            <div class="text-right shrink-0">
              <p class="text-[11.5px] font-bold" :style="{ color: WARNA_ALUR[u.status] }">
                {{ u.statusLabel }}
              </p>
              <p v-if="u.tglExpired" class="text-[10.5px] mt-0.5 text-stone-500">
                {{ u.keterangan }}
              </p>
            </div>
          </div>

          <div v-if="props.bolehUbah" class="flex flex-wrap items-center gap-3 mt-2">
            <button v-if="u.dapatDiubah" type="button"
                    class="text-[11px] font-semibold text-cam-lime-deep"
                    @click="ajukan(u.id)">Ajukan</button>
            <button v-if="u.dapatDitinjau" type="button" class="text-[11px] font-semibold"
                    :style="{ color: KEADAAN.baik }" @click="tinjau(u.id, 'setujui')">Setujui</button>
            <button v-if="u.dapatDitinjau" type="button" class="text-[11px] font-semibold text-red-600"
                    @click="tinjau(u.id, 'tolak')">Tolak</button>
            <button v-if="u.status === 'diajukan'" type="button" class="text-[11px] text-stone-500"
                    @click="tinjau(u.id, 'tarik')">Tarik</button>
            <button v-if="u.dapatDiubah" type="button" class="text-[11px] text-red-600 ml-auto"
                    @click="hapus(u.id, u.kode)">Hapus</button>
          </div>
        </li>
      </ul>
      <p v-else class="text-[12px] py-10 text-center text-stone-400">Belum ada uji kelayakan tercatat.</p>
    </section>
  </div>
</template>
