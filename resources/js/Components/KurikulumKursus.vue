<script setup lang="ts">
/**
 * Daftar isi kursus yang menempel di sisi ruang belajar.
 *
 * Inilah yang membuat seluruh materi tampil seragam. Sebelumnya setiap
 * materi hanya sebaris tautan di dalam daftar modul, dan satu-satunya
 * cara mengetahui sudah sampai mana adalah mengingatnya sendiri.
 *
 * ── Modul yang sedang dibuka terbuka, sisanya tertutup ──
 *
 * Kursus dengan delapan modul dan empat puluh materi yang seluruhnya
 * terbuka menjadi daftar sepanjang tiga layar, dan materi yang sedang
 * dikerjakan tenggelam di tengahnya. Yang tertutup tetap menunjukkan
 * jumlah materinya dan berapa yang tuntas — cukup untuk memutuskan
 * membukanya atau tidak, tanpa harus membukanya dulu.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import type { ModulKurikulum } from '../types';
import IkonStat from './IkonStat.vue';

const props = defineProps<{
  modul: ModulKurikulum[];
  /** Judul kursus dan tautan kembali ke halaman kursusnya. */
  kursus: {
    judul: string; url: string; progres: number;
    /* Angka materi, BUKAN progres kursus.
       Bilah di sini berdiri tepat di atas centang per materi; menyebut
       persentase modul di situ membantah centangnya sendiri — dua
       materi tuntas dari tiga, tetapi "0% selesai". */
    materiTuntas: number; materiTotal: number;
  };
}>();

/** Modul mana yang sedang terbuka. Kuncinya id, bukan indeks. */
const buka = ref<Record<number, boolean>>({});

/**
 * Buka modul yang memuat materi berjalan; biarkan pilihan orangnya.
 *
 * `watch` dengan immediate, bukan sekali saat dipasang: berpindah ke
 * materi berikutnya lewat tombol "Berikutnya" adalah kunjungan Inertia
 * yang MEMPERTAHANKAN komponen ini, sehingga kode yang hanya berjalan
 * saat dipasang tidak pernah berjalan lagi — dan modul berikutnya tetap
 * tertutup meski materinya sedang dibuka.
 */
watch(() => props.modul, (daftar) => {
  daftar.forEach((m) => {
    if (m.materi.some((x) => x.kini)) buka.value[m.id] = true;
  });
}, { immediate: true, deep: true });

function tuntas(m: ModulKurikulum) {
  return m.materi.filter((x) => x.selesai).length;
}

/**
 * Materi mana yang sedang dibuka — alamatnya, bukan penandanya.
 *
 * Inertia MEMPERTAHANKAN halaman lama sampai jawabannya tiba, jadi
 * menekan satu butir kurikulum tidak mengubah apa pun di layar selama
 * satu-dua detik. Di sambungan site tambang itu cukup lama untuk
 * membuat orang menekannya dua kali, lalu menekan butir lain karena
 * mengira yang pertama tidak masuk.
 *
 * Bilah muat global di puncak halaman memang menyala, tetapi ia tidak
 * menjawab pertanyaan yang sedang ditanyakan orangnya: yang MANA yang
 * sedang dibuka.
 */
const memuat = ref<string | null>(null);

const lepas: Array<() => void> = [];

onMounted(() => {
  lepas.push(router.on('start', (e: any) => {
    const tujuan = String(e?.detail?.visit?.url ?? '');

    /* Hanya perpindahan ke salah satu butir DI SINI yang ditandai.
       Menandai setiap kunjungan membuat butir acak menyala ketika
       orangnya menekan menu di bilah samping. */
    memuat.value = props.modul.some((m) => m.materi.some((x) => tujuan.endsWith(x.url)))
      ? tujuan
      : null;
  }));

  /* `finish`, bukan `success`: ia menyala juga untuk kunjungan yang
     gagal, dibatalkan, atau disela — dan di situlah penanda paling
     mungkin tertinggal menyala selamanya. */
  lepas.push(router.on('finish', () => { memuat.value = null; }));
});

onBeforeUnmount(() => lepas.forEach((f) => f()));

function sedangDibuka(url: string) {
  return memuat.value !== null && memuat.value.endsWith(url);
}

/* Pembagi nol dijaga. Kursus yang belum berisi materi sama sekali
   menghasilkan NaN, dan `width: NaN%` diam-diam diabaikan peramban —
   bilahnya tergambar penuh, bukan kosong. */
const persenMateri = computed(() => (props.kursus.materiTotal
  ? Math.round((props.kursus.materiTuntas / props.kursus.materiTotal) * 100)
  : 0));
</script>

