<script setup lang="ts">
/**
 * Kartu "Detail" — apa yang SUDAH diketahui sistem, seluruhnya dibaca.
 *
 * Dipakai bersama oleh layar berkas peserta dan layar Authority, dan
 * kebersamaannya disengaja. Keduanya menampilkan identitas orang yang
 * sama dari sumber yang sama; disalin menjadi dua markup, keduanya akan
 * berselisih pada perubahan berikutnya — biasanya yang jarang dibuka
 * yang tertinggal, dan justru di situ orang percaya pada yang tertulis.
 *
 * TIDAK ADA ISIAN DI SINI, dan itu bukan kelalaian. Nomor register,
 * NIK, dan jabatan punya sumbernya masing-masing; diketik ulang di
 * layar ini, salinannya akan berselisih dengan sumbernya tanpa ada yang
 * tahu mana yang benar.
 */
defineProps<{
  judul: string;
  profil: Array<{ label: string; nilai: string | null }>;

  /** Dokumen yang SUDAH ada, digambar sebagai tombol buka. */
  dokumen?: Array<{ label: string; url: string | null }>;

  /** Alamat cetak kartunya, bila sudah boleh dicetak. */
  urlCetak?: string | null;
  labelCetak?: string;

  /** Lencana keadaan di kepala kartu — "draft", "open", "close". */
  status?: string | null;
  warnaStatus?: string | null;
}>();
</script>

<template>
  <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
    <header class="px-5 py-3.5 border-b border-stone-100 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-2.5 min-w-0">
        <h3 class="text-[13.5px] font-bold text-cam-ink">{{ judul }}</h3>
        <span v-if="status" class="rounded-md px-2 py-0.5 text-[10px] font-bold shrink-0"
              :style="{ background: (warnaStatus ?? '#78716C') + '1F', color: warnaStatus ?? '#78716C' }">
          {{ status }}
        </span>
      </div>

      <a v-if="urlCetak" :href="urlCetak" target="_blank" rel="noopener"
         class="shrink-0 rounded-lg bg-cam-ink text-white px-3 py-1.5 text-[11px] font-bold hover:bg-stone-700 transition">
        {{ labelCetak ?? 'Cetak kartu' }}
      </a>
    </header>

    <dl class="px-5 py-4 grid gap-x-5 gap-y-2.5 sm:grid-cols-2">
      <div v-for="p in profil" :key="p.label" class="min-w-0">
        <dt class="text-[10px] uppercase tracking-wide text-stone-400">{{ p.label }}</dt>
        <dd class="text-[12.5px] font-semibold text-cam-ink truncate" :title="p.nilai ?? '—'">
          {{ p.nilai || '—' }}
        </dd>
      </div>
    </dl>

    <!-- Dokumen yang sudah ada: tombol buka, bukan nama berkas. Nama
         berkas tidak dapat diperiksa; yang memeriksanya perlu
         membukanya. -->
    <div v-if="(dokumen ?? []).length" class="px-5 pb-4 pt-1 border-t border-stone-100">
      <p class="text-[10px] uppercase tracking-wide text-stone-400 mb-2">Dokumen terkait</p>
      <div class="flex flex-wrap gap-2">
        <a v-for="d in dokumen" :key="d.label"
           :href="d.url ?? undefined"
           :target="d.url ? '_blank' : undefined" rel="noopener"
           class="rounded-lg px-2.5 py-1.5 text-[11px] font-semibold border transition"
           :class="d.url
             ? 'border-stone-200 text-cam-ink hover:bg-stone-50'
             : 'border-stone-100 text-stone-300 cursor-not-allowed'">
          {{ d.label }}<span v-if="!d.url"> · belum ada</span>
        </a>
      </div>
    </div>
  </section>
</template>
