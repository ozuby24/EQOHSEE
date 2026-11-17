<script setup lang="ts">
/**
 * Dasbor Pemenuhan.
 *
 * Satu pertanyaan yang dibawa manajemen: seberapa jauh kewajiban yang
 * mengikat sudah dipenuhi. Angka itu ditaruh paling atas dan paling
 * besar — beserta dua hal yang membuatnya jujur: berapa butir yang
 * BELUM dinilai sama sekali, dan berapa peraturan yang terdaftar tanpa
 * satu pun butir. Tanpa keduanya, register yang baru sepuluh persen
 * dikerjakan dapat menunjukkan seratus persen pemenuhan, dan angka itu
 * benar sekaligus menyesatkan.
 */
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanKepatuhanDasbor } from '../../types';
import PindahKepatuhan from './Pindah.vue';
import IkonStat from '../../Components/IkonStat.vue';
import CincinPersen from '../../Components/CincinPersen.vue';
import TrenPemenuhan from '../../Components/TrenPemenuhan.vue';

const props = defineProps<HalamanKepatuhanDasbor>();

/* Namanya BUKAN `saring`: itu nama propnya, dan fungsi bernama sama
   menutupi prop di dalam template — saringan yang tampil di layar
   berubah menjadi sebuah fungsi, dan nilai terpilihnya lenyap. */
function ubahSaring(ubah: Record<string, string | number | null>) {
  router.get(props.tautan.dasbor, { ...props.saring, ...ubah },
             { preserveState: true, replace: true });
}

const persen = computed(() => props.ringkas.persen);

/* Angka yang belum dapat dihitung ditulis sebagai "—", bukan 0%.
   Nol berarti "seluruhnya tidak comply" — pernyataan yang jauh lebih
   keras daripada "belum ada yang dinilai". */
const teksPersen = (p: number | null) => (p === null ? '—' : `${p}%`);

const nada = (p: number | null) =>
  p === null ? '#A8A29E' : p >= 90 ? '#16A34A' : p >= 70 ? '#CA9A04' : '#DC2626';

