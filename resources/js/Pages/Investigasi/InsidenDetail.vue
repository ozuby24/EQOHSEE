<script setup lang="ts">
/**
 * Rincian satu insiden — dan pintu membuka berkas investigasinya.
 *
 * Tombol "Buka investigasi" hanya muncul sesudah triase. Levelnya yang
 * menentukan berapa tahap yang harus dilalui berkas itu, dan membuka
 * berkas sebelum levelnya diketahui berarti berkasnya lahir dengan
 * jalur penuh enam tahap — termasuk bagi kejadian yang di lapangan
 * selesai dalam satu pagi.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { propHalaman } from '../../halaman';
import { KEADAAN } from '../../Grafik/warna';

const props = propHalaman();

function buka() {
  router.post(`/investigasi/insiden/${props.insiden.id}/buka`, {}, { preserveScroll: true });
}
</script>

<template>
  <Head :title="props.insiden?.nomor ?? 'Insiden'" />

  <div class="max-w-[1200px] mx-auto space-y-5">
    <section class="flex flex-wrap items-start justify-between gap-4">
      <div class="min-w-0">
        <p class="text-[11.5px] text-stone-400 num">{{ props.insiden?.nomor }}</p>
        <h2 class="text-xl font-bold text-cam-ink">{{ props.insiden?.judul }}</h2>
        <p class="text-[12.5px] text-stone-500 mt-0.5">
          {{ props.insiden?.tanggal }}<span v-if="props.insiden?.waktu"> · {{ props.insiden.waktu }}</span>
          <span v-if="props.insiden?.lokasi"> · {{ props.insiden.lokasi }}</span>
        </p>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <Link v-if="!props.insiden?.ditriase" :href="`/investigasi/insiden/${props.insiden.id}/triase`"
              class="eq-btn-utama">Triase insiden →</Link>

        <Link v-else-if="props.insiden?.invId" :href="`/investigasi/berkas/${props.insiden.invId}`"
              class="eq-btn-utama">Buka berkas {{ props.insiden.invNomor }} →</Link>

        <span v-else class="inline-flex">
          <button type="button" class="eq-btn-utama" @click="buka">Buka investigasi</button>
        </span>
      </div>
    </section>

    <!-- ══════════ pita tenggat regulasi ══════════ -->
    <section v-if="props.insiden?.wajibLapor"
             class="rounded-2xl px-5 py-4 border"
             :style="props.insiden?.terlambatLapor
               ? { background: '#FEF2F2', borderColor: '#FECACA' }
               : { background: '#F6EEDF', borderColor: '#E4DCCB' }">
      <p class="text-[12.5px] font-bold" :style="{ color: props.insiden?.terlambatLapor ? KEADAAN.gawat : '#0F766E' }">
        {{ props.insiden?.terlambatLapor
            ? 'Lewat tenggat pelaporan ke Kepala Inspektur Tambang.'
            : 'Wajib dilaporkan ke Kepala Inspektur Tambang.' }}
      </p>
      <p class="text-[11.5px] text-stone-600 mt-1">
        Tenggat lapor <b class="num">{{ props.insiden?.tenggatLapor }}</b> ·
        tenggat mulai menyelidiki <b class="num">{{ props.insiden?.tenggatSelidik }}</b>.
        Keduanya dihitung dari waktu kejadian, bukan waktu pelaporan.
      </p>
    </section>

    <div class="grid gap-4 lg:grid-cols-[1.15fr_.85fr] items-start">
      <div class="grid gap-4">
        <!-- ══════════ uraian ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Uraian kejadian <span class="font-normal text-stone-400">| menurut pelapor</span>
            </h3>
          </header>
          <div class="px-5 py-4 grid gap-3">
            <p class="text-[12.5px] text-cam-ink whitespace-pre-line">{{ props.insiden?.kronologi || '—' }}</p>

            <div v-if="props.insiden?.tindakanSegera" class="pt-3 border-t border-stone-100">
              <p class="text-[10px] uppercase tracking-wide text-stone-400 mb-1">Tindakan segera</p>
              <p class="text-[12.5px] text-cam-ink whitespace-pre-line">{{ props.insiden.tindakanSegera }}</p>
            </div>
          </div>
        </section>

        <!-- ══════════ orang yang terlibat ══════════ -->
        <section v-if="(props.insiden?.orang ?? []).length"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Orang terlibat <span class="font-normal text-stone-400">| {{ props.insiden.orang.length }} orang</span>
            </h3>
          </header>

          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-[11.5px]">
              <thead>
                <tr class="text-stone-400 border-b border-stone-200 bg-stone-50/70">
                  <th class="px-4 py-2 font-semibold">Nama</th>
                  <th class="px-4 py-2 font-semibold">Jabatan</th>
                  <th class="px-4 py-2 font-semibold">Peran</th>
                  <th class="px-4 py-2 font-semibold">Cedera</th>
                  <th class="px-4 py-2 font-semibold text-right whitespace-nowrap">Hari hilang</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in props.insiden.orang" :key="o.id" class="border-b border-stone-100">
                  <td class="px-4 py-2.5 font-semibold text-cam-ink">{{ o.nama }}</td>
                  <td class="px-4 py-2.5 text-stone-500">{{ o.jabatan || '—' }}</td>
                  <td class="px-4 py-2.5 capitalize text-stone-500">{{ o.peran }}</td>
                  <td class="px-4 py-2.5 text-stone-500">{{ o.rincianCedera || '—' }}</td>
                  <td class="px-4 py-2.5 num text-right">{{ o.hariHilang ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </div>

      <div class="grid gap-4">
        <!-- ══════════ hasil triase ══════════ -->
        <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Hasil triase <span class="font-normal text-stone-400">| level dan sebabnya</span>
            </h3>
          </header>

          <div v-if="props.insiden?.ditriase" class="px-5 py-4 grid gap-3">
            <div class="flex items-center gap-3">
              <span class="rounded-lg px-3 py-2 text-[18px] font-bold num"
                    :style="{ background: props.insiden.warnaPita + '1F', color: props.insiden.warnaPita }">
                {{ props.insiden.skor }}
              </span>
              <div>
                <p class="text-[14px] font-bold" :style="{ color: props.insiden.warnaPita }">
                  {{ props.insiden.levelNama }}
                </p>
                <p class="text-[11px] text-stone-500">
                  Kemungkinan {{ props.insiden.kemungkinan }} × keparahan
                  {{ Math.max(props.insiden.keparahan ?? 0, props.insiden.keparahanPotensial ?? 0) }}
                </p>
              </div>
            </div>

            <p v-if="props.insiden?.regulasi" class="text-[12px] text-cam-ink">
              <span class="text-stone-400">Klasifikasi:</span> {{ props.insiden.regulasi }}
            </p>

            <!-- Kriteria yang TIDAK terpenuhi disebut satu per satu. -->
            <div v-if="(props.insiden?.kriteriaKurang ?? []).length"
                 class="rounded-xl px-3.5 py-2.5" style="background:#FEF3C7;color:#92400E">
              <p class="text-[11.5px] font-bold mb-1">Belum memenuhi kriteria kecelakaan tambang:</p>
              <ul class="text-[11.5px] list-disc pl-4 grid gap-0.5">
                <li v-for="k in props.insiden.kriteriaKurang" :key="k">{{ k }}</li>
              </ul>
            </div>
          </div>

          <p v-else class="px-5 py-8 text-center text-[12px] text-stone-400">
            Belum ditriase — levelnya belum dinilai siapa pun.
          </p>
        </section>

        <!-- ══════════ pemicu yang dicentang pelapor ══════════ -->
        <section v-if="(props.insiden?.pemicu ?? []).length"
                 class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
          <header class="px-5 py-3.5 border-b border-stone-100">
            <h3 class="text-[13.5px] font-bold text-cam-ink">
              Keadaan saat kejadian <span class="font-normal text-stone-400">| menurut pelapor</span>
            </h3>
          </header>
          <div class="px-5 py-4 flex flex-wrap gap-1.5">
            <span v-for="p in props.insiden.pemicu" :key="p"
                  class="rounded-md px-2 py-1 text-[10.5px] font-semibold"
                  style="background:#F6EEDF;color:#0F766E">{{ p }}</span>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>