<template>
  <aside class="eq-kur">
    <div class="eq-kur-kepala">
      <Link :href="kursus.url" class="eq-kur-balik">← {{ kursus.judul }}</Link>

      <div class="eq-kur-laju" role="img"
           :aria-label="`${kursus.materiTuntas} dari ${kursus.materiTotal} materi selesai`">
        <span :style="{ width: persenMateri + '%' }"></span>
      </div>
      <p class="eq-kur-persen">{{ kursus.materiTuntas }} dari {{ kursus.materiTotal }} materi selesai</p>
    </div>

    <ol class="eq-kur-modul">
      <li v-for="m in modul" :key="m.id">
        <button type="button" class="eq-kur-judul"
                :aria-expanded="buka[m.id] === true"
                @click="buka[m.id] = !buka[m.id]">
          <span class="eq-kur-tanda" :class="{ 'is-tuntas': m.selesai }" aria-hidden="true">
            <svg v-if="m.selesai" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
            <template v-else>{{ m.urutan }}</template>
          </span>

          <span class="eq-kur-teks">
            <strong>{{ m.judul }}</strong>
            <small>{{ tuntas(m) }} dari {{ m.materi.length }} materi</small>
          </span>

          <svg class="eq-kur-panah" :class="{ 'is-buka': buka[m.id] }" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"
               stroke-linejoin="round" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
        </button>

        <ul v-show="buka[m.id]" class="eq-kur-materi">
          <li v-for="x in m.materi" :key="x.id">
            <!--
              `aria-current`, bukan sekadar kelas berwarna. Yang memakai
              pembaca layar tidak melihat sorotannya, dan daftar empat
              puluh butir tanpa penanda mana yang sedang dibuka tidak
              dapat ditelusuri sama sekali.
            -->
            <Link :href="x.url" class="eq-kur-butir"
                  :class="{ 'is-kini': x.kini, 'is-memuat': sedangDibuka(x.url) }"
                  :aria-current="x.kini ? 'page' : undefined"
                  :aria-busy="sedangDibuka(x.url) || undefined">
              <span v-if="sedangDibuka(x.url)" class="eq-kur-putar" aria-hidden="true" />

              <span v-else class="eq-kur-kotak" :class="{ 'is-tuntas': x.selesai }" aria-hidden="true">
                <svg v-if="x.selesai" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
              </span>

              <span class="eq-kur-butir-teks">
                <span class="eq-kur-butir-judul">{{ x.judul }}</span>
                <span class="eq-kur-butir-ket">
                  <IkonStat :nama="x.ikon" :ukuran="12" />
                  {{ x.label }}<template v-if="x.durasi"> · {{ x.durasi }}</template>
                </span>
              </span>
            </Link>
          </li>

          <li v-if="!m.materi.length" class="eq-kur-hampa">Modul ini belum berisi materi.</li>
        </ul>
      </li>
    </ol>
  </aside>
</template>

<style scoped>
.eq-kur {
  border: 1px solid #E7E5E4;
  border-radius: 18px;
  background: #fff;
  overflow: hidden;

  /* Menempel, dan tingginya dibatasi supaya ia bergulir SENDIRI alih-alih
     mendorong halaman. Kursus berempat puluh materi yang tidak dibatasi
     membuat sisi kanan lebih panjang daripada isinya, dan halaman
     berhenti dapat digulir pada titik yang masuk akal. */
  position: sticky;
  top: 1rem;
  max-height: calc(100dvh - 2rem);
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
}

.eq-kur-kepala {
  padding: .95rem 1.05rem;
  border-bottom: 1px solid #F5F5F4;
  background: #FCFCFB;
}

.eq-kur-balik {
  display: block;
  font-size: 12px;
  font-weight: 700;
  color: #1B2024;
  text-decoration: none;
  line-height: 1.4;
}

