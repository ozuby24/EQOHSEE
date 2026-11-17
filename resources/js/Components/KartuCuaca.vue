<script setup lang="ts">
import { computed } from 'vue';

/**
 * Kartu cuaca pada sampul modul.
 *
 * Bukan lencana bertuliskan "Hujan Sedang" saja. Angka 34 mm tidak
 * berarti apa pun bagi pembaca yang tidak hafal ambang BMKG, dan label
 * tanpa angka menghapus selisih antara gerimis 0,6 mm dan 19 mm yang
 * sama-sama disebut "Hujan Ringan". Karena itu ketiganya digambar
 * sekaligus: angkanya, namanya, dan kedudukannya pada skala lima
 * tingkat — sehingga terbaca sebagai bacaan alat yang terkalibrasi,
 * bukan sebagai hiasan.
 *
 * Warnanya mengikuti beratnya hujan. Hujan sangat lebat menghentikan
 * pekerjaan di lereng dan jalan angkut; menyamakan warnanya dengan
 * gerimis menghapus perbedaan itu tepat di tempat orang membacanya
 * sekilas.
 *
 * `cuaca` boleh NULL, dan yang digambar untuk null bukan ketiadaan.
 *
 * Sebelumnya pemanggilnya menyembunyikan kartu ini seluruhnya bila
 * situsnya belum mencatat hujan. Akibatnya bukan tampilan yang lebih
 * bersih melainkan fitur yang tidak pernah terlihat: pemasangan yang
 * belum pernah mengisi penirisan tidak punya cara mengetahui bahwa
 * lencana cuaca ada, apalagi dari mana isinya datang.
 *
 * Yang tetap dipegang: TIDAK BOLEH mengaku "Cerah" tanpa catatan.
 * Lencana itu terbaca sebagai bacaan alat, dan orang mengambil
 * keputusan lapangan dari bacaan alat. Karena itu keadaan kosongnya
 * menyebut dirinya kosong — bukan menebak, bukan menghilang.
 */
const props = defineProps<{
  cuaca: {
    kunci: string;
    label: string;
    hujanMm: number;
    tingkat: number;
    skala: number;
    tanggal: string | null;
    hariIni: boolean;

    /** 'situs' = terukur alat sendiri; 'perkiraan' = dari koordinat. */
    sumber?: string;
    tempat?: string | null;
    kasar?: boolean;
  } | null;
}>();

/**
 * Sumbernya SELALU disebut, dan itu bukan keterangan tambahan.
 *
 * Yang tercatat di situs adalah bacaan alat milik perusahaan itu —
 * boleh dipakai menghentikan pekerjaan di lereng. Yang perkiraan
 * bukan, dan jarak antara keduanya kadang puluhan kilometer. Angka
 * yang sama persis tergambar untuk keduanya; satu-satunya pembeda
 * adalah baris ini.
 */
const sumber = computed(() => {
  const c = props.cuaca;
  if (!c) return null;
  if (c.sumber !== 'perkiraan') return 'Tercatat di situs';

  /* Tempatnya ikut disebut. Nama yang ketemu kerap berbeda dari nama
     yang dicari, dan selisih itulah satu-satunya tanda bagi pembacanya
     bahwa koordinatnya meleset. */
  const t = c.tempat ? ` · ${c.tempat}` : '';

  return (c.kasar ? 'Perkiraan wilayah' : 'Perkiraan') + t;
});

/**
 * Warna aksen per kelas — dipakai ikon, angka, dan meter sekaligus.
 *
 * Tangganya sengaja melompati rona, bukan hanya menggelapkan satu warna:
 * kuning → sian → biru langit → nila → merah. Percobaan pertama memakai
 * dua biru berdekatan untuk "sedang" dan "lebat", dan pada tangkapan
 * layar keduanya tidak terbedakan — padahal itu justru batas yang
 * menentukan pekerjaan di lereng dan jalan angkut dihentikan atau tidak.
 */
const AKSEN: Record<string, string> = {
  cerah:              '#FBBF24',
  hujan_ringan:       '#67E8F9',
  hujan_sedang:       '#38BDF8',
  hujan_lebat:        '#818CF8',
  hujan_sangat_lebat: '#F87171',
};

const aksen = computed(() => AKSEN[props.cuaca?.kunci ?? ''] ?? '#D6D3D1');

const cerah = computed(() => props.cuaca?.kunci === 'cerah');

/** Kotak meter: yang sudah terlewati ikut menyala, sisanya redup. */
const kotak = computed(() =>
  Array.from({ length: props.cuaca?.skala ?? 0 }, (_, i) => i <= (props.cuaca?.tingkat ?? 0)));

/** Tiga tetes dengan tundaan berbeda supaya tidak jatuh serentak. */
const TETES = [
  { x: 7,  tunda: '0s' },
  { x: 12, tunda: '.35s' },
  { x: 17, tunda: '.7s' },
];
</script>