const isian = 'ring-focus rounded-xl border border-stone-200 bg-white pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Dasbor Pemenuhan" />

  <div class="space-y-5">
    <PindahKepatuhan :tautan="tautan" kini="dasbor" />

    <!-- Saringan: perusahaan, tahun evaluasi, sumber kewajiban, aspek. -->
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Tahun evaluasi</span>
          <select :class="isian" :value="saring.tahun" @change="ubahSaring({ tahun: ($event.target as HTMLSelectElement).value })">
            <option v-for="t in opsi.tahun" :key="t" :value="t">{{ t }}</option>
          </select>
        </label>

        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">Sumber kewajiban</span>
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

        <a :href="tautan.unggah" class="eq-btn-lain ml-auto" style="flex:none">☁ Unggah &amp; Rangkum</a>
        <a :href="tautan.buat" class="eq-btn-utama" style="flex:none">+ Tambah Kewajiban</a>
      </div>
    </div>

    <!-- Angka utama diberi CINCIN, bukan disejajarkan dengan tiga angka
         pendamping. Ia jawaban atas pertanyaan yang membawa orang ke
         halaman ini; tiga sisanya penjelas. Menyamakan ukurannya
         membuat mata harus memilih sendiri mana yang harus dibaca. -->
    <div class="kpt-utama">
      <section class="kpt-sorot">
        <CincinPersen :persen="persen" :ukuran="150" :tebal="14"
                      :ket="`${ringkas.comply} dari ${ringkas.dinilai} dinilai`" />

        <div class="min-w-0">
          <p class="kpt-sorot-label">Pemenuhan Kewajiban · {{ saring.tahun }}</p>
          <p class="kpt-sorot-ket">
            Dihitung dari butir yang benar-benar dinilai. Butir <b>N/A</b> tidak ikut menjadi
            pembagi, dan butir yang <b>belum dinilai</b> dihitung terpisah — keduanya sengaja tidak
            menaikkan maupun menurunkan angka ini.
          </p>

          <div class="kpt-pecahan">
            <span class="kpt-keping is-ok">
              <b class="num">{{ ringkas.comply }}</b> comply
            </span>
            <span class="kpt-keping is-nok">
              <b class="num">{{ ringkas.notComply }}</b> not comply
            </span>
            <span class="kpt-keping is-na">
              <b class="num">{{ ringkas.na }}</b> N/A
            </span>
            <span class="kpt-keping is-kosong">
              <b class="num">{{ ringkas.belum }}</b> belum dinilai
            </span>
          </div>
        </div>
      </section>

      <div class="eq-kpi-baris kpt-tiga">
        <div class="eq-kpi">
          <span class="eq-kpi-ikon" style="background:#FEE9E9;color:#DC2626">
            <IkonStat nama="menunggu" :ukuran="21" />
          </span>
          <div class="eq-kpi-isi">
            <span class="eq-kpi-label">Belum Comply</span>
            <span class="eq-kpi-nilai">{{ ringkas.notComply }}</span>
            <span class="eq-kpi-ket">butir menunggu tindak lanjut</span>
          </div>
        </div>

        <div class="eq-kpi">
          <span class="eq-kpi-ikon" style="background:#E8ECF0;color:#0F1720">
            <IkonStat nama="audit" :ukuran="21" />
          </span>
          <div class="eq-kpi-isi">
            <span class="eq-kpi-label">Kewajiban Terdaftar</span>
            <span class="eq-kpi-nilai">{{ ringkas.subjek }}</span>
            <span class="eq-kpi-ket"><b class="num">{{ ringkas.total }}</b> butir diidentifikasi</span>
          </div>
        </div>

        <div class="eq-kpi">
          <span class="eq-kpi-ikon" style="background:#FFF2E2;color:#DC6E00">
            <IkonStat nama="penilaian" :ukuran="21" />
          </span>
          <div class="eq-kpi-isi">
            <span class="eq-kpi-label">Belum Dinilai</span>
            <span class="eq-kpi-nilai">{{ ringkas.belum }}</span>
            <span class="eq-kpi-ket">
              butir belum diputuskan
              <template v-if="ringkas.na">· <b class="num">{{ ringkas.na }}</b> N/A tidak jadi pembagi</template>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Draf hasil rangkuman tidak ikut dihitung; itu harus disebut,
         bukan disembunyikan di balik angka yang tampak lengkap. -->
    <div v-if="ringkas.draf" class="flex items-start gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
      <span class="text-[13px]">⧗</span>
      <p class="text-[12px] text-amber-900 leading-relaxed">
        Ada <b class="num">{{ ringkas.draf }}</b> kewajiban berstatus <b>draf</b> hasil rangkuman yang belum
        diperiksa. Selama masih draf, isinya tidak ikut dihitung di angka mana pun pada halaman ini.
        <a :href="tautan.register" class="font-bold underline">Buka register</a>.
      </p>
    </div>

    <!-- Pemenuhan per aspek: yang paling tertinggal lebih dulu. -->
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Pemenuhan per Aspek</h3>
        <span class="eq-panel-ket">Diurutkan dari yang paling tertinggal</span>
      </div>

      <div v-if="aspek.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="a in aspek" :key="a.nama" class="kpt-aspek">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full text-white"
                  :style="{ background: a.warna }">{{ a.nama }}</span>
            <span class="text-[15px] font-extrabold num" :style="{ color: nada(a.persen) }">
              {{ teksPersen(a.persen) }}
            </span>
          </div>

          <div class="kpt-pita">
            <div class="kpt-pita-isi" :style="{ width: (a.persen ?? 0) + '%', background: nada(a.persen) }"></div>
          </div>

          <p class="mt-1.5 text-[10.5px] text-stone-400 num">
            {{ a.comply }} comply · {{ a.notComply }} not comply · {{ a.na }} N/A
            <template v-if="a.belum"> · {{ a.belum }} belum</template>
          </p>

          <button type="button" class="mt-1.5 text-[10.5px] font-bold text-cam-orange hover:underline"
                  @click="ubahSaring({ aspek: a.aspek ?? '' })">Lihat kewajibannya →</button>
        </div>
      </div>

      <p v-else class="eq-kosong eq-kosong-kecil">
        <strong>Belum ada yang dinilai pada tahun {{ saring.tahun }}.</strong>
        <span class="block halus">Tambahkan kewajibannya, atau unggah naskah peraturan untuk dipecah otomatis.</span>
      </p>
    </section>

    <!-- Tren bulanan, dari rekap yang sudah ditandatangani. -->
    <section v-if="tren.length" class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Tren Pemenuhan {{ saring.tahun }}</h3>
        <span class="eq-panel-ket">Dari rekap bulanan yang sudah disimpan</span>
      </div>

      <TrenPemenuhan :tren="tren" />
    </section>

    <div class="grid gap-5 xl:grid-cols-[1.6fr_1fr]">
      <!-- Daftar kerja: butir yang belum comply, paling mendesak dulu. -->
      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Menunggu Tindak Lanjut</h3>
          <span class="eq-panel-ket">{{ menunggu.length }} butir belum comply</span>
        </div>

        <div v-if="menunggu.length" class="space-y-2">
          <div v-for="m in menunggu" :key="m.id"
               class="rounded-xl border border-stone-200 p-3"
               :class="m.lewat ? 'border-red-200 bg-red-50/40' : ''">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <a v-if="m.url" :href="m.url" class="text-[11.5px] font-bold text-cam-ink hover:underline">
                  {{ m.nomor }}
                </a>
                <p class="text-[10.5px] text-stone-400">{{ m.judul }}</p>
              </div>
              <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full"
                    :class="m.lewat ? 'bg-red-600 text-white' : 'bg-stone-100 text-stone-600'">
                {{ m.target ?? 'belum dijadwalkan' }}
              </span>
            </div>

            <p class="mt-1.5 text-[12px] font-semibold text-cam-ink">{{ m.penunjuk }}</p>
            <p class="text-[11.5px] text-stone-500 leading-snug">{{ m.rangkuman ?? '—' }}</p>

            <p v-if="m.tindak" class="mt-1 text-[11.5px] text-stone-600">
              <b>Tindak lanjut:</b> {{ m.tindak }}
            </p>
            <p v-if="m.pic" class="text-[11px] text-stone-400"><b>PIC:</b> {{ m.pic }}</p>
          </div>
        </div>

        <p v-else class="eq-kosong eq-kosong-kecil">
          <strong>Tidak ada butir yang belum comply.</strong>
          <span class="block halus">Periksa juga berapa yang belum dinilai sama sekali di kartu atas.</span>
        </p>
      </section>

      <!-- Kewajiban terdaftar yang belum punya satu butir pun. -->
      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Belum Dirinci Butirnya</h3>
        </div>

        <p class="text-[11.5px] text-stone-500 leading-relaxed mb-3">
          Kewajiban berikut sudah terdaftar tetapi belum punya satu baris butir pun, jadi belum ikut
          menentukan persentase di atas.
        </p>

        <div v-if="kosong.length" class="space-y-2">
          <a v-for="k in kosong" :key="k.id" :href="k.url"
             class="block rounded-xl border border-stone-200 p-2.5 hover:border-cam-orange transition">
            <span class="block text-[11.5px] font-bold text-cam-ink">{{ k.nomor }}</span>
            <span class="block text-[10.5px] text-stone-400">{{ k.judul }}</span>
          </a>
        </div>

        <p v-else class="eq-kosong eq-kosong-kecil">
          <strong>Semuanya sudah dirinci.</strong>
        </p>
      </section>
    </div>
  </div>
