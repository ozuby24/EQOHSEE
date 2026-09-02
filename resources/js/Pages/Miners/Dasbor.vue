<script setup lang="ts">
/**
 * Ringkasan Authority.
 *
 * Susunannya mengikuti pertanyaan yang dibawa orang pagi hari, bukan
 * bentuk tabelnya: apa yang menunggu saya, lalu siapa yang tertahan,
 * baru jumlah keseluruhan.
 *
 * ANGKA BESAR DILETAKKAN PALING BAWAH, dan itu bertentangan dengan
 * kebiasaan dasbor. Sebabnya: "3.959 MCU" tidak menuntut apa pun dari
 * pembacanya — ia hanya memberi tahu bahwa datanya banyak. Yang
 * menuntut tindakan adalah dua bagian di atasnya, dan yang diletakkan
 * paling atas seharusnya yang dibaca lebih dulu, bukan yang paling
 * mudah dibuat mengesankan.
 *
 * Tiap kartu angka dapat ditekan. Angka yang tidak menuntun ke barisnya
 * memaksa pembacanya mencari sendiri lewat menu, dan yang dicari selalu
 * barisnya — bukan angkanya.
 */
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

/** Sebaran dengan nilai bawaan — halaman tidak boleh pecah saat propnya belum ada. */
const sebaran = computed(() => props.sebaran ?? { total: 0, aman: 0, perhatian: 0, takLayak: 0 });

/**
 * Juring donat, sudah lengkap dengan titik mulainya.
 *
 * Offset dihitung BERANTAI dari juring sebelumnya, bukan dari indeksnya:
 * dihitung dari indeks, tiga juring yang panjangnya tidak sama akan
 * saling menumpuk dan yang tergambar bukan galat melainkan donat yang
 * juringnya salah besar.
 *
 * Total nol memulangkan daftar kosong, sehingga yang tergambar hanya
 * cincin abu-abunya. Membagi dengan nol menghasilkan NaN pada
 * stroke-dasharray, dan SVG dengan NaN tidak menggambar apa pun sama
 * sekali — lingkarannya hilang tanpa satu pun pesan.
 */
const juring = computed(() => {
  const s = sebaran.value;
  const total = s.total || 0;

  if (!total) return [];

  const isi = [
    { label: 'Aman bekerja',      nilai: s.aman,      warna: '#16A34A' },
    { label: 'Perlu perhatian',   nilai: s.perhatian, warna: '#F59E0B' },
    { label: 'Tidak boleh bekerja', nilai: s.takLayak, warna: '#DC2626' },
  ];

  let jalan = 0;

  return isi.map((j) => {
    const panjang = j.nilai / total * 100;
    const mulai = -jalan;
    jalan += panjang;

    return { ...j, panjang, mulai, persen: Math.round(panjang) };
  });
});

const persenAman = computed(() => {
  const s = sebaran.value;

  return s.total ? Math.round(s.aman / s.total * 100) : 0;
});

/* Perisai TIDAK selalu hijau. Perisai hijau di atas berkas yang
   bermasalah adalah gambar yang berbohong, dan gambar itu yang paling
   dulu dipercaya orang saat membuka halaman. */
const warnaPerisai = computed(() =>
  persenAman.value === 100 ? '#16A34A' : persenAman.value >= 70 ? '#F59E0B' : '#DC2626');

const NADA: Record<string, string> = {
  netral: KEADAAN.netral,
  ingat:  KEADAAN.ingat,
  serius: KEADAAN.serius,
  gawat:  KEADAAN.gawat,
};

/**
 * Angka nol tidak diberi warna peringatan.
 *
 * Kartu "Rujukan tertunggak: 0" yang tetap merah melatih orang
 * mengabaikan merah — dan warna yang diabaikan tidak lagi memperingatkan
 * apa pun ketika angkanya benar-benar naik.
 */
function warna(k: { nilai: number; nada: string }): string {
  return k.nilai > 0 ? (NADA[k.nada] ?? KEADAAN.netral) : KEADAAN.netral;
}

const menunggu = () =>
  (props.menunggu?.mcu?.length ?? 0)
  + (props.menunggu?.kartu?.length ?? 0)
  + (props.menunggu?.lain?.length ?? 0);
</script>

