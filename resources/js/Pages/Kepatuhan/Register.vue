<script setup lang="ts">
/**
 * Register Pemenuhan.
 *
 * Daftar kewajiban yang sudah diidentifikasi, dengan bar capaian pada
 * TIAP BARIS. Bar itu yang membuat daftarnya terbaca sekilas: tanpanya,
 * dua ratus baris berjudul panjang harus dibuka satu per satu untuk
 * mengetahui mana yang tertinggal.
 */
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import type { HalamanKepatuhanRegister } from '../../types';
import PindahKepatuhan from './Pindah.vue';

const props = defineProps<HalamanKepatuhanRegister>();

const cari = ref(props.saring.cari ?? '');

/* Namanya BUKAN `saring`: itu nama propnya, dan fungsi bernama sama
   menutupi prop di dalam template. */
function ubahSaring(ubah: Record<string, string | number | null>) {
  router.get(props.tautan.register, { ...props.saring, cari: cari.value, ...ubah },
             { preserveState: true, replace: true });
}

/* Pencarian ditunda seperempat detik sesudah ketukan terakhir.
   Mengirim tiap huruf berarti sebelas permintaan untuk satu kata, dan
   jawaban yang datang tidak berurutan menampilkan hasil kata yang belum
   selesai diketik. */
let jeda: ReturnType<typeof setTimeout> | undefined;
watch(cari, () => {
  clearTimeout(jeda);
  jeda = setTimeout(() => ubahSaring({}), 250);
});

const teksPersen = (p: number | null) => (p === null ? '—' : `${p}%`);

const nada = (p: number | null) =>
  p === null ? '#A8A29E' : p >= 90 ? '#16A34A' : p >= 70 ? '#CA9A04' : '#DC2626';

const isian = 'ring-focus rounded-xl border border-stone-200 bg-white pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600';
const teks  = 'ring-focus rounded-xl border border-stone-200 bg-white px-3 py-2 text-[12px] text-stone-600';
</script>