.eq-kur-balik:hover { color: var(--eq-aksen, #D96500); }

.eq-kur-laju {
  height: 5px;
  margin-top: .6rem;
  border-radius: 99px;
  background: #E7E5E4;
  overflow: hidden;
}

.eq-kur-laju span {
  display: block;
  height: 100%;
  border-radius: 99px;
  background: linear-gradient(90deg, #F57C00, #FF9800);
  transition: width .3s ease;
}

.eq-kur-persen { margin: .35rem 0 0; font-size: 11px; font-weight: 700; color: #A8A29E; }

.eq-kur-modul {
  min-height: 0;
  overflow-y: auto;
  margin: 0;
  padding: 0;
  list-style: none;
}

.eq-kur-modul > li + li { border-top: 1px solid #F5F5F4; }

.eq-kur-judul {
  display: flex;
  align-items: center;
  gap: .6rem;
  width: 100%;
  padding: .75rem .9rem;
  border: 0;
  background: none;
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.eq-kur-judul:hover { background: #FAFAF9; }

.eq-kur-tanda {
  display: grid;
  place-items: center;
  flex: none;
  width: 1.4rem;
  height: 1.4rem;
  border-radius: 99px;
  border: 1.5px solid #D6D3D1;
  font-size: 10.5px;
  font-weight: 800;
  color: #A8A29E;
}

.eq-kur-tanda.is-tuntas {
  border-color: transparent;
  background: #0F9D52;
  color: #fff;
}

.eq-kur-tanda svg { width: .75rem; height: .75rem; }

.eq-kur-teks { flex: 1; min-width: 0; }
.eq-kur-teks strong { display: block; font-size: 12.5px; font-weight: 700; color: #1B2024; line-height: 1.35; }
.eq-kur-teks small { display: block; font-size: 10.5px; color: #A8A29E; margin-top: 1px; }

.eq-kur-panah { flex: none; width: .9rem; height: .9rem; color: #A8A29E; transition: transform .18s ease; }
.eq-kur-panah.is-buka { transform: rotate(180deg); }

.eq-kur-materi { margin: 0; padding: 0 .55rem .55rem; list-style: none; }

.eq-kur-butir {
  display: flex;
  align-items: flex-start;
  gap: .55rem;
  padding: .45rem .5rem;
  border-radius: 10px;
  text-decoration: none;
  transition: background .15s ease;
}

.eq-kur-butir:hover { background: #FAFAF9; }

/* Sorotan materi berjalan: bilah di tepi kiri, bukan hanya latar.
   Latar yang samar sendirian tidak cukup terbaca di layar tablet yang
   dipakai di lapangan pada cahaya matahari. */
.eq-kur-butir.is-kini {
  background: color-mix(in srgb, var(--eq-aksen, #F57C00) 9%, transparent);
  box-shadow: inset 3px 0 0 var(--eq-aksen, #F57C00);
}

.eq-kur-kotak {
  display: grid;
  place-items: center;
  flex: none;
  width: 1.05rem;
  height: 1.05rem;
  margin-top: .1rem;
  border-radius: 5px;
  border: 1.5px solid #D6D3D1;
  color: #fff;
}

.eq-kur-kotak.is-tuntas { border-color: transparent; background: #0F9D52; }

/* Menggantikan kotak centang di tempat yang sama, bukan menambah satu
   lambang di sebelahnya: baris yang bertambah lebar saat ditekan
   menggeser seluruh daftar di bawahnya. */
.eq-kur-putar {
  flex: none;
  width: 1.05rem;
  height: 1.05rem;
  margin-top: .1rem;
  border-radius: 99px;
  border: 2px solid color-mix(in srgb, var(--eq-aksen, #F57C00) 28%, transparent);
  border-top-color: var(--eq-aksen, #F57C00);
  animation: eq-kur-putar .62s linear infinite;
}

@keyframes eq-kur-putar { to { transform: rotate(360deg); } }

.eq-kur-butir.is-memuat { background: color-mix(in srgb, var(--eq-aksen, #F57C00) 7%, transparent); }

@media (prefers-reduced-motion: reduce) {
  .eq-kur-putar {
    animation: none;
    border-color: color-mix(in srgb, var(--eq-aksen, #F57C00) 60%, transparent);
  }
}
.eq-kur-kotak svg { width: .62rem; height: .62rem; }

.eq-kur-butir-teks { min-width: 0; }

.eq-kur-butir-judul {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: #44403C;
  line-height: 1.4;
}

.eq-kur-butir.is-kini .eq-kur-butir-judul { font-weight: 800; color: #1B2024; }

.eq-kur-butir-ket {
  display: flex;
  align-items: center;
  gap: .25rem;
  font-size: 10.5px;
  color: #A8A29E;
  margin-top: 1px;
}

.eq-kur-hampa { padding: .5rem; font-size: 11px; color: #A8A29E; font-style: italic; }

/* ── mode gelap ── */
:global([data-tema='gelap']) .eq-kur { background: #141F23; border-color: #223238; }
:global([data-tema='gelap']) .eq-kur-kepala { background: #0F1A1E; border-bottom-color: #223238; }
:global([data-tema='gelap']) .eq-kur-balik,
:global([data-tema='gelap']) .eq-kur-teks strong,
:global([data-tema='gelap']) .eq-kur-butir.is-kini .eq-kur-butir-judul { color: #E8EFF2; }
:global([data-tema='gelap']) .eq-kur-butir-judul { color: #C4CDD2; }
:global([data-tema='gelap']) .eq-kur-modul > li + li { border-top-color: #223238; }
:global([data-tema='gelap']) .eq-kur-judul:hover,
:global([data-tema='gelap']) .eq-kur-butir:hover { background: rgba(255, 255, 255, .045); }
:global([data-tema='gelap']) .eq-kur-laju { background: #223238; }

/* Di bawah lebar ini kurikulum pindah ke atas isi dan berhenti menempel:
   panel setinggi layar di ponsel berarti isinya sendiri tidak pernah
   terlihat tanpa menggulir melewati seluruh daftar materi. */
@media (max-width: 1023px) {
  .eq-kur { position: static; max-height: none; }
  .eq-kur-modul { max-height: 22rem; }
}
</style>
