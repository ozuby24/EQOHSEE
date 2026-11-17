<script setup lang="ts">
/**
 * Pustaka daftar periksa yang sudah jadi.
 *
 * Menyusun register dari nol berarti mengetik tiga puluh sampai lima
 * puluh baris sebelum satu pun penilaian dapat dikerjakan — dan itulah
 * yang membuat modul evaluasi pemenuhan sering berhenti di baris
 * keenam. Di sini pekerjaannya sudah dikerjakan; yang tersisa menilai.
 *
 * Yang sudah pernah diterbitkan untuk tahun dan perusahaan yang sama
 * DITANDAI, dan tombolnya berubah menjadi tautan ke register yang ada.
 * Menerbitkan dua kali tidak menimbulkan galat apa pun — ia hanya
 * menggandakan tiga puluh butir yang lalu dinilai dua orang berbeda
 * dengan jawaban berbeda, dan angka pemenuhannya menghitung keduanya.
 */
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import type { HalamanKepatuhanPustaka } from '../../types';
import PindahKepatuhan from './Pindah.vue';

const props = defineProps<HalamanKepatuhanPustaka>();

const tahun = ref(String(props.saring.tahun));
const menerbitkan = ref<string | null>(null);

function terbitkan(kunci: string) {
  menerbitkan.value = kunci;

  router.post(props.tautan.terbitkan, {
    kunci,
    tahun: tahun.value,
    company_id: props.saring.perusahaan ?? '',
  }, { onFinish: () => { menerbitkan.value = null; } });
}

const buka = ref<string | null>(null);

const pilih = 'ring-focus rounded-xl border border-stone-200 bg-white pl-3 pr-9 py-2 text-[12px] font-semibold text-stone-600';
</script>

<template>
  <Head title="Pustaka Daftar Periksa" />

  <div class="space-y-5">
    <PindahKepatuhan :tautan="tautan" kini="pustaka" />

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="grid gap-1">
          <span class="block text-[10.5px] font-bold uppercase tracking-wide text-stone-400">
            Terbitkan untuk tahun evaluasi
          </span>
          <select v-model="tahun" :class="pilih">
            <option v-for="t in opsi.tahun" :key="t" :value="String(t)">{{ t }}</option>
          </select>
        </label>

        <p class="text-[11.5px] text-stone-500 max-w-xl leading-relaxed">
          Daftar periksa yang diterbitkan masuk ke register sebagai kewajiban berstatus
          <b>Tetap</b>, dan seluruh butirnya <b>belum dinilai</b> — bukan N/A. Daftar periksa
          yang lahir sudah bernilai adalah daftar periksa yang tidak akan pernah dibaca.
        </p>
      </div>
    </div>

    <div class="grid gap-5 xl:grid-cols-2 items-start">
      <article v-for="p in pustaka" :key="p.kunci" class="kpt-kartu">
        <header class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="kpt-lencana" :style="{ background: p.warna }">{{ p.aspek }}</span>
              <span class="kpt-sumber">{{ opsi.sumberNama[p.sumber] }}</span>
              <span class="kpt-jumlah num">{{ p.jumlah }} butir</span>
            </div>
            <h3 class="kpt-nama">{{ p.nama }}</h3>
            <p class="kpt-nomor num">{{ p.nomor }}</p>
          </div>
        </header>

        <p class="kpt-judul">{{ p.judul }}</p>
        <p class="kpt-ket">{{ p.ket }}</p>

        <!-- Contoh butirnya diperlihatkan sebelum diterbitkan.
             Menerbitkan lima puluh baris tanpa melihat satu pun di
             antaranya adalah keputusan yang diambil tanpa dasar. -->
        <button type="button" class="kpt-intip" @click="buka = buka === p.kunci ? null : p.kunci">
          {{ buka === p.kunci ? '▴ Sembunyikan contoh butirnya' : '▾ Lihat contoh butirnya' }}
        </button>

        <ol v-if="buka === p.kunci" class="kpt-contoh">
          <li v-for="(b, i) in p.contoh" :key="i">
            <b>{{ b.penunjuk }}</b>
            <span>{{ b.uraian }}</span>
          </li>
          <li v-if="p.jumlah > p.contoh.length" class="kpt-sisa">
            … dan <b class="num">{{ p.jumlah - p.contoh.length }}</b> butir lainnya.
          </li>
        </ol>

        <p class="kpt-acuan">{{ p.acuan }}</p>

        <footer class="flex flex-wrap items-center gap-2 mt-3">
          <template v-if="p.sudah">
            <a :href="`/kepatuhan/${p.sudah}`" class="eq-btn-lain" style="flex:none">
              Sudah diterbitkan — buka registernya →
            </a>
            <span class="text-[11px] text-stone-400">untuk tahun {{ saring.tahun }}</span>
          </template>

          <button v-else type="button" class="eq-btn-utama" style="flex:none;padding:9px 18px"
                  :disabled="menerbitkan === p.kunci" @click="terbitkan(p.kunci)">
            {{ menerbitkan === p.kunci ? 'Menerbitkan…' : `Terbitkan ${p.jumlah} Butir` }}
          </button>
        </footer>
      </article>
    </div>

    <p class="text-[11.5px] text-stone-500 leading-relaxed px-1 max-w-3xl">
      Pustaka ini memuat <b>nomor klausul, judulnya, dan pertanyaan pemeriksaan</b> yang disusun
      sendiri. Teks persyaratan standar ISO berhak cipta dan tidak dimuat di sini — organisasi tetap
      perlu memegang salinan resminya, dan daftar ini indeks penelusuran, bukan penggantinya.
    </p>
  </div>