<template>
  <Head title="Register Pemenuhan" />

  <div class="space-y-5">
    <PindahKepatuhan :tautan="tautan" kini="register" />

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Tahun</span>
          <select :class="isian" :value="saring.tahun" @change="ubahSaring({ tahun: ($event.target as HTMLSelectElement).value })">
            <option v-for="t in opsi.tahun" :key="t" :value="t">{{ t }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Sumber</span>
          <select :class="isian" :value="saring.sumber ?? ''" @change="ubahSaring({ sumber: ($event.target as HTMLSelectElement).value })">
            <option value="">Semua sumber</option>
            <option v-for="s in opsi.sumber" :key="s" :value="s">{{ opsi.sumberNama[s] }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Aspek</span>
          <select :class="isian" :value="saring.aspek ?? ''" @change="ubahSaring({ aspek: ($event.target as HTMLSelectElement).value })">
            <option value="">Semua aspek</option>
            <option v-for="a in opsi.aspek" :key="a.nilai" :value="a.nilai">{{ a.nama }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Punya butir berstatus</span>
          <select :class="isian" :value="saring.punya ?? ''" @change="ubahSaring({ punya: ($event.target as HTMLSelectElement).value })">
            <option value="">Semua status</option>
            <option v-for="s in opsi.status" :key="s" :value="s">{{ s }}</option>
            <option value="belum">Belum dinilai</option>
          </select>
        </label>

        <label class="grid gap-1 flex-1 min-w-[180px]">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Cari</span>
          <input v-model="cari" :class="teks" placeholder="nomor / judul / instansi">
        </label>

        <a :href="tautan.unggah" class="eq-btn-lain" style="flex:none">☁ Unggah &amp; Rangkum</a>
        <a :href="tautan.buat" class="eq-btn-utama" style="flex:none">+ Tambah</a>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden">
      <div class="px-5 py-3.5 border-b border-stone-100 flex items-center justify-between gap-2">
        <h3 class="text-[13px] font-bold text-cam-ink">Daftar Kewajiban</h3>
        <span class="text-[11.5px] text-stone-400 num">{{ halaman.total }} baris</span>
      </div>

      <div class="overflow-x-auto">
        <table class="kpt-reg w-full">
          <thead>
            <tr>
              <th class="kpt-k-kode">Kode</th>
              <th class="kpt-k-aspek">Aspek</th>
              <th class="kpt-k-nomor">Nomor &amp; Judul</th>
              <th class="kpt-k-jenis">Jenis</th>
              <th class="kpt-k-instansi">Instansi</th>
              <th class="kpt-k-terbit">Terbit</th>
              <th class="kpt-k-capai">Pemenuhan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in daftar" :key="d.id">
              <td class="kpt-k-kode num">{{ d.kode ?? '—' }}</td>

              <td class="kpt-k-aspek">
                <span class="kpt-lencana" :style="{ background: d.aspekWarna }">{{ d.aspekNama }}</span>
              </td>

              <td class="kpt-k-nomor">
                <a :href="d.url" class="kpt-nomor">{{ d.nomor }}</a>
                <span v-if="d.status === 'Draf'" class="kpt-tanda kpt-draf">Draf</span>
                <span v-if="d.dariAi" class="kpt-tanda kpt-ai" title="Hasil rangkuman otomatis">AI</span>
                <span class="kpt-judul">{{ d.judul }}</span>
              </td>

              <td class="kpt-k-jenis">{{ d.jenis ?? '—' }}</td>
              <td class="kpt-k-instansi">{{ d.instansi ?? '—' }}</td>
              <td class="kpt-k-terbit num">{{ d.terbit ?? '—' }}</td>

              <td class="kpt-k-capai">
                <template v-if="d.rekap.total">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-stone-100 overflow-hidden min-w-[54px]">
                      <div class="h-full rounded-full"
                           :style="{ width: (d.rekap.persen ?? 0) + '%', background: nada(d.rekap.persen) }"></div>
                    </div>
                    <span class="num font-extrabold text-[11.5px]" :style="{ color: nada(d.rekap.persen) }">
                      {{ teksPersen(d.rekap.persen) }}
                    </span>
                  </div>
                  <span class="kpt-pecah num">
                    {{ d.rekap.comply }} comply · {{ d.rekap.notComply }} NC · {{ d.rekap.na }} N/A
                    <template v-if="d.rekap.belum"> · {{ d.rekap.belum }} belum</template>
                  </span>
                </template>

                <span v-else class="kpt-belum">⊘ butir belum dirinci</span>
              </td>
            </tr>

            <tr v-if="!daftar.length">
              <td colspan="7">
                <p class="eq-kosong">
                  <strong>Belum ada kewajiban yang cocok dengan saringan ini.</strong>
                  <span class="block halus">Ubah saringannya, atau tambahkan kewajiban baru.</span>
                  <a :href="tautan.buat" class="eq-btn-utama">+ Tambah Kewajiban</a>
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="halaman.akhir > 1" class="flex flex-wrap gap-1 px-5 py-3 border-t border-stone-100">
        <a v-for="(t, n) in halaman.tautan" :key="n" :href="t.url ?? undefined"
           class="px-2.5 py-1 rounded-lg text-[11.5px] font-semibold"
           :class="t.aktif ? 'bg-cam-ink text-white' : t.url ? 'text-stone-500 hover:bg-stone-100' : 'text-stone-300'"
           v-html="t.label"></a>
      </nav>
    </div>
  </div>
</template>

<style scoped>
.kpt-reg { border-collapse: collapse; font-size: 12px; }

.kpt-reg thead th {
  background: #FAFAF9;
  border-bottom: 1px solid #E7E5E4;
  padding: .55rem .6rem;
  text-align: left;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: .05em;
  text-transform: uppercase;
  color: #78716C;
  white-space: normal;
  vertical-align: bottom;
}

.kpt-reg tbody td { border-bottom: 1px solid #F5F5F4; padding: .6rem; vertical-align: top; }
.kpt-reg tbody tr:hover td { background: #FAFAF9; }

.kpt-k-kode     { width: 5%; font-weight: 700; color: #57534E; }
.kpt-k-aspek    { width: 9%; }
.kpt-k-nomor    { width: 32%; }
.kpt-k-jenis    { width: 12%; color: #57534E; }
.kpt-k-instansi { width: 14%; color: #57534E; }
.kpt-k-terbit   { width: 8%; color: #78716C; }
.kpt-k-capai    { width: 20%; }

.kpt-lencana {
  display: inline-block; color: #fff; font-size: 9.5px; font-weight: 800;
  padding: .1rem .4rem; border-radius: 999px; letter-spacing: .02em;
}

.kpt-nomor { font-weight: 700; color: #0F1720; }
.kpt-nomor:hover { color: #DC6E00; text-decoration: underline; }
.kpt-judul { display: block; color: #78716C; font-size: 11px; line-height: 1.35; margin-top: .1rem; }

.kpt-tanda { display: inline-block; margin-left: .3rem; font-size: 9px; font-weight: 800;
            padding: .05rem .3rem; border-radius: .3rem; vertical-align: middle; }
.kpt-draf { background: #FACC15; color: #422006; }
.kpt-ai   { background: #E8ECF0; color: #57534E; }

.kpt-pecah { display: block; margin-top: .25rem; font-size: 10px; color: #A8A29E; }
.kpt-belum { font-size: 11px; color: #A8A29E; }

/* Di layar sempit, kolom yang hanya menjelaskan asal-usul dilepas
   lebih dulu — yang harus tetap terbaca adalah nomor, judul, dan
   capaiannya. */
@media (max-width: 75rem) {
  .kpt-k-jenis, .kpt-k-instansi { display: none; }
  .kpt-k-nomor { width: 46%; }
}

@media (max-width: 52rem) {
  .kpt-k-terbit, .kpt-k-kode { display: none; }
}
</style>
