<script setup lang="ts">
/**
 * Pemantauan masa berlaku MCU, Mine Permit, dan SIMPER.
 *
 * Menjawab satu pertanyaan yang ditanyakan tiap pagi di gerbang: siapa
 * yang hari ini tidak boleh masuk, dan siapa yang minggu depan tidak
 * boleh masuk kalau tidak ada yang mengurusnya sekarang.
 *
 * YANG DITAMPILKAN TANGGAL EFEKTIF, BUKAN YANG TERCETAK. Kartu berpijak
 * pada berkas lain — permit pada MCU, SIMPER pada SIM kepolisian — dan
 * tidak dapat hidup lebih lama daripada dasarnya. Baris yang dibatasi
 * dasarnya diberi keterangan, sebab "habis 12 September" tanpa sebab
 * membuat orang memperpanjang kartunya padahal yang perlu diperpanjang
 * MCU-nya.
 */
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';

const props = propHalaman();

/**
 * Warna pita, dipesan maknanya.
 *
 * Merah untuk yang SUDAH habis — bukan tingkat kemendesakan melainkan
 * larangan masuk hari ini. Oranye dan kuning untuk yang mendekat, hijau
 * untuk yang panjang, abu untuk yang belum bertanggal.
 */
const WARNA: Record<string, { latar: string; teks: string; garis: string }> = {
  habis:           { latar: '#FEE2E2', teks: '#B91C1C', garis: '#FCA5A5' },
  mendesak:        { latar: '#FFEDD5', teks: '#C2410C', garis: '#FDBA74' },
  dekat:           { latar: '#FEF9C3', teks: '#A16207', garis: '#FDE68A' },
  panjang:         { latar: '#DCFCE7', teks: '#15803D', garis: '#86EFAC' },
  'tak-bertanggal': { latar: '#F5F5F4', teks: '#78716C', garis: '#E7E5E4' },
};

const warna = (k: string) => WARNA[k] ?? WARNA['tak-bertanggal'];

const saring = computed(() => props.saring ?? {});

/** Menyaring lewat URL, bukan di peramban: hasilnya dapat ditautkan. */
function pasang(kunci: string, nilai: string | number | null) {
  const q: Record<string, any> = { ...saring.value };

  if (nilai === null || nilai === '' || q[kunci] === nilai) delete q[kunci];
  else q[kunci] = nilai;

  /* Kunci kosong dibuang, bukan dikirim bernilai kosong. URL yang
     memuat `?jenis=&perusahaan=&q=` menyimpan penyaring yang tidak
     dipakai, dan tautan yang disalin dari bilah alamat membawa serta
     kekosongan itu ke orang berikutnya. */
  for (const [k, v] of Object.entries(q)) {
    if (v === null || v === '' || v === undefined) delete q[k];
  }

  router.get('/miners/kedaluwarsa', q, { preserveScroll: true, preserveState: true });
}

/* Kartu ringkasan. Yang habis dan yang mendekati diletakkan lebih dulu:
   keduanya menuntut tindakan, sisanya hanya kabar baik. */
const kartu = computed(() => {
  const r = props.ringkas ?? {};

  return [
    { kunci: 'habis',    label: 'Sudah habis',   nilai: r.habis ?? 0,
      ket: 'tidak boleh masuk hari ini' },
    { kunci: 'mendesak', label: '≤ 30 hari',     nilai: r.perKeadaan?.mendesak ?? 0,
      ket: 'urus sekarang' },
    { kunci: 'dekat',    label: '31–60 hari',    nilai: r.perKeadaan?.dekat ?? 0,
      ket: 'jadwalkan' },
    { kunci: 'panjang',  label: '> 60 hari',     nilai: r.perKeadaan?.panjang ?? 0,
      ket: 'aman' },
  ];
});
</script>

