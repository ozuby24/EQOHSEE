<script setup lang="ts">
/**
 * Ikhtisar satu audit: skor, sertifikat, sumbangan tiap bagian,
 * pengurang, dan profil perusahaan.
 *
 * Urutannya mengikuti pertanyaan yang dibawa orang ke halaman ini:
 * berapa skornya → apakah sertifikatnya sudah bisa terbit (dan bila
 * belum, apa yang kurang) → bagian mana yang menahan skornya.
 *
 * Syarat sertifikat ditampilkan sebagai daftar periksa, bukan satu
 * kalimat penolakan: perusahaan berskor 99 yang sertifikatnya tidak
 * dapat terbit perlu melihat SATU syarat yang belum terpenuhi, bukan
 * menebaknya dari angka.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { HalamanAuditLingkunganIkhtisar } from '../../types';
import Dialog from '../../Components/Dialog.vue';
import SertifikatLingkungan from '../../Components/SertifikatLingkungan.vue';
import { useDialog } from '../../dialog';
import PindahBagian from './Pindah.vue';
import KartuSkor from './Skor.vue';
import KontribusiBagian from './KontribusiBagian.vue';
import { WARNA_BAGIAN, WAJIB, angka } from './bantu';
import './akl.css';

const props = defineProps<HalamanAuditLingkunganIkhtisar>();

const { dialog, tanya, minta, batal, lanjut } = useDialog();

/* ═══════════ pengurang ═══════════ */

const kurang = ref<string[]>([...props.a.pengurang]);
const menyimpanKurang = ref(false);
const kurangBerubah = computed(() => [...kurang.value].sort().join() !== [...props.a.pengurang].sort().join());

function simpanKurang() {
  menyimpanKurang.value = true;
  router.post(props.tautan.pengurang, { pengurang: kurang.value }, {
    preserveScroll: true,
    onFinish: () => { menyimpanKurang.value = false; },
  });
}

/* ═══════════ bagian ═══════════ */

const daftarBagian = computed(() => Object.values(props.skor.bagian));

const maju = computed(() => Object.fromEntries(
  daftarBagian.value.map((b) => [b.kunci, { belum: b.belum, kriteria: b.kriteria, penuh: b.penuh }]),
));

/* ═══════════ sertifikat ═══════════ */

const sert = computed(() => props.sertifikat);

const syarat = computed(() => {
  const s = props.skor;
  const a = s.bagian.a, b = s.bagian.b;
  return [
    { ok: s.belum === 0, teks: `Seluruh ${s.kriteria} kriteria terverifikasi`, ket: `${s.kriteria - s.belum} / ${s.kriteria}` },
    { ok: !!a?.penuh, teks: 'Bagian A — Administrasi Lingkungan bernilai penuh', ket: a ? `${a.nilai} / ${a.maks}` : '—' },
    { ok: !!b?.penuh, teks: 'Bagian B — Implementasi bernilai penuh', ket: b ? `${b.nilai} / ${b.maks}` : '—' },
    { ok: s.akhir >= 70, teks: 'Skor akhir minimal 70 (PRATAMA)', ket: angka(s.akhir) },
  ];
});

/** Bagian pertama yang perlu dibuka untuk memenuhi syarat. */
const langkahBerikut = computed(() => {
  const belum = daftarBagian.value.find((b) => b.belum > 0);
  if (belum) return { kunci: belum.kunci, teks: `Lanjutkan verifikasi bagian ${belum.kunci.toUpperCase()} (${belum.belum} kriteria)` };
  const wajib = daftarBagian.value.find((b) => WAJIB.includes(b.kunci) && !b.penuh);
  if (wajib) return { kunci: wajib.kunci, teks: `Tinjau bagian ${wajib.kunci.toUpperCase()} — kurang ${wajib.maks - wajib.nilai} poin` };
  return null;
});

const terbit = useForm({
  terbit: props.sertifikat.bawaan.terbit,
  berlaku: props.sertifikat.bawaan.berlaku,
  tempat: props.sertifikat.bawaan.tempat,
  signatory_id: props.sertifikat.bawaan.signatory_id ? String(props.sertifikat.bawaan.signatory_id) : '',
});