</template>

<style scoped>
/* ── sorotan angka utama ── */
.kpt-utama { display: grid; gap: 1rem; }

@media (min-width: 72rem) {
  .kpt-utama { grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr); align-items: stretch; }
  .kpt-tiga  { grid-template-columns: 1fr; align-content: stretch; }
}

.kpt-sorot {
  display: flex; align-items: center; gap: 1.4rem; flex-wrap: wrap;
  background: #fff; border: 1px solid rgba(27, 32, 36, .07); border-radius: 18px;
  padding: 1.25rem 1.4rem;
  box-shadow: 0 1px 3px rgba(34, 49, 47, .05), 0 14px 40px -18px rgba(34, 49, 47, .2);
}

.kpt-sorot-label { font-size: 12px; font-weight: 700; color: #78716C; letter-spacing: .01em; }
.kpt-sorot-ket   { font-size: 11.5px; color: #A8A29E; line-height: 1.5; margin-top: .3rem; max-width: 34rem; }

.kpt-pecahan { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .8rem; }

.kpt-keping {
  display: inline-flex; align-items: baseline; gap: .3rem;
  border-radius: 999px; padding: .2rem .6rem;
  font-size: 10.5px; font-weight: 600;
}
.kpt-keping b { font-size: 13px; font-weight: 800; }

.kpt-keping.is-ok     { background: #E7F8ED; color: #15803D; }
.kpt-keping.is-nok    { background: #FEE9E9; color: #B91C1C; }
.kpt-keping.is-na     { background: #F0EFEE; color: #57534E; }
.kpt-keping.is-kosong { background: #FFF2E2; color: #B45309; }

/* ── kartu aspek ── */
.kpt-aspek {
  border: 1px solid #E7E5E4; border-radius: .9rem; padding: .85rem;
  transition: border-color .18s, transform .18s cubic-bezier(.21, .6, .35, 1);
}
.kpt-aspek:hover { border-color: #D6D3D1; transform: translateY(-2px); }

.kpt-pita {
  margin-top: .55rem; height: 7px; border-radius: 999px;
  background: #F0EFEE; overflow: hidden;
}
.kpt-pita-isi {
  height: 100%; border-radius: 999px;
  transition: width .6s cubic-bezier(.21, .6, .35, 1);
}
</style>