<template>
  <!-- Belum ada catatan: disebut apa adanya, bukan ditebak dan bukan
       dihilangkan. Kalimatnya menyebut DARI MANA isinya datang, sebab
       "belum ada data" tanpa itu tidak dapat ditindaklanjuti siapa pun. -->
  <div v-if="!props.cuaca" class="eq-cuaca flex items-center gap-2.5 rounded-xl px-3 py-2"
       title="Lencana cuaca membaca curah hujan yang dicatat modul Penirisan">
    <svg class="h-7 w-7 shrink-0" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.45)"
         stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M7.4 15.6a3.9 3.9 0 0 1 .5-7.8 5.2 5.2 0 0 1 9.9 1.5 3.2 3.2 0 0 1-.7 6.3Z"/>
      <path d="M9.5 19.2 8.7 20.8M14.5 19.2l-.8 1.6"/>
    </svg>

    <div class="min-w-0 leading-tight">
      <p class="text-[11px] font-bold text-white/75">Belum ada catatan hujan</p>
      <p class="mt-0.5 text-[9.5px] text-white/45">Diisi dari modul Penirisan</p>
    </div>
  </div>

  <div v-else class="eq-cuaca flex items-center gap-3 rounded-xl px-3 py-2"
       :title="`Curah hujan tercatat ${props.cuaca.hujanMm} mm — ${props.cuaca.label}`">
    <!-- Ikon. Matahari berdenyut pelan, hujan menjatuhkan tetesnya;
         keduanya mati pada perangkat yang meminta gerak dikurangi. -->
    <svg class="h-8 w-8 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <template v-if="cerah">
        <g class="eq-cuaca-surya" :stroke="aksen" stroke-width="1.8" stroke-linecap="round">
          <circle cx="12" cy="12" r="4" :fill="aksen" fill-opacity=".28" />
          <path d="M12 3.2v2.1M12 18.7v2.1M4.8 4.8l1.5 1.5M17.7 17.7l1.5 1.5M3.2 12h2.1M18.7 12h2.1M4.8 19.2l1.5-1.5M17.7 6.3l1.5-1.5" />
        </g>
      </template>

      <template v-else>
        <path d="M7.4 15.6a3.9 3.9 0 0 1 .5-7.8 5.2 5.2 0 0 1 9.9 1.5 3.2 3.2 0 0 1-.7 6.3Z"
              :fill="aksen" fill-opacity=".22" :stroke="aksen" stroke-width="1.6"
              stroke-linejoin="round" />
        <g :stroke="aksen" stroke-width="1.9" stroke-linecap="round">
          <line v-for="t in TETES" :key="t.x" class="eq-cuaca-tetes"
                :x1="t.x" y1="17.6" :x2="t.x - 1" y2="20.4"
                :style="{ '--eq-tunda': t.tunda }" />
        </g>
      </template>
    </svg>

    <div class="min-w-0">
      <!-- Angka besar, satuan kecil. tabular-nums supaya lebarnya tidak
           bergoyang saat angkanya berganti pada halaman yang dimuat ulang. -->
      <p class="flex items-baseline gap-1 leading-none">
        <b class="text-[19px] font-extrabold tabular-nums text-white">{{ props.cuaca.hujanMm }}</b>
        <small class="text-[10px] font-bold uppercase tracking-wider text-white/60">mm</small>
      </p>

      <p class="mt-1 truncate text-[11px] font-bold leading-none" :style="{ color: aksen }">
        {{ props.cuaca.label }}
      </p>

      <!-- Meter lima tingkat: menjawab "seberapa berat" tanpa menuntut
           pembacanya hafal ambang BMKG. -->
      <div class="mt-1.5 flex items-center gap-2">
        <div class="flex gap-[3px]" role="img"
             :aria-label="`Tingkat ${props.cuaca.tingkat + 1} dari ${props.cuaca.skala} pada skala curah hujan`">
          <i v-for="(nyala, i) in kotak" :key="i"
             class="block h-[3px] w-3.5 rounded-full"
             :class="nyala && i === props.cuaca.tingkat ? 'eq-cuaca-aktif' : ''"
             :style="{ background: nyala ? aksen : 'rgba(255,255,255,.22)' }"></i>
        </div>

        <!-- Catatan yang bukan hari ini disebut tanggalnya. Tanpa itu,
             hujan kemarin tidak dapat dibedakan dari hujan pagi tadi —
             dan keduanya menuntut keputusan yang berbeda. -->
        <small v-if="!props.cuaca.hariIni && props.cuaca.tanggal"
               class="text-[9.5px] font-semibold text-white/55">{{ props.cuaca.tanggal }}</small>
      </div>

      <p v-if="sumber" class="mt-1 truncate text-[9px] leading-none text-white/45">{{ sumber }}</p>
    </div>
  </div>
</template>
