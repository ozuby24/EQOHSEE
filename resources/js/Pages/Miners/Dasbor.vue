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
import { Head, Link, usePage } from '@inertiajs/vue3';
import { KEADAAN } from '../../Grafik/warna';

const props = usePage<any>().props as any;

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

    <section class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-1">{{ props.subjudul }}</p>
      </div>
      <div class="flex gap-2">
        <Link href="/miners" class="eq-btn-lain">Kelayakan kerja</Link>
        <Link href="/miners/mcu" class="eq-btn-utama">Pengajuan MCU</Link>
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
        Keputusan MCU, Mine Permit, dan Mine License dipegang tim OHSE. Selama belum ada
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

        <!-- Yang sedang pergi disebut TERPISAH dari yang tidak layak,
             dan kalimatnya menegaskan bedanya. Menggabungkan keduanya
             membuat orang yang cutinya sah terbaca sebagai temuan. -->
        <div v-if="props.pergi?.length" class="mt-3 pt-3 border-t border-stone-100">
          <p class="text-[11px] text-stone-500">
            {{ props.pergi.length }} orang sedang tidak di lokasi (field break atau cuti) —
            mereka tetap memenuhi syarat, hanya sedang tidak di sini:
            <span class="text-stone-600">{{ props.pergi.map((x: any) => x.nama).join(', ') }}</span>
          </p>
        </div>
      </section>
    </div>

    <!-- ══ jumlah keseluruhan ══ -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Link v-for="k in (props.kartu ?? [])" :key="k.label" :href="k.jalur"
            class="rounded-2xl bg-white border border-stone-100 shadow-card p-5 block
                   hover:border-stone-200 transition-colors">
        <p class="text-[28px] font-bold leading-none num" :style="{ color: warna(k) }">{{ k.nilai }}</p>
        <p class="text-[11.5px] text-stone-500 mt-1.5">{{ k.label }}</p>
      </Link>
    </section>
  </div>
</template>