</template>

<style scoped>
.kpt-kartu {
  background: #fff; border: 1px solid rgba(27, 32, 36, .07); border-radius: 18px;
  padding: 1.15rem 1.25rem;
  box-shadow: 0 1px 3px rgba(34, 49, 47, .05), 0 14px 40px -18px rgba(34, 49, 47, .2);
}

.kpt-lencana {
  color: #fff; font-size: 9.5px; font-weight: 800;
  padding: .12rem .45rem; border-radius: 999px;
}
.kpt-sumber {
  background: #F0EFEE; color: #57534E; font-size: 9.5px; font-weight: 700;
  padding: .12rem .45rem; border-radius: 999px;
}
.kpt-jumlah { font-size: 10px; color: #A8A29E; font-weight: 700; }

.kpt-nama  { font-size: 14.5px; font-weight: 800; color: #0F1720; margin-top: .45rem; line-height: 1.3; }
.kpt-nomor { font-size: 11px; color: #A8A29E; margin-top: .1rem; }
.kpt-judul { font-size: 12.5px; color: #44403C; margin-top: .5rem; line-height: 1.45; }
.kpt-ket   { font-size: 11.5px; color: #78716C; margin-top: .5rem; line-height: 1.55; }

.kpt-intip {
  margin-top: .6rem; font-size: 11px; font-weight: 700; color: #DC6E00;
}
.kpt-intip:hover { text-decoration: underline; }

.kpt-contoh {
  margin-top: .5rem; padding: .7rem .8rem;
  border: 1px solid #E7E5E4; border-radius: .8rem; background: #FAFAF9;
  display: grid; gap: .5rem;
}
.kpt-contoh li { font-size: 11.5px; line-height: 1.45; }
.kpt-contoh b  { display: block; color: #0F1720; font-size: 11px; }
.kpt-contoh span { color: #78716C; }
.kpt-sisa { color: #A8A29E; font-style: italic; }
/* Angkanya sebaris dengan kalimatnya. `.kpt-contoh b` di atas
   membuat penunjuk butir berdiri sendiri di atas uraiannya — di baris
   ini yang dibungkus <b> justru sebuah angka di tengah kalimat, dan
   block memecahnya menjadi tiga baris. */
.kpt-sisa b { display: inline; color: inherit; font-size: inherit; }

.kpt-acuan {
  margin-top: .7rem; padding-top: .6rem; border-top: 1px solid #F5F5F4;
  font-size: 10.5px; color: #A8A29E; line-height: 1.5;
}
</style>