async function terbitkan() {
  const ok = await tanya({
    judul: 'Terbitkan sertifikat?',
    pesan: `Sertifikat predikat ${props.skor.predikat.nama} diterbitkan untuk ${props.a.perusahaan ?? props.a.judul}. `
      + 'Isinya dibekukan saat ini — penyuntingan nilai sesudahnya tidak mengubah lembar yang sudah terbit. '
      + 'Audit ditandai Selesai.',
    labelAksi: 'Terbitkan',
  });
  if (!ok) return;

  terbit.transform((d) => ({ ...d, signatory_id: d.signatory_id || null }))
    .post(sert.value.urlTerbit, { preserveScroll: true });
}

async function cabut() {
  const aktif = sert.value.aktif;
  if (!aktif) return;

  const alasan = await minta({
    judul: `Cabut sertifikat ${aktif.nomor}?`,
    pesan: 'Halaman verifikasi QR-nya akan menyatakan DICABUT beserta alasan ini — dapat dibaca siapa pun yang memindainya.',
    label: 'Alasan pencabutan',
    jenis: 'panjang',
    wajib: true,
    min: 5,
    nada: 'bahaya',
    labelAksi: 'Cabut Sertifikat',
  });
  if (alasan === null) return;

  router.post(aktif.urlCabut, { alasan }, { preserveScroll: true });
}

const tersalin = ref(false);
async function salinTautan(url: string) {
  try {
    await navigator.clipboard.writeText(url);
    tersalin.value = true;
    setTimeout(() => { tersalin.value = false; }, 2200);
  } catch {
    window.open(url, '_blank', 'noopener');
  }
}

const warnaPeringkat = computed(() =>
  props.skor.peringkat.nama === 'HITAM' ? '#57534E' : props.skor.peringkat.warna);
</script>

