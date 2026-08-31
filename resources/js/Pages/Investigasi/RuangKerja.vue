<script setup lang="ts">
/**
 * Ruang kerja investigasi — satu layar untuk seluruh berkas.
 *
 * SATU LAYAR, BUKAN ENAM TAB. Yang dikerjakan di sini adalah satu
 * rantai: bukti menyokong akar masalah, akar melahirkan temuan, temuan
 * melahirkan tindakan perbaikan. Memecahnya menjadi tab membuat rantai
 * itu tidak pernah terlihat utuh — dan rantai yang tidak terlihat adalah
 * rantai yang putus tanpa ada yang menyadarinya.
 *
 * Kolom kanan berisi apa yang MENJELASKAN berkas ini: bukti, tim,
 * keterangan, pembelajaran, dan ringkasan rantainya. Kolom kiri berisi
 * apa yang DIKERJAKAN: kronologi, akar masalah, temuan, tindakan.
 */
import { Head, Link } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';
import RelTahap from './RelTahap.vue';

const props = propHalaman();

const WARNA_STATUS: Record<string, string> = {
  terbuka:      '#B45309',
  berjalan:     '#0F766E',
  selesai:      KEADAAN.baik,
  diverifikasi: KEADAAN.baik,
  ditutup:      '#78716C',
};
</script>

