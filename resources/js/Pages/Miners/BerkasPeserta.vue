<script setup lang="ts">
/**
 * Berkas satu peserta — susunan D'Best: "Detail" lalu "Attachment".
 *
 * DUA KARTU, DAN PEMBAGIANNYA BUKAN SOAL TATA LETAK.
 *
 *   Kartu DETAIL berisi apa yang SUDAH diketahui sistem: identitas
 *   orangnya, nomor registernya, dan dokumen yang sudah ada. Seluruhnya
 *   hanya dibaca — diketik ulang di sini, ia akan berselisih dengan
 *   sumbernya tanpa ada yang tahu mana yang benar.
 *
 *   Kartu ATTACHMENT berisi apa yang HARUS DIKERJAKAN peserta:
 *   mengunggah berkas syaratnya. Hanya di sinilah ada isian.
 *
 * Pembagian itu menjawab pertanyaan yang paling sering diajukan orang
 * yang membuka halaman ini — "apa lagi yang kurang dari saya" — dalam
 * sekali lihat, tanpa membaca formulir bermedan dua puluh.
 *
 * ── UNGGAH, BUKAN KETIK ──
 *
 * Seluruh lampiran ini dahulu berupa kolom teks berisi "jalur berkas".
 * Yang tersimpan bukan bukti melainkan pernyataan bahwa buktinya ada di
 * suatu tempat, dan pernyataan itu tidak dapat dibuka maupun diperiksa.
 * Di sini tiap lampiran adalah unggahan sungguhan dengan tombol
 * membukanya begitu tersimpan.
 */
import { computed, ref } from 'vue';
import { KEADAAN } from '../../Grafik/warna';

const props = defineProps<{
  /** Identitas orangnya — seluruhnya hanya dibaca. */
  profil: Array<{ label: string; nilai: string | null }>;
  judulDetail: string;
  judulLampiran: string;

  /** Dokumen yang SUDAH ada, digambar sebagai tombol buka. */
  dokumen?: Array<{ label: string; url: string | null }>;

  lampiran: Array<{
    kolom: string; label: string; wajib: boolean;
    ada: boolean; url: string | null;
  }>;

  /** Wajib yang belum ada — disebut di muka, bukan setelah ditolak. */
  kurang?: string[];

  bisaUnggah?: boolean;
  /** Alamat cetak kartunya, bila sudah boleh dicetak. */
  urlCetak?: string | null;
  labelCetak?: string;
}>();

const emit = defineEmits<{
  (e: 'unggah', kolom: string, jalur: string, nama: string): void;
}>();

/** Berkas yang sedang naik, per kolom — supaya tombolnya mati sendiri. */
const naik = ref<Record<string, boolean>>({});
const galat = ref<Record<string, string>>({});
const baru  = ref<Record<string, string>>({});

async function pilih(kolom: string, ev: Event) {
  const f = (ev.target as HTMLInputElement).files?.[0];
  if (!f) return;

  naik.value[kolom] = true;
  delete galat.value[kolom];

  const data = new FormData();
  data.append('kolom', kolom);
  data.append('berkas', f);

  try {
    const r = await fetch('/miners/lampiran', {
      method: 'POST',
      body: data,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
      },
    });

    const j = await r.json();

    if (!r.ok) throw new Error(j?.pesan ?? `Gagal mengunggah (HTTP ${r.status}).`);

    baru.value[kolom] = j.nama;
    emit('unggah', kolom, j.jalur, j.nama);
  } catch (e: any) {
    /* Sebabnya disebut, bukan dibiarkan diam. Unggahan yang gagal
       tanpa keterangan terbaca sebagai unggahan yang berhasil — dan
       yang mengiranya berhasil tidak akan mencoba lagi. */
    galat.value[kolom] = e?.message ?? 'Gagal mengunggah berkas.';
  } finally {
    naik.value[kolom] = false;
  }
}

const adaKurang = computed(() => (props.kurang ?? []).length > 0);
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-[1.05fr_.95fr] items-start">

    <!-- ══════════ DETAIL — hanya dibaca ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100 flex items-center justify-between gap-3">
        <h3 class="text-[13.5px] font-bold text-cam-ink">{{ judulDetail }}</h3>
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

      <!-- Dokumen yang sudah ada: tombol buka, bukan nama berkas.
           Nama berkas tidak dapat diperiksa; yang memeriksanya perlu
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

    <!-- ══════════ ATTACHMENT — satu-satunya yang diisi ══════════ -->
    <section class="rounded-2xl bg-white border border-stone-100 shadow-card overflow-hidden">
      <header class="px-5 py-3.5 border-b border-stone-100">
        <h3 class="text-[13.5px] font-bold text-cam-ink">{{ judulLampiran }}</h3>
        <p class="text-[11px] text-stone-500 mt-0.5">
          Bertanda <span class="text-red-600 font-bold">*</span> wajib ada sebelum kartunya dapat terbit.
        </p>
      </header>

      <!-- Yang kurang disebut DI MUKA, bukan setelah pengajuan ditolak. -->
      <p v-if="adaKurang" class="mx-5 mt-4 rounded-xl px-3.5 py-2.5 text-[11.5px]"
         style="background:#FEF3C7;color:#92400E">
        Belum lengkap: <b>{{ (kurang ?? []).join(', ') }}</b>.
      </p>

      <ul class="px-5 py-4 grid gap-2.5">
        <li v-for="l in lampiran" :key="l.kolom"
            class="rounded-xl border px-3.5 py-3"
            :class="l.ada ? 'border-stone-100 bg-stone-50/60' : (l.wajib ? 'border-amber-200' : 'border-stone-100')">

          <div class="flex items-center gap-3">
            <span class="w-1.5 h-1.5 rounded-full shrink-0"
                  :style="{ background: l.ada ? KEADAAN.baik : (l.wajib ? KEADAAN.ingat : '#D6D3D1') }"></span>

            <span class="text-[12px] font-semibold text-cam-ink flex-1 min-w-0">
              {{ l.label }}<span v-if="l.wajib" class="text-red-600"> *</span>
            </span>

            <a v-if="l.url" :href="l.url" target="_blank" rel="noopener"
               class="shrink-0 text-[11px] font-bold text-cam-lime-deep hover:underline">Lihat</a>
          </div>

          <div v-if="bisaUnggah" class="mt-2">
            <input type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                   :disabled="naik[l.kolom]"
                   class="w-full rounded-lg border-stone-200 text-[11.5px]"
                   :aria-label="l.label"
                   @change="pilih(l.kolom, $event)">

            <p v-if="naik[l.kolom]" class="text-[10.5px] text-stone-400 mt-1">Mengunggah…</p>
            <p v-else-if="galat[l.kolom]" class="text-[10.5px] text-red-600 mt-1">{{ galat[l.kolom] }}</p>
            <p v-else-if="baru[l.kolom]" class="text-[10.5px] mt-1" :style="{ color: KEADAAN.baik }">
              Terunggah: {{ baru[l.kolom] }} — tekan Simpan agar melekat pada kartunya.
            </p>
          </div>

          <p v-else-if="!l.ada" class="text-[10.5px] text-stone-400 mt-1">
            Belum diunggah.
          </p>
        </li>
      </ul>
    </section>
  </div>
</template>
