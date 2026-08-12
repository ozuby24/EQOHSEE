<script setup lang="ts">
/**
 * Pesan — chat langsung dan grup perusahaan.
 *
 * Bentuknya sama dengan kotak masuk Bantuan (daftar utas kiri, percakapan
 * kanan), tapi sisi gelembung di sini ditentukan oleh milikSaya per pesan,
 * bukan oleh kolom peran — setiap pengirim adalah sesama pengguna.
 */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { HalamanPesan } from '../../types';

const props = defineProps<HalamanPesan>();

const form = useForm({ isi: '' });
const gulung = ref<HTMLElement | null>(null);

async function keBawah() {
  await nextTick();
  if (gulung.value) gulung.value.scrollTop = gulung.value.scrollHeight;
}

/** Sudah menempel di bawah — dibaca sebelum DOM diperbarui. */
function diBawah(): boolean {
  const el = gulung.value;

  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80;
}

keBawah();

// Pesan yang datang lewat penyegaran tidak menyeret orang yang sedang
// membaca ke atas kembali ke bawah. Sebelum ada penyegaran berkala ini
// tidak pernah jadi soal: panjang daftar hanya berubah oleh kiriman
// sendiri, dan pengirimnya memang selalu berada di bawah.
watch(() => props.pesan.length, (baru, lama) => {
  if (baru > lama && !diBawah()) return;

  keBawah();
});

function buka(id: number) {
  router.get('/pesan', { percakapan: id }, { preserveScroll: true, preserveState: true });
}

function kirim() {
  if (!form.isi.trim() || !props.terpilih) return;

  form.post(`/pesan/${props.terpilih}`, {
    preserveScroll: true,
    only: ['pesan', 'percakapan', 'terpilih', 'errors'],
    onSuccess: () => { form.reset('isi'); },
  });
}

/**
 * Menyegarkan sendiri selama halaman dilihat.
 *
 * Percakapan yang hanya berubah setelah orang menekan muat ulang bukan
 * percakapan — balasan yang datang saat lawan bicara masih menatap
 * layar tidak akan pernah muncul.
 *
 * Berhenti saat tab tidak terlihat: tab yang tertinggal terbuka semalaman
 * akan memanggil server ribuan kali tanpa seorang pun membacanya.
 *
 * Tidak menyegarkan selagi pesan sedang dikirim. Inertia membatalkan
 * kunjungan yang lebih lama ketika ada yang baru, jadi penyegaran yang
 * kebetulan menyusul tepat setelah tombol Kirim ditekan akan membatalkan
 * pengiriman itu sendiri — pesannya hilang tanpa satu pun galat tampak.
 */
const JEDA = 10000;

let jam: ReturnType<typeof setInterval> | null = null;

function segarkan() {
  if (document.visibilityState !== 'visible' || form.processing) return;

  /* Percakapan yang sedang dibuka ikut disebut, dan terpilih ikut dimuat.
     Tanpa keduanya, pesan masuk yang menaikkan utas lain ke urutan teratas
     membuat server memilihkan utas itu — isi berganti di bawah judul yang
     tidak berubah, dan orang membaca percakapan yang bukan dibukanya. */
  router.reload({
    only: ['pesan', 'percakapan', 'terpilih'],
    ...(props.terpilih ? { data: { percakapan: props.terpilih } } : {}),
  });
}

onMounted(() => { jam = setInterval(segarkan, JEDA); });
onBeforeUnmount(() => { if (jam) clearInterval(jam); });
</script>

<template>
  <Head title="Pesan" />

  <div class="max-w-6xl mx-auto grid gap-4 lg:grid-cols-[300px_1fr]"
       style="height:calc(100vh - 190px); min-height:460px">

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden flex flex-col">
      <div class="px-4 py-3 border-b border-stone-100">
        <h3 class="text-[13px] font-bold text-cam-ink">Percakapan ({{ percakapan.length }})</h3>
      </div>

      <div class="flex-1 overflow-y-auto divide-y divide-stone-100">
        <p v-if="!percakapan.length" class="px-4 py-10 text-[12.5px] text-stone-400 text-center">
          Belum ada percakapan. Mulai dari Direktori.
        </p>

        <button v-for="p in percakapan" :key="p.id" type="button" @click="buka(p.id)"
                class="w-full text-left px-4 py-3 hover:bg-stone-50 transition flex items-center gap-3"
                :class="p.aktif ? 'bg-stone-50' : ''">
          <img v-if="p.avatar" :src="p.avatar" alt=""
               class="w-9 h-9 rounded-full object-cover border border-stone-200 shrink-0">
          <span v-else class="w-9 h-9 rounded-full grid place-items-center text-white text-[12px] font-black shrink-0"
                style="background:linear-gradient(135deg,var(--eq-aksen,#0E747E),#2CB0BC)">
            {{ p.jenis === 'grup' ? 'G' : p.nama.charAt(0).toUpperCase() }}
          </span>

          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <span class="text-[12.5px] font-bold text-cam-ink truncate">{{ p.nama }}</span>
              <span v-if="p.belumDibaca" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full shrink-0
                           bg-[color:var(--eq-aksen,#0E747E)] text-white">
                {{ p.belumDibaca }}
              </span>
            </div>
            <div class="text-[10.5px] text-stone-400 mt-0.5">{{ p.terakhir ?? 'belum ada pesan' }}</div>
          </div>
        </button>
      </div>
    </div>

    <div class="bg-white rounded-2xl shadow-card border border-stone-100 overflow-hidden flex flex-col">
      <template v-if="terpilih">
        <div class="px-5 py-3 border-b border-stone-100">
          <h3 class="text-[13px] font-bold text-cam-ink">
            {{ percakapan.find(p => p.id === terpilih)?.nama }}
          </h3>
        </div>

        <div ref="gulung" class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-stone-50/60">
          <div v-for="m in pesan" :key="m.id" class="flex" :class="m.milikSaya ? 'justify-end' : ''">
            <div class="rounded-2xl px-4 py-2.5 max-w-[80%] whitespace-pre-wrap break-words text-[12.5px] leading-relaxed"
                 :class="m.milikSaya
                   ? 'bg-[color:var(--eq-aksen,#0E747E)] text-white'
                   : 'bg-white border border-stone-200 text-cam-ink'">
              <div v-if="!m.milikSaya" class="text-[10px] font-bold uppercase tracking-wider opacity-60 mb-1">
                {{ m.nama }}
              </div>
              {{ m.isi }}
              <div class="text-[10px] opacity-50 mt-1">{{ m.waktu }}</div>
            </div>
          </div>
        </div>

        <div class="border-t border-stone-100 p-3.5">
          <textarea v-model="form.isi" rows="2" placeholder="Tulis pesan…"
                    class="w-full rounded-xl border border-stone-200 px-3.5 py-2.5 text-[13px] resize-none
                           focus:border-[color:var(--eq-aksen,#0E747E)] focus:ring-0"></textarea>
          <p v-if="form.errors.isi" class="text-[11.5px] text-red-600 mt-1">{{ form.errors.isi }}</p>

          <button type="button" :disabled="form.processing || !form.isi.trim()" @click="kirim"
                  class="eq-btn-utama mt-2.5 disabled:opacity-40 disabled:cursor-not-allowed"
                  style="flex:none;padding:9px 18px">
            {{ form.processing ? 'Mengirim…' : 'Kirim' }}
          </button>
        </div>
      </template>

      <p v-else class="m-auto text-[12.5px] text-stone-400 text-center px-6">
        Pilih percakapan di sebelah kiri, atau mulai percakapan baru dari Direktori.
      </p>
    </div>
  </div>
</template>