<template>
  <Head :title="props.judul" />

  <div class="max-w-[1400px] mx-auto space-y-5">

    <!-- ══════════ hero ══════════
         Judulnya dicetak DI SINI, bukan di kepala halaman. Kepala
         halaman kini menyapa orangnya; mencetak judul di keduanya
         membuat kalimat yang sama muncul dua kali berjarak dua
         sentimeter — persis keadaan sebelum perubahan ini. -->
    <section class="miners-hero">
      <div class="miners-hero-isi">
        <div class="min-w-0">
          <h2>{{ props.judul }}</h2>
          <p>{{ props.subjudul }}</p>
        </div>
        <div class="miners-hero-aksi">
          <Link href="/miners" class="eq-btn-lain">Kelayakan kerja</Link>
          <Link href="/miners/mcu" class="eq-btn-utama">Pengajuan MCU</Link>
        </div>
      </div>

      <!-- Pita kelayakan hari ini. Menyebut ANGKANYA, bukan sekadar
           "ada masalah": "3 dari 24 orang" dapat ditindaklanjuti,
           "ada masalah" hanya membuat orang membuka halaman lain. -->
      <div class="miners-pita" :class="(props.sebaran?.takLayak ?? 0) ? 'miners-pita-gawat' : 'miners-pita-aman'">
        <span class="miners-pita-ikon" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1"
               stroke-linecap="round" stroke-linejoin="round">
            <path v-if="(props.sebaran?.takLayak ?? 0)" d="M12 8.5v4.2M12 16.2h.01M10.3 4.2 2.8 17.5a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3L13.7 4.2a2 2 0 0 0-3.4 0Z"/>
            <path v-else d="m5 12.5 4.5 4.5L19 7.5"/>
          </svg>
        </span>
        <span class="min-w-0 flex-1">
          <strong v-if="(props.sebaran?.takLayak ?? 0)">Tidak boleh bekerja hari ini</strong>
          <strong v-else>Seluruh {{ props.sebaran?.total ?? 0 }} orang memenuhi syarat masuk hari ini.</strong>
          <small v-if="(props.sebaran?.takLayak ?? 0)">
            Induksi, MCU, atau kartu masuk kadaluarsa, belum ada, belum disetujui,
            atau hasil MCU menyatakan tidak layak.
          </small>
        </span>
        <span v-if="(props.sebaran?.takLayak ?? 0)" class="miners-pita-angka num">
          {{ props.sebaran.takLayak }} dari {{ props.sebaran.total }} orang
        </span>
      </div>
    </section>

    <!-- ══════════ tiga kartu ringkasan ══════════ -->
    <section class="grid gap-3 lg:grid-cols-3">

      <!-- Sebaran kelayakan. Juringnya berjumlah persis sebanyak orang
           terdaftar; donat yang tidak berjumlah totalnya membuat orang
           mencari ke mana sisanya pergi. -->
      <div class="miners-kartu">
        <header>
          <h3>Ringkasan Hari Ini</h3>
          <p>Status kelayakan kerja</p>
        </header>

        <div class="miners-kartu-isi flex items-center gap-5">
          <svg viewBox="0 0 42 42" class="miners-donat" role="img"
               :aria-label="`${sebaran.aman} aman, ${sebaran.perhatian} perlu perhatian, ${sebaran.takLayak} tidak boleh bekerja`">
            <circle cx="21" cy="21" r="15.9" fill="none" stroke="var(--eq-garis,#E7E5E4)" stroke-width="6"/>
            <circle v-for="j in juring" :key="j.label" cx="21" cy="21" r="15.9" fill="none"
                    :stroke="j.warna" stroke-width="6"
                    :stroke-dasharray="`${j.panjang} ${100 - j.panjang}`"
                    :stroke-dashoffset="j.mulai" transform="rotate(-90 21 21)"/>
            <text x="21" y="20" class="miners-donat-angka">{{ sebaran.total }}</text>
            <text x="21" y="25.5" class="miners-donat-teks">Total</text>
          </svg>

          <ul class="min-w-0 flex-1 space-y-1.5">
            <li v-for="j in juring" :key="'l' + j.label" class="flex items-center gap-2 text-[11.5px]">
              <span class="miners-titik" :style="{ background: j.warna }"></span>
              <span class="flex-1 text-stone-600">{{ j.label }}</span>
              <span class="num font-bold text-cam-ink">{{ j.nilai }}</span>
              <span class="num text-stone-400 w-11 text-right">({{ j.persen }}%)</span>
            </li>
          </ul>
        </div>

        <Link href="/miners" class="miners-kaki">Data per orang <span aria-hidden="true">&rsaquo;</span></Link>
      </div>

      <!-- Yang perlu diperpanjang. Bilahnya berskala terhadap JUMLAH
           ORANG, bukan terhadap nilai terbesar di antara keempatnya:
           skala yang mengikuti nilai terbesar membuat satu induksi
           kadaluarsa tampil sepanjang bilah penuh. -->
      <div class="miners-kartu">
        <header>
          <h3>Yang Perlu Diperpanjang</h3>
          <p>Segera lakukan perpanjangan</p>
        </header>

        <ul class="miners-kartu-isi space-y-2.5">
          <li v-for="b in (props.perpanjang ?? [])" :key="b.label" class="flex items-center gap-2.5">
            <span class="miners-chip" :style="{ background: b.warna + '1F', color: b.warna }">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 7.5V12l3 1.8M12 3.5a8.5 8.5 0 1 1 0 17 8.5 8.5 0 0 1 0-17Z"/>
              </svg>
            </span>
            <span class="text-[11.5px] text-stone-600 w-[112px] shrink-0">{{ b.label }}</span>
            <span class="miners-bilah"><i :style="{ width: (b.nilai / b.maks * 100) + '%', background: b.warna }"></i></span>
            <span class="num text-[12px] font-bold w-4 text-right"
                  :style="{ color: b.nilai ? b.warna : '#A8A29E' }">{{ b.nilai }}</span>
          </li>
        </ul>

        <Link href="/miners/kedaluwarsa" class="miners-kaki">Lihat angkanya <span aria-hidden="true">&rsaquo;</span></Link>
      </div>

      <!-- Perisai. Warnanya mengikuti keadaan, bukan selalu hijau:
           perisai hijau di atas berkas yang bermasalah adalah gambar
           yang berbohong. -->
      <div class="miners-kartu">
        <header>
          <h3>Masa Berlaku Seluruh Berkas</h3>
          <p>{{ persenAman === 100 ? 'Berlaku aman sesuai ketentuan' : 'Sebagian berkas perlu ditindaklanjuti' }}</p>
        </header>

        <div class="miners-kartu-isi text-center">
          <svg viewBox="0 0 24 24" class="miners-perisai" :style="{ color: warnaPerisai }" aria-hidden="true">
            <path fill="currentColor" opacity=".16"
                  d="M12 2.5 4.5 5.6v6.1c0 4.7 3.2 9.1 7.5 10.3 4.3-1.2 7.5-5.6 7.5-10.3V5.6L12 2.5Z"/>
            <path fill="none" stroke="currentColor" stroke-width="1.6"
                  d="M12 2.5 4.5 5.6v6.1c0 4.7 3.2 9.1 7.5 10.3 4.3-1.2 7.5-5.6 7.5-10.3V5.6L12 2.5Z"/>
            <path fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round"
                  stroke-linejoin="round" d="m8.6 12.2 2.4 2.4 4.4-4.6"/>
          </svg>
          <p class="text-[22px] font-bold leading-none num text-cam-ink mt-1">{{ sebaran.total }}</p>
          <p class="text-[11px] text-stone-500">orang</p>
          <p class="text-[12.5px] font-bold mt-1.5" :style="{ color: warnaPerisai }">
            <span class="num">{{ persenAman }}%</span> {{ persenAman === 100 ? 'Aman' : 'Berlaku aman' }}
          </p>
        </div>

        <Link href="/miners/kedaluwarsa" class="miners-kaki">Detail lengkap <span aria-hidden="true">&rsaquo;</span></Link>
      </div>
    </section>

    <!-- Kebuntuan yang tidak terlihat dari mana pun: tanpa seorang pun
         bertanda OHSE, seluruh pengajuan menumpuk pada "menunggu
         tinjauan" dan tombol setujuinya tidak muncul bagi siapa pun. -->
    <section v-if="props.menunggu && props.menunggu.adaOhse === false"
             class="rounded-2xl bg-white border border-amber-300 shadow-card p-5">
      <h3 class="text-[14px] font-bold" :style="{ color: KEADAAN.serius }">
        Belum ada pengguna bertanda OHSE
      </h3>
      <p class="text-[12px] text-stone-600 mt-1">
        Keputusan MCU, Mine Permit, dan SIMPER dipegang tim OHSE. Selama belum ada
        satu pun pengguna yang ditandai, seluruh pengajuan akan menumpuk pada
        "menunggu tinjauan" dan tombol setujuinya tidak muncul bagi siapa pun.
        Tandai lewat <b>Admin → Pengguna → Peran OHSE</b>.
      </p>
    </section>

    <div class="grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">

      <!-- ══ menunggu keputusan saya ══ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div>
            <h3 class="text-[14px] font-bold text-cam-ink">Menunggu persetujuan Anda</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">
              Hanya yang benar-benar Anda putuskan — bukan seluruh antrean.
            </p>
          </div>
          <span class="text-[22px] font-bold leading-none num shrink-0"
                :style="{ color: menunggu() ? KEADAAN.ingat : KEADAAN.baik }">{{ menunggu() }}</span>
        </div>

        <ul v-if="menunggu()" class="divide-y divide-stone-100">
          <li v-for="m in props.menunggu.mcu" :key="'m'+m.id" class="py-2.5">
            <Link href="/miners/mcu" class="text-[12.5px] font-semibold text-cam-lime-deep">
              {{ m.nomor }}
            </Link>
            <span class="text-[11px] text-stone-400"> · MCU · {{ m.jumlah }} nama</span>
            <p v-if="m.tertinggal?.length" class="text-[10.5px] mt-0.5" :style="{ color: KEADAAN.ingat }">
              Belum diparaf: {{ m.tertinggal.join(', ') }}
            </p>
          </li>
          <li v-for="k in props.menunggu.kartu" :key="'k'+k.id" class="py-2.5">
            <Link :href="`/miners/${k.pasporId}`" class="text-[12.5px] font-semibold text-cam-lime-deep">
              {{ k.nama }}
            </Link>
            <span class="text-[11px] text-stone-400"> · {{ k.jenis }} · {{ k.sebab }}</span>
            <p v-if="k.tertinggal?.length" class="text-[10.5px] mt-0.5" :style="{ color: KEADAAN.ingat }">
              Belum diparaf: {{ k.tertinggal.join(', ') }}
            </p>
          </li>
          <li v-for="(l, n) in (props.menunggu.lain ?? [])" :key="'l'+n" class="py-2.5">
            <Link :href="l.jalur" class="text-[12.5px] font-semibold text-cam-lime-deep">
              {{ l.sebutan }}
            </Link>
            <span class="text-[11px] text-stone-400"> · {{ l.apa }} · {{ l.terang }}</span>
          </li>
        </ul>

        <!-- "Kosong" dibedakan dari "bukan urusan Anda". Keduanya
             menampilkan nol, dan menyamakannya membuat orang menyangka
             sistemnya rusak. -->
        <p v-else-if="props.menunggu?.sayaPenentu" class="text-[12px] py-4 text-center"
           :style="{ color: KEADAAN.baik }">
          Tidak ada yang menunggu keputusan Anda.
        </p>
        <p v-else class="text-[12px] py-4 text-center text-stone-400">
          Persetujuan kartu masuk dan MCU dipegang tim OHSE.
        </p>
      </section>

      <!-- ══ yang tertahan hari ini ══ -->
      <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
        <div class="flex items-start justify-between gap-3 mb-3">
          <div>
            <h3 class="text-[14px] font-bold text-cam-ink">Tidak boleh bekerja hari ini</h3>
            <p class="text-[11.5px] text-stone-500 mt-0.5">Beserta sebabnya, bukan hanya vonisnya.</p>
          </div>
          <Link href="/miners" class="text-[11px] font-semibold text-cam-lime-deep shrink-0">
            Seluruhnya →
          </Link>
        </div>

        <ul v-if="props.mendesak?.length" class="divide-y divide-stone-100">
          <li v-for="o in props.mendesak" :key="o.id" class="py-2.5 flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ background: KEADAAN.gawat }"></span>
            <Link :href="`/miners/${o.id}`"
                  class="text-[12.5px] font-semibold text-cam-ink hover:text-cam-lime-deep truncate w-40">
              {{ o.nama }}
            </Link>
            <span class="text-[11.5px] font-semibold ml-auto text-right"
                  :style="{ color: KEADAAN.gawat }">{{ o.sebab.join(' · ') }}</span>
          </li>
        </ul>
        <p v-else class="text-[12px] py-4 text-center" :style="{ color: KEADAAN.baik }">
          Seluruh pekerja memenuhi syarat masuk hari ini.
        </p>
      </section>
    </div>

    <!-- ══ jumlah keseluruhan ══ -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Link v-for="k in (props.kartu ?? [])" :key="k.label" :href="k.jalur"
            class="miners-ubin">
        <span class="miners-chip miners-chip-besar"
              :style="{ background: (k.warna ?? '#78716C') + '1F', color: k.warna ?? '#78716C' }">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path :d="k.ikon"/>
          </svg>
        </span>
        <span class="min-w-0">
          <span class="miners-ubin-angka num" :style="{ color: warna(k) }">{{ k.nilai }}</span>
          <span class="miners-ubin-label">{{ k.label }}</span>
        </span>
      </Link>
    </section>
  </div>
</template>