<template>
  <Head :title="a.kode" />

  <div class="akl-akar space-y-5">
    <PindahBagian :bagian="daftarBagian.map((b) => ({
                    kunci: b.kunci, huruf: b.kunci.toUpperCase(), judul: b.judul,
                    bobot: b.bobot, kriteria: b.kriteria, wajib: WAJIB.includes(b.kunci) }))"
                  :tautan-bagian="tautan.bagian" :ikhtisar="tautan.ikhtisar"
                  :kini="null" :maju="maju" />

    <!-- ═══ identitas & skor ═══ -->
    <section class="akl-hero" :style="{ '--akl-warna': warnaPeringkat }">
      <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="akl-keping gelap num">{{ a.kode }}</span>
            <span class="akl-keping" :class="a.status === 'Selesai' ? 'ok' : 'jalan'">{{ a.status }}</span>
            <span class="akl-keping num">Periode {{ a.tahun }}</span>
            <span v-if="sertifikat.aktif" class="akl-keping sert">★ Bersertifikat</span>
          </div>
          <h2 class="akl-hero-nama mt-2">{{ a.perusahaan ?? a.judul }}</h2>
          <p class="akl-hero-sub">
            {{ a.judul }}<template v-if="a.lokasi"> · {{ a.lokasi }}</template>
            <template v-if="a.tanggal"> · {{ a.tanggal }}</template>
          </p>
        </div>

        <div class="flex flex-wrap gap-2 min-w-0 max-w-full">
          <a :href="tautan.ubah" class="eq-btn-mini">✎ Ubah Identitas</a>
          <a :href="tautan.lembar" target="_blank" rel="noopener" class="eq-btn-lain" style="flex:none">⎙ Lembar Audit</a>
          <a v-if="sertifikat.aktif" :href="sertifikat.aktif.url" class="eq-btn-utama" style="flex:none">★ Sertifikat</a>
        </div>
      </div>

      <KartuSkor :skor="skor" :peringkat="opsi.peringkat" />
    </section>

    <!-- ═══ sertifikat ═══ -->
    <section class="eq-panel" id="sertifikat">
      <div class="eq-panel-kepala">
        <h3>Sertifikat Penghargaan</h3>
        <span class="eq-panel-ket">
          <template v-if="sertifikat.aktif">Berlaku — dapat diverifikasi lewat QR oleh siapa pun</template>
          <template v-else-if="sertifikat.layak">Syarat terpenuhi — siap diterbitkan</template>
          <template v-else>Belum memenuhi syarat</template>
        </span>
      </div>

      <!-- berlaku -->
      <div v-if="sertifikat.aktif" class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] items-start">
        <a :href="sertifikat.aktif.url" class="akl-pratinjau" :title="`Buka sertifikat ${sertifikat.aktif.nomor}`">
          <SertifikatLingkungan :s="sertifikat.aktif" />
        </a>

        <div class="space-y-3">
          <p v-if="sertifikat.aktif.berubah" class="akl-kotak-info">
            <b>Nilai audit berubah sejak sertifikat terbit.</b> Lembar yang terbit tetap berbunyi skor
            {{ angka(sertifikat.aktif.skor) }} ({{ sertifikat.aktif.predikat }}, {{ sertifikat.aktif.peringkat }});
            hitungan sekarang {{ angka(skor.akhir) }}
            <template v-if="skor.predikat.nama">({{ skor.predikat.nama }}, {{ skor.peringkat.nama }})</template>.
            Bila perubahan ini disengaja, cabut lalu terbitkan ulang.
          </p>

          <dl class="akl-dl">
            <div><dt>Nomor</dt><dd class="num">{{ sertifikat.aktif.nomor }}</dd></div>
            <div><dt>Kode verifikasi</dt><dd class="num font-mono">{{ sertifikat.aktif.kode }}</dd></div>
            <div><dt>Predikat · peringkat</dt><dd>{{ sertifikat.aktif.predikat }} · {{ sertifikat.aktif.peringkat }}</dd></div>
            <div><dt>Terbit</dt><dd>{{ sertifikat.aktif.terbit }}<template v-if="sertifikat.aktif.tempat"> · {{ sertifikat.aktif.tempat }}</template></dd></div>
            <div><dt>Berlaku s.d.</dt><dd>{{ sertifikat.aktif.berlaku ?? '—' }}</dd></div>
            <div><dt>Penanda tangan</dt><dd>{{ sertifikat.aktif.ttdNama ?? '—' }}</dd></div>
            <div>
              <dt>Keadaan</dt>
              <dd>
                <span class="akl-keping" :class="sertifikat.aktif.status === 'sah' ? 'ok' : 'jalan'">
                  {{ sertifikat.aktif.status === 'sah' ? 'Sah' : 'Kedaluwarsa' }}
                </span>
              </dd>
            </div>
          </dl>

          <div class="flex flex-wrap gap-2">
            <a :href="sertifikat.aktif.url" class="eq-btn-utama" style="flex:none">⎙ Buka &amp; Cetak</a>
            <button type="button" class="eq-btn-lain" style="flex:none" @click="salinTautan(sertifikat.aktif.urlVerifikasi)">
              {{ tersalin ? '✓ Tersalin' : 'Salin tautan verifikasi' }}
            </button>
            <button v-if="sertifikat.bolehCabut" type="button" class="eq-btn-mini bahaya" @click="cabut">Cabut</button>
          </div>
        </div>
      </div>

      <!-- siap terbit -->
      <div v-else-if="sertifikat.layak && sertifikat.pratinjau"
           class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] items-start">
        <div class="akl-pratinjau">
          <SertifikatLingkungan :s="sertifikat.pratinjau" pratinjau />
        </div>

        <form class="space-y-3" @submit.prevent="terbitkan">
          <ul class="akl-syarat">
            <li v-for="(x, i) in syarat" :key="i" class="ok"><span class="tanda">✓</span>{{ x.teks }}<small>{{ x.ket }}</small></li>
          </ul>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="akl-lbl" for="s-terbit">Tanggal terbit</label>
              <input id="s-terbit" v-model="terbit.terbit" type="date" class="akl-isian">
              <p v-if="terbit.errors.terbit" class="text-[11px] text-red-600 mt-1">{{ terbit.errors.terbit }}</p>
            </div>
            <div>
              <label class="akl-lbl" for="s-berlaku">Berlaku s.d.</label>
              <input id="s-berlaku" v-model="terbit.berlaku" type="date" class="akl-isian">
              <p v-if="terbit.errors.berlaku" class="text-[11px] text-red-600 mt-1">{{ terbit.errors.berlaku }}</p>
            </div>
          </div>
          <div>
            <label class="akl-lbl" for="s-tempat">Ditetapkan di</label>
            <input id="s-tempat" v-model="terbit.tempat" class="akl-isian" placeholder="mis. Balikpapan">
          </div>
          <div>
            <label class="akl-lbl" for="s-ttd">Penanda tangan</label>
            <select id="s-ttd" v-model="terbit.signatory_id" class="akl-isian">
              <option value="">— tanpa penanda tangan —</option>
              <option v-for="t in sertifikat.penandatangan" :key="t.id" :value="String(t.id)">
                {{ t.nama }}<template v-if="t.jabatan"> — {{ t.jabatan }}</template>
              </option>
            </select>
            <p v-if="terbit.errors.signatory_id" class="text-[11px] text-red-600 mt-1">{{ terbit.errors.signatory_id }}</p>
            <p v-if="!sertifikat.penandatangan.length" class="text-[11px] mt-1" style="color: var(--akl-tinta-3)">
              Belum ada penanda tangan aktif untuk perusahaan ini — atur di menu Penanda Tangan.
            </p>
          </div>

          <p v-if="!sertifikat.pratinjau?.logoPenerbit && !sertifikat.pratinjau?.logoPenerima" class="akl-kotak-info">
            <b>Logo perusahaan belum diunggah.</b> Kop sertifikat sementara memakai nama perusahaan.
            <a :href="sertifikat.urlLogo" class="underline font-semibold">Unggah logo di Profil Perusahaan</a>
            — logo yang diunggah kemudian ikut tampil pada sertifikat yang sudah terbit.
          </p>

          <button type="submit" class="eq-btn-utama w-full" :disabled="terbit.processing">
            {{ terbit.processing ? 'Menerbitkan…' : '★ Terbitkan Sertifikat' }}
          </button>
          <p class="text-[11px] leading-relaxed" style="color: var(--akl-tinta-3)">
            Nomor dan kode QR dibuat saat terbit. Isi sertifikat dibekukan: nilai yang disunting sesudahnya
            tidak mengubah lembar yang sudah terbit.
          </p>
        </form>
      </div>

      <!-- belum memenuhi syarat -->
      <div v-else class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-start">
        <div>
          <ul class="akl-syarat">
            <li v-for="(x, i) in syarat" :key="i" :class="x.ok ? 'ok' : 'belum'">
              <span class="tanda">{{ x.ok ? '✓' : '!' }}</span>{{ x.teks }}<small>{{ x.ket }}</small>
            </li>
          </ul>
          <a v-if="langkahBerikut" :href="tautan.bagian[langkahBerikut.kunci]" class="eq-btn-utama mt-3" style="flex:none">
            {{ langkahBerikut.teks }} →
          </a>
        </div>
        <div class="space-y-2">
          <!-- Alasan predikat sudah tercetak di kartu skor; tidak diulang. -->
          <p v-if="sertifikat.alasan && sertifikat.alasan !== skor.predikat.alasan" class="akl-kotak-info">{{ sertifikat.alasan }}</p>
          <p class="text-[11.5px] leading-relaxed" style="color: var(--akl-tinta-2)">
            Sertifikat Penghargaan terbit untuk predikat <b>PRATAMA</b> (skor 70–79), <b>UTAMA</b> (80–89),
            atau <b>ADITAMA</b> (90–100), dari audit yang seluruh kriterianya sudah diverifikasi dan bagian A
            serta B bernilai penuh. Warnanya mengikuti peringkat: BIRU, HIJAU, atau EMAS.
          </p>
        </div>
      </div>

      <details v-if="sertifikat.riwayat.length" class="mt-4">
        <summary class="text-[12px] font-bold cursor-pointer" style="color: var(--akl-tinta-2)">
          Riwayat sertifikat dicabut ({{ sertifikat.riwayat.length }})
        </summary>
        <ul class="mt-2 space-y-1.5">
          <li v-for="r in sertifikat.riwayat" :key="r.id" class="text-[11.5px]" style="color: var(--akl-tinta-2)">
            <a :href="r.url" class="font-bold num hover:underline" style="color: var(--akl-tinta)">{{ r.nomor }}</a>
            · {{ r.predikat }} · terbit {{ r.terbit }} · dicabut {{ r.dicabut }}<template v-if="r.alasan"> — {{ r.alasan }}</template>
          </li>
        </ul>
      </details>
    </section>

    <!-- ═══ bagian ═══ -->
    <section class="eq-panel">
      <div class="eq-panel-kepala">
        <h3>Sumbangan per Bagian</h3>
        <span class="eq-panel-ket">Tiap bagian menyumbang sesuai bobotnya; jumlahnya adalah persentase pemenuhan</span>
      </div>

      <KontribusiBagian :bagian="skor.bagian" :pemenuhan="skor.pemenuhan" />

      <div class="akl-bagian-kisi mt-5">
        <a v-for="b in daftarBagian" :key="b.kunci" :href="tautan.bagian[b.kunci]" class="akl-kb">
          <div class="akl-kb-kepala">
            <span class="akl-kb-huruf" :style="{ background: WARNA_BAGIAN[b.kunci] }">{{ b.kunci.toUpperCase() }}</span>
            <div class="min-w-0">
              <p class="akl-kb-judul">{{ b.judul }}</p>
              <p class="akl-kb-ket num">bobot {{ angka(b.bobot) }} · {{ b.kriteria }} kriteria</p>
            </div>
          </div>
          <div class="akl-kb-pita"><span :style="{ width: `${b.persen}%`, background: WARNA_BAGIAN[b.kunci] }"></span></div>
          <div class="akl-kb-angka">
            <span><b class="num">{{ angka(b.persen, 1) }}%</b> capaian</span>
            <span class="num">{{ b.nilai }} / {{ b.maks }} poin</span>
          </div>
          <div class="flex flex-wrap items-center justify-between gap-1">
            <span v-if="WAJIB.includes(b.kunci)" class="akl-kb-status" :class="b.penuh ? 'ok' : 'kurang'">
              {{ b.penuh ? '✓ wajib penuh — terpenuhi' : `wajib penuh — kurang ${b.maks - b.nilai} poin` }}
            </span>
            <span v-else class="akl-kb-status num" style="color: var(--akl-tinta-3)">sumbangan {{ angka(b.hasil) }}</span>
            <span v-if="b.belum" class="akl-kb-status kurang">{{ b.belum }} belum diverifikasi</span>
            <span v-else class="akl-kb-status ok">lengkap</span>
          </div>
        </a>
      </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-2 items-start">
      <!-- ═══ pengurang ═══ -->
      <section class="eq-panel">
        <div class="eq-panel-kepala">
          <h3>Nilai Pengurang</h3>
          <span class="eq-panel-ket">Dipotong dari skor akhir, bukan dari bagiannya</span>
        </div>

        <div class="grid gap-2">
          <label v-for="p in opsi.pengurang" :key="p.kunci" class="akl-kurang-baris">
            <input v-model="kurang" type="checkbox" :value="p.kunci" class="ring-focus">
            <span class="akl-kurang-label">{{ p.label }}</span>
            <span class="akl-kurang-poin num">−{{ p.poin }}</span>
          </label>
        </div>

        <button type="button" class="eq-btn-lain mt-3" style="flex:none"
                :disabled="menyimpanKurang || !kurangBerubah" @click="simpanKurang">
          {{ menyimpanKurang ? 'Menyimpan…' : kurangBerubah ? 'Simpan Nilai Pengurang' : 'Tersimpan' }}
        </button>
      </section>

      <!-- ═══ profil & catatan ═══ -->
      <section class="eq-panel">
        <div class="eq-panel-kepala"><h3>Profil Perusahaan</h3></div>

        <dl v-if="a.profil.length" class="akl-profil">
          <template v-for="p in a.profil" :key="p.kunci">
            <dt>{{ p.label }}</dt><dd>{{ p.nilai }}</dd>
          </template>
        </dl>
        <p v-else class="text-[12px]" style="color: var(--akl-tinta-3)">
          Profil belum diisi. <a :href="tautan.ubah" class="font-bold hover:underline" style="color: #DC6E00">Lengkapi →</a>
        </p>

        <template v-if="a.catatan">
          <h4 class="akl-lbl mt-5">Catatan auditor</h4>
          <p class="text-[12.5px] leading-relaxed whitespace-pre-line" style="color: var(--akl-tinta-2)">{{ a.catatan }}</p>
        </template>
      </section>
    </div>
  </div>

  <Dialog v-bind="dialog" @batal="batal" @lanjut="lanjut" />
</template>

<style scoped>
.akl-kurang-baris {
  display: flex; align-items: center; gap: .6rem;
  border: 1px solid var(--akl-garis); border-radius: .7rem; padding: .5rem .7rem;
  cursor: pointer; transition: border-color .16s, background-color .16s;
}
.akl-kurang-baris:hover { border-color: #FCA5A5; }
.akl-kurang-baris:has(input:checked) { border-color: #DC2626; background: #FFFAFA; }
.akl-kurang-label { flex: 1; font-size: 11.5px; color: var(--akl-tinta-2); }
.akl-kurang-poin  { font-size: 13px; font-weight: 800; color: #DC2626; }

.akl-profil { display: grid; grid-template-columns: auto 1fr; gap: .3rem .9rem; font-size: 12px; }
.akl-profil dt { font-weight: 700; color: var(--akl-tinta-3); }
.akl-profil dd { color: var(--akl-tinta-2); margin: 0; }

:global(:root[data-tema="gelap"] .akl-kurang-baris:has(input:checked)) { background: rgba(239, 68, 68, .1); }
</style>