<template>
  <Head :title="props.judul" />

  <div class="space-y-5">
    <!--
      Berapa ORANGNYA, bukan berapa berkasnya. Satu orang memegang MCU,
      Mine Permit, dan kerap SIMPER pula; empat pita di bawah menghitung
      berkas, dan tanpa baris ini tiga puluh pekerja terbaca sebagai
      delapan puluh tenaga kerja.
    -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-4
                    flex flex-wrap items-center gap-x-8 gap-y-3">
      <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Tenaga kerja</p>
        <strong class="num text-2xl text-cam-ink">{{ props.ringkas?.manpower ?? 0 }}</strong>
      </div>

      <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Aktif</p>
        <strong class="num text-2xl" style="color:#15803D">{{ props.ringkas?.manpowerAktif ?? 0 }}</strong>
      </div>

      <div>
        <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Tidak aktif</p>
        <strong class="num text-2xl"
                :style="{ color: props.ringkas?.manpowerNonaktif ? '#A16207' : '#A8A29E' }">
          {{ props.ringkas?.manpowerNonaktif ?? 0 }}
        </strong>
        <span class="block text-[11px] text-stone-400">cuti atau sudah keluar</span>
      </div>

      <div class="ml-auto">
        <p class="text-[11px] font-bold uppercase tracking-wide text-stone-400">Berkas dipantau</p>
        <strong class="num text-2xl text-cam-ink">{{ props.ringkas?.total ?? 0 }}</strong>
        <span class="block text-[11px] text-stone-400">MCU, Mine Permit, SIMPER</span>
      </div>
    </section>

    <!--
      Empat kartu, dan mengkliknya menyaring daftarnya. Angka yang tidak
      dapat diklik memaksa orang membaca angkanya lalu mencarinya sendiri
      di bawah — dua langkah untuk satu pertanyaan.
    -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <button v-for="k in kartu" :key="k.kunci" type="button"
              class="rounded-2xl border p-4 text-left transition hover:brightness-[.98]"
              :style="{ background: warna(k.kunci).latar,
                        borderColor: saring.keadaan === k.kunci ? warna(k.kunci).teks : warna(k.kunci).garis }"
              @click="pasang('keadaan', k.kunci)">
        <p class="text-[11px] font-bold uppercase tracking-wide"
           :style="{ color: warna(k.kunci).teks }">{{ k.label }}</p>
        <strong class="num block text-2xl mt-1" :style="{ color: warna(k.kunci).teks }">{{ k.nilai }}</strong>
        <p class="text-[11px] mt-0.5" :style="{ color: warna(k.kunci).teks, opacity: .8 }">{{ k.ket }}</p>
      </button>
    </section>

    <!--
      Angka yang memisahkan dua pekerjaan berbeda: memperpanjang kartu,
      dan memperbarui MCU atau SIM yang mendasarinya. Tanpa pemisahan
      ini keduanya tampak sebagai satu tumpukan "kartu bermasalah".
    -->
    <p v-if="props.ringkas?.dibatasiDasar" class="text-[12px] rounded-xl px-4 py-3"
       style="background:#FEF6E7;color:#92400E">
      <b>{{ props.ringkas.dibatasiDasar }} kartu</b> habis lebih dulu karena MCU atau SIM-nya,
      bukan karena kartunya sendiri. Yang perlu diperpanjang berkas dasarnya.
    </p>

    <!-- penyaring -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card p-4">
      <div class="flex flex-wrap items-center gap-2">
        <input :value="saring.q" type="search" placeholder="Cari nama, NIK, jabatan…"
               class="ring-focus rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] min-w-[200px] flex-1"
               aria-label="Cari orang"
               @change="(e: any) => pasang('q', e.target.value)">

        <select :value="saring.jenis ?? ''" class="rounded-xl border-stone-200 text-[12.5px] py-2"
                aria-label="Jenis berkas"
                @change="(e: any) => pasang('jenis', e.target.value || null)">
          <option value="">Semua jenis berkas</option>
          <option v-for="j in props.opsiJenis ?? []" :key="j" :value="j">{{ j }}</option>
        </select>

        <select :value="saring.perusahaan ?? ''" class="rounded-xl border-stone-200 text-[12.5px] py-2"
                aria-label="Perusahaan"
                @change="(e: any) => pasang('perusahaan', e.target.value || null)">
          <option value="">Semua perusahaan</option>
          <option v-for="c in props.opsiPerusahaan ?? []" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <select :value="saring.status ?? ''" class="rounded-xl border-stone-200 text-[12.5px] py-2"
                aria-label="Status kepegawaian"
                @change="(e: any) => pasang('status', e.target.value || null)">
          <option value="">Aktif dan tidak aktif</option>
          <option v-for="(label, kode) in props.opsiStatus ?? {}" :key="kode" :value="kode">{{ label }}</option>
        </select>

        <button v-if="saring.keadaan || saring.jenis || saring.perusahaan || saring.q || saring.status"
                type="button" class="eq-btn-lain" style="flex:none"
                @click="router.get('/miners/kedaluwarsa')">Bersihkan</button>
      </div>
    </section>

    <!-- per perusahaan -->
    <section v-if="props.perPerusahaan?.length"
             class="rounded-2xl bg-white border border-stone-100 shadow-card p-5">
      <h3 class="font-bold text-[14px]">Per perusahaan</h3>
      <p class="text-[11.5px] text-stone-400 mt-0.5">
        Diurutkan dari yang paling banyak masalahnya — itu yang perlu ditelepon lebih dulu.
      </p>

      <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-left text-[12px]">
          <thead>
            <tr class="text-stone-400 border-b border-stone-100">
              <th class="py-2 pr-3 font-semibold">Perusahaan</th>
              <th class="py-2 pr-3 font-semibold text-right">Tenaga kerja</th>
              <th class="py-2 pr-3 font-semibold text-right">Tidak aktif</th>
              <th class="py-2 pr-3 font-semibold text-right">Berkas</th>
              <th class="py-2 pr-3 font-semibold text-right">Berlaku</th>
              <th class="py-2 pr-3 font-semibold text-right">Mendekati</th>
              <th class="py-2 font-semibold text-right">Habis</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in props.perPerusahaan" :key="c.perusahaan" class="border-b border-stone-50">
              <td class="py-2 pr-3 font-semibold text-cam-ink">{{ c.perusahaan }}</td>
              <td class="py-2 pr-3 num text-right">{{ c.manpower }}</td>
              <td class="py-2 pr-3 num text-right"
                  :style="{ color: c.manpowerNonaktif ? '#A16207' : '#A8A29E' }">
                {{ c.manpowerNonaktif || '—' }}
              </td>
              <td class="py-2 pr-3 num text-right text-stone-500">{{ c.total }}</td>
              <td class="py-2 pr-3 num text-right" style="color:#15803D">{{ c.aktif }}</td>
              <td class="py-2 pr-3 num text-right" style="color:#A16207">{{ c.mendekati || '—' }}</td>
              <td class="py-2 num text-right font-bold" :style="{ color: c.habis ? '#B91C1C' : '#A8A29E' }">
                {{ c.habis || '—' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- daftar -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-x-auto">
      <table class="min-w-full text-left text-[12px]">
        <thead>
          <tr class="text-stone-400 border-b border-stone-100">
            <th class="px-5 py-3 font-semibold">Nama</th>
            <th class="px-5 py-3 font-semibold">Perusahaan</th>
            <th class="px-5 py-3 font-semibold">Berkas</th>
            <th class="px-5 py-3 font-semibold">Berlaku sampai</th>
            <th class="px-5 py-3 font-semibold text-right">Keadaan</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in props.baris ?? []" :key="b.id" class="border-b border-stone-50">
            <td class="px-5 py-3">
              <Link :href="`/miners/${b.pasporId}`" class="font-semibold text-cam-lime-deep py-1.5 -my-1.5">
                {{ b.nama }}
              </Link>
              <small class="block text-stone-400">{{ b.jabatan || '—' }}</small>
            </td>
            <td class="px-5 py-3 text-stone-500">
              {{ b.perusahaan || '—' }}
              <!--
                Status kepegawaian disebut hanya bila BUKAN aktif. Yang
                aktif adalah keadaan biasa, dan menuliskannya di tiap
                baris membuat yang tidak aktif justru sulit dilihat.
              -->
              <small v-if="!b.orangAktif" class="block text-[10.5px]" style="color:#A16207">
                {{ b.statusOrang === 'keluar' ? 'sudah keluar' : b.statusOrang }}
              </small>
            </td>
            <td class="px-5 py-3">
              {{ b.jenis }}
              <small v-if="b.nomor" class="block text-stone-400 num">{{ b.nomor }}</small>
              <!--
                MCU yang masih berlaku tetapi berhasil "unfit" tetap
                melarang orang bekerja. Tanpa keterangan ini pita
                hijaunya terbaca sebagai aman.
              -->
              <small v-if="b.hasil && !b.hasilLayak" class="block text-[10.5px] font-semibold"
                     style="color:#B91C1C">{{ b.hasil }}</small>
              <small v-else-if="b.hasil" class="block text-[10.5px] text-stone-400">{{ b.hasil }}</small>
            </td>
            <td class="px-5 py-3">
              <span class="num">{{ b.tglEfektif || '—' }}</span>
              <!--
                Sebabnya, bukan cuma tanggalnya. Tanpa keterangan ini
                orang memperpanjang kartunya, padahal yang habis MCU-nya.
              -->
              <small v-if="b.dibatasiDasar" class="block text-[10.5px]" style="color:#92400E">
                dibatasi {{ b.namaDasar }} ({{ b.tglDasar }})
              </small>
              <small v-else-if="b.tglTercetak" class="block text-[10.5px] text-stone-400">
                sesuai kartu
              </small>
            </td>
            <td class="px-5 py-3 text-right whitespace-nowrap">
              <span class="inline-block rounded-md px-2 py-1 text-[10.5px] font-bold"
                    :style="{ background: warna(b.keadaan).latar, color: warna(b.keadaan).teks }">
                {{ b.keadaanLabel }}
              </span>
              <small v-if="b.sisaHari !== null" class="block text-[10.5px] text-stone-400 mt-0.5 num">
                {{ b.sisaHari < 0 ? `lewat ${Math.abs(b.sisaHari)} hari` : `${b.sisaHari} hari lagi` }}
              </small>
            </td>
          </tr>

          <tr v-if="!(props.baris ?? []).length">
            <td colspan="5" class="px-5 py-10 text-center text-[13px] text-stone-500">
              Tidak ada berkas yang cocok dengan penyaring ini.
            </td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