<template>
  <Head :title="props.inv?.nomor ?? 'Investigasi'" />

  <div class="max-w-[1500px] mx-auto space-y-5">

    <!-- ══════════ kepala ══════════ -->
    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11.5px] text-stone-400">
          <Link :href="`/investigasi/insiden/${props.inv?.insiden?.id}`" class="num hover:underline">
            {{ props.inv?.insiden?.nomor }}
          </Link>
          · {{ props.inv?.insiden?.tanggal }}
        </p>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.inv?.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          <span class="num font-semibold">{{ props.inv?.nomor }}</span>
          · Ketua: {{ props.inv?.ketua || '—' }}
          <span v-if="props.inv?.target"> · Target: {{ props.inv.target }}</span>
        </p>
      </div>

      <div class="text-right shrink-0">
        <span class="rounded-lg px-2.5 py-1.5 text-[12px] font-bold"
              :style="{ background: props.inv?.warnaPita + '1F', color: props.inv?.warnaPita }">
          {{ props.inv?.levelNama }}
        </span>
        <p class="text-[11px] text-stone-500 mt-1 num">Skor risiko {{ props.inv?.skor }}</p>
      </div>
    </section>

    <!-- ══════════ rel tahap ══════════ -->
    <RelTahap :rel="props.inv?.rel ?? []" :tugas="props.inv?.tugas"
              :kurang="props.inv?.kurang ?? []" :boleh-maju="props.inv?.bolehMaju" />

    <!-- Metode wajib menurut levelnya — disebut, bukan dipaksakan.
         Menilai kecocokan metode adalah pekerjaan KTT, bukan palang
         yang menghentikan orang di tengah pekerjaan. -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card px-5 py-3.5">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <div>
          <p class="text-[10px] uppercase tracking-wide text-stone-400">Metode yang diwajibkan level ini</p>
          <div class="flex flex-wrap gap-1.5 mt-1">
            <span v-for="m in (props.inv?.metodeWajib ?? [])" :key="m"
                  class="rounded-md px-2 py-1 text-[10.5px] font-bold uppercase"
                  style="background:#F6EEDF;color:#0F766E">{{ m }}</span>
          </div>
        </div>
        <div class="ml-auto text-right">
          <p class="text-[10px] uppercase tracking-wide text-stone-400">Penyetuju penutupan</p>
          <p class="text-[12px] font-semibold text-cam-ink">{{ props.inv?.penyetuju || '—' }}</p>
        </div>
      </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.3fr_.7fr] items-start">

      <!-- ══════════════ KOLOM KIRI — yang dikerjakan ══════════════ -->
      <div class="grid gap-4">

        <!-- kronologi -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Rekonstruksi kronologi
              <span class="font-normal text-stone-400">| {{ (props.inv?.kronologi ?? []).length }} peristiwa</span>
            </h3>
          </header>

          <ul v-if="(props.inv?.kronologi ?? []).length" class="divide-y divide-stone-100">
            <li v-for="k in props.inv.kronologi" :key="k.id" class="px-5 py-3 flex items-start gap-3">
              <span class="text-[11px] text-stone-400 num w-28 shrink-0">{{ k.waktu || '—' }}</span>
              <span class="min-w-0 flex-1">
                <span class="text-[12.5px] font-semibold text-cam-ink">
                  {{ k.peristiwa }}
                  <!-- Peristiwa yang diduga penyebab ditandai: kata pada
                       peristiwa inilah yang dibaca mesin saran SCAT. -->
                  <span v-if="k.penyebab" class="ml-1.5 rounded px-1.5 py-0.5 text-[10px] font-bold align-middle"
                        :style="{ background: KEADAAN.serius + '1F', color: KEADAAN.serius }">penyebab</span>
                </span>
                <span v-if="k.keterangan" class="block text-[11.5px] text-stone-500 mt-0.5">{{ k.keterangan }}</span>
              </span>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Belum ada peristiwa dicatat.</p>
        </section>

        <!-- akar masalah -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Akar masalah <span class="font-normal text-stone-400">| {{ (props.inv?.akar ?? []).length }} akar</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Setiap akar masalah harus dapat ditelusuri ke bukti. Akar tanpa bukti adalah pendapat,
              dan pendapat tidak bertahan di depan Inspektur Tambang.
            </p>
          </header>

          <ul v-if="(props.inv?.akar ?? []).length" class="divide-y divide-stone-100">
            <li v-for="a in props.inv.akar" :key="a.id" class="px-5 py-3">
              <p class="text-[12.5px] font-semibold text-cam-ink">{{ a.uraian }}</p>

              <div class="flex flex-wrap items-center gap-2 mt-1.5">
                <span v-if="a.taksonomi" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                      style="background:#F6EEDF;color:#0F766E">{{ a.taksonomi.metode }}</span>
                <span v-if="a.taksonomi" class="text-[11px] text-stone-500 num">
                  {{ a.taksonomi.kode }} — {{ a.taksonomi.label }}
                </span>
                <span v-else class="text-[11px] text-stone-400">5 Why</span>
              </div>

              <p class="text-[11px] mt-1"
                 :style="{ color: a.disokong ? KEADAAN.baik : KEADAAN.ingat }">
                {{ a.disokong ? '✓ disokong ' + a.bukti.join(', ') : '⚠ belum disokong bukti' }}
              </p>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Akar masalah belum dirumuskan.</p>
        </section>

        <!-- temuan dan tindakan -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Temuan &amp; tindakan perbaikan
              <span class="font-normal text-stone-400">| {{ (props.inv?.temuan ?? []).length }} temuan</span>
            </h3>
          </header>

          <ul v-if="(props.inv?.temuan ?? []).length" class="divide-y divide-stone-100">
            <li v-for="t in props.inv.temuan" :key="t.id" class="px-5 py-4">
              <div class="flex items-start gap-2 flex-wrap">
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold num"
                      style="background:#F5F5F4;color:#78716C">{{ t.nomor }}</span>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold capitalize"
                      :style="{ background: (t.tingkat === 'tinggi' ? KEADAAN.gawat : KEADAAN.ingat) + '1F',
                                color: t.tingkat === 'tinggi' ? KEADAAN.gawat : KEADAAN.ingat }">
                  {{ t.tingkat }}
                </span>
              </div>

              <p class="text-[12.5px] font-semibold text-cam-ink mt-1.5">{{ t.uraian }}</p>
              <p v-if="t.rekomendasi" class="text-[11.5px] text-stone-500 italic mt-0.5">
                Rekomendasi: {{ t.rekomendasi }}
              </p>

              <ul class="mt-2.5 grid gap-1.5">
                <li v-for="x in t.tindakan" :key="x.id"
                    class="rounded-xl border border-stone-100 px-3 py-2 flex flex-wrap items-center gap-2">
                  <span class="rounded px-1.5 py-0.5 text-[10px] font-bold num"
                        style="background:#F5F5F4;color:#78716C">{{ x.nomor }}</span>

                  <span class="text-[12px] text-cam-ink flex-1 min-w-0">{{ x.uraian }}</span>

                  <!-- Tingkat hierarki pengendalian tercantum: berkas
                       yang seluruh tindakannya bertingkat 4 dan 5 adalah
                       berkas yang tidak mengubah apa pun di lapangan. -->
                  <span v-if="x.hierarki" class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                        :style="{ background: (x.tingkat <= 3 ? KEADAAN.baik : '#B45309') + '1F',
                                  color: x.tingkat <= 3 ? KEADAAN.baik : '#B45309' }">
                    {{ x.hierarki }}
                  </span>

                  <span class="rounded px-1.5 py-0.5 text-[10px] font-bold"
                        :style="{ background: (WARNA_STATUS[x.status] ?? '#78716C') + '1F',
                                  color: WARNA_STATUS[x.status] ?? '#78716C' }">
                    {{ x.statusLabel }}
                  </span>

                  <span class="text-[10.5px] text-stone-400 num whitespace-nowrap">
                    {{ x.pic || '—' }} · {{ x.tenggat || 'tanpa tenggat' }}
                  </span>

                  <span v-if="x.telat" class="text-[10.5px] font-bold num" :style="{ color: KEADAAN.gawat }">
                    telat {{ x.telat }} hari
                  </span>
                </li>
              </ul>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Belum ada temuan dirumuskan.</p>
        </section>
      </div>

      <!-- ══════════════ KOLOM KANAN — yang menjelaskan ══════════════ -->
      <div class="grid gap-4">

        <!-- bukti -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Bukti <span class="font-normal text-stone-400">| {{ (props.inv?.bukti ?? []).length }} berkas</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Tiap bukti disidik jarinya dengan SHA-256 lalu dikunci. Bukti yang bisa diam-diam diganti
              sesudah dikumpulkan tidak ada nilainya saat pemeriksaan.
            </p>
          </header>

          <ul v-if="(props.inv?.bukti ?? []).length" class="divide-y divide-stone-100">
            <li v-for="b in props.inv.bukti" :key="b.id" class="px-5 py-3">
              <div class="flex items-start gap-2">
                <span class="min-w-0 flex-1">
                  <span class="text-[12px] font-semibold text-cam-ink block">{{ b.judul }}</span>
                  <span class="text-[10.5px] text-stone-400 num block">
                    {{ b.nomor }} · {{ b.jenisLabel }}<span v-if="b.tanggal"> · {{ b.tanggal }}</span>
                  </span>
                  <span v-if="b.sidik" class="text-[10px] text-stone-300 num block truncate">
                    sha256 {{ b.sidik }}…
                  </span>
                </span>

                <span class="shrink-0 flex items-center gap-1.5">
                  <a v-if="b.url" :href="b.url" target="_blank" rel="noopener"
                     class="text-[11px] font-bold text-cam-lime-deep hover:underline">Buka</a>
                  <span v-if="b.dikunci" :title="`Dikunci oleh ${b.pengunci ?? '—'}`"
                        class="text-[12px]" :style="{ color: KEADAAN.baik }">🔒</span>
                </span>
              </div>
            </li>
          </ul>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">Belum ada bukti dikumpulkan.</p>
        </section>

        <!-- tim -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Tim investigasi <span class="font-normal text-stone-400">| {{ (props.inv?.tim ?? []).length }} anggota</span>
            </h3>
          </header>

          <ul v-if="(props.inv?.tim ?? []).length" class="divide-y divide-stone-100">
            <li v-for="t in props.inv.tim" :key="t.id" class="px-5 py-2.5 flex items-center justify-between gap-3">
              <span class="text-[12px] font-semibold text-cam-ink">{{ t.nama }}</span>
              <span class="text-[11px] text-stone-400">{{ t.peran || '—' }}</span>
            </li>
          </ul>

          <p v-else class="px-5 py-6 text-center text-[12px] text-stone-400">Tim belum dibentuk.</p>
        </section>

        <!-- keterangan -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Keterangan investigasi <span class="font-normal text-stone-400">| syarat tahap Perencanaan</span>
            </h3>
          </header>

          <dl class="px-5 py-4 grid gap-2.5">
            <div>
              <dt class="text-[10px] uppercase tracking-wide text-stone-400">Tujuan</dt>
              <dd class="text-[12px] text-cam-ink">{{ props.inv?.tujuan || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-wide text-stone-400">Ruang lingkup</dt>
              <dd class="text-[12px] text-cam-ink">{{ props.inv?.ruangLingkup || '—' }}</dd>
            </div>
            <div>
              <dt class="text-[10px] uppercase tracking-wide text-stone-400">Prioritas</dt>
              <dd class="text-[12px] text-cam-ink capitalize">{{ props.inv?.prioritas }}</dd>
            </div>
          </dl>
        </section>

        <!-- pembelajaran -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Pembelajaran <span class="font-normal text-stone-400">| {{ (props.inv?.pembelajaran ?? []).length }} terbit</span>
            </h3>
            <p class="text-[11px] text-stone-500 mt-0.5">
              Yang dibaca site lain bukan seluruh berkas ini, melainkan satu paragraf. Tanpa
              pembelajaran yang disebarkan, penyebab yang sama akan ditemukan lagi di tempat lain
              dari awal.
            </p>
          </header>

          <ul v-if="(props.inv?.pembelajaran ?? []).length" class="divide-y divide-stone-100">
            <li v-for="p in props.inv.pembelajaran" :key="p.id" class="px-5 py-3">
              <p class="text-[12.5px] font-semibold text-cam-ink">{{ p.judul }}</p>
              <p class="text-[11.5px] text-stone-500 mt-1">{{ p.ringkasan }}</p>
              <p v-if="p.pesanKunci" class="text-[11.5px] mt-1.5 rounded-lg px-3 py-2"
                 style="background:#F6EEDF;color:#0F766E">{{ p.pesanKunci }}</p>
            </li>
          </ul>

          <p v-else class="px-5 py-6 text-center text-[12px] text-stone-400">Belum ada pembelajaran terbit.</p>
        </section>

        <!-- ringkasan rantai -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">Ringkasan rantai</h3>
          </header>

          <div class="px-5 py-4">
            <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
              <span v-for="(x, i) in [
                      { n: props.inv?.rantai?.bukti, l: 'bukti' },
                      { n: props.inv?.rantai?.akar, l: 'akar' },
                      { n: props.inv?.rantai?.temuan, l: 'temuan' },
                      { n: props.inv?.rantai?.tindakan, l: 'tindakan' },
                    ]" :key="x.l" class="flex items-center gap-1.5">
                <span class="rounded-md px-2 py-1 font-bold num"
                      style="background:#F6EEDF;color:#0F766E">{{ x.n ?? 0 }} {{ x.l }}</span>
                <span v-if="i < 3" class="text-stone-300">→</span>
              </span>
            </div>

            <p class="text-[11.5px] text-stone-500 mt-3">
              Investigasi hanya boleh ditutup bila setiap temuan sudah punya sedikitnya satu tindakan,
              dan setiap tindakan sudah diverifikasi efektif.
            </p>

            <ul v-if="(props.inv?.kurangTutup ?? []).length" class="mt-2.5 grid gap-1">
              <li v-for="k in props.inv.kurangTutup" :key="k" class="text-[11px]" style="color:#92400E">
                · {{ k }}
              </li>
            </ul>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
