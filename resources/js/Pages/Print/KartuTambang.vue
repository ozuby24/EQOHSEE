<script setup lang="ts">
/**
 * Kartu tambang siap cetak — dua sisi, ukuran kartu identitas.
 *
 * BERBENTUK KARTU, bukan lembar A4. Yang dibawa orangnya ke gerbang
 * adalah benda yang muat di saku dan dapat ditunjukkan sambil berjalan;
 * lembar A4 dilipat empat, basah, lalu berhenti dibawa — dan yang tidak
 * dibawa tidak dapat diperiksa.
 *
 * DUA SISI, DAN KEDUANYA PERLU. Muka menjawab "siapa ini dan sampai
 * kapan"; belakang menjawab "boleh apa" — daftar unit beserta kelas
 * kewenangannya bagi SIMPER, syarat dan kontak darurat bagi permit.
 * Menjejalkan keduanya ke satu sisi menghasilkan huruf sekecil enam
 * poin, yang tidak terbaca di gerbang pada tengah hari.
 *
 * UKURANNYA ISO/IEC 7810 ID-1 — 85,6 × 54 mm, ukuran KTP dan kartu
 * bank. Bukan angka yang dikarang: kartu seukuran itu muat di setiap
 * dompet, holder, dan mesin laminasi yang sudah ada di site.
 */
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import BlankLayout from '../../Layouts/BlankLayout.vue';
import PrintShell from '../../Components/PrintShell.vue';

defineOptions({ layout: BlankLayout });

const props = defineProps<{
  dok: any;
  orang: Record<string, any>;
  kartu: Record<string, any>;
  kartuCetak: Record<string, any>;
  dasar: { mcu: Record<string, any> | null; induksi: Record<string, any> | null };
  kembali?: string;
}>();

const simper = computed(() => props.kartuCetak?.jenis === 'SIMPER');

const judul = computed(() => simper.value ? 'MINE LICENSE' : 'MINE PERMIT');

const tanggal = (v: unknown) => v
  ? new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
      .format(new Date(String(v)))
  : '—';

/**
 * Tanggal yang sesungguhnya membatasi kartu ini.
 *
 * Yang tercetak pada kartunya bukan jawabannya: permit gugur bersama
 * MCU-nya, SIMPER gugur bersama SIM kepolisiannya. Mencetak tanggal
 * kartunya saja membuat orang mengira izinnya masih lama padahal
 * dasarnya habis pekan depan — dan petugas gerbang tidak punya cara
 * mengetahuinya dari kartu itu.
 */
const berlakuSampai = computed(() => {
  const tgl = [
    props.kartu?.tglExpired,
    props.dasar?.mcu?.tglExpired,
    props.dasar?.induksi?.tglExpired,
    simper.value ? props.kartuCetak?.simExpired : null,
  ].filter(Boolean).map((t) => String(t)).sort();

  return tgl[0] ?? null;
});

const dibatasiOleh = computed(() => {
  const s = berlakuSampai.value;
  if (!s || s === props.kartu?.tglExpired) return null;

  if (s === props.dasar?.mcu?.tglExpired)     return 'MCU';
  if (s === props.dasar?.induksi?.tglExpired) return 'induksi';
  if (s === props.kartuCetak?.simExpired)     return 'SIM kepolisian';

  return null;
});

/**
 * Kelas kewenangan yang dimiliki, sebagai deret huruf.
 *
 * D'Best mencetaknya sebagai baris huruf tunggal pada muka kartu —
 * petugas gerbang membacanya sekali pandang tanpa membalik kartunya.
 * Yang BELUM lulus tidak ikut: kartu menyatakan kewenangan, bukan
 * riwayat ujian.
 */
const authority = computed<string[]>(() => {
  const a = (props.kartuCetak?.unit ?? [])
    .filter((u: any) => u.lulus && u.authority)
    .map((u: any) => String(u.authority).toUpperCase());

  return [...new Set(a)].sort() as string[];
});

const unitLulus = computed<any[]>(() =>
  (props.kartuCetak?.unit ?? []).filter((u: any) => u.lulus));

/** Warna pita kartu — permit hijau tambang, SIMPER jingga kendaraan. */
const nada = computed(() => simper.value
  ? { tua: '#7C2D12', muda: '#EA580C', latar: '#FFF7ED' }
  : { tua: '#14532D', muda: '#15803D', latar: '#F0FDF4' });
</script>

<template>
  <Head :title="judul" />

  <PrintShell :title="judul" :kembali="props.kembali">
    <div class="flex flex-wrap gap-6 justify-center print:gap-3">

      <!-- ═══════════ MUKA ═══════════ -->
      <article class="kartu" :style="{ background: nada.latar }">
        <header class="kartu-kepala" :style="{ background: nada.tua }">
          <div class="min-w-0">
            <p class="perusahaan">{{ props.dok?.perusahaan ?? props.orang?.perusahaan }}</p>
            <p class="jenis">{{ judul }}</p>
          </div>
          <span class="lencana" :style="{ background: nada.muda }">
            {{ props.kartuCetak?.golongan || (simper ? 'SIMPER' : 'AREA') }}
          </span>
        </header>

        <div class="kartu-isi">
          <!--
            Pas foto dicetak, bukan hanya nama. Kartu tanpa foto hanya
            membuktikan bahwa KARTUNYA sah, bukan bahwa yang membawanya
            orang yang sama — dan itulah satu-satunya hal yang diperiksa
            di gerbang.
          -->
          <div class="foto">
            <img v-if="props.kartuCetak?.foto" :src="props.kartuCetak.foto" alt="">
            <span v-else>FOTO</span>
          </div>

          <div class="min-w-0 flex-1">
            <p class="nama">{{ props.orang?.nama }}</p>
            <p class="jabatan">{{ props.orang?.jabatan || '—' }}</p>

            <dl class="rinci">
              <div><dt>NIK</dt><dd class="num">{{ props.orang?.nik || '—' }}</dd></div>
              <div><dt>No.</dt><dd class="num">{{ props.kartuCetak?.nomor || '—' }}</dd></div>
              <div><dt>Gol. darah</dt><dd>{{ props.kartuCetak?.golonganDarah || '—' }}</dd></div>
              <div v-if="props.kartuCetak?.tglLahir">
                <dt>Lahir</dt><dd class="num">{{ tanggal(props.kartuCetak.tglLahir) }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Deret huruf kewenangan: dibaca sekali pandang, tanpa membalik kartu. -->
        <div v-if="simper && authority.length" class="authority">
          <span class="label">AUTHORITY</span>
          <span v-for="a in authority" :key="a" class="huruf" :style="{ background: nada.muda }">{{ a }}</span>
        </div>

        <footer class="kartu-kaki">
          <div>
            <span class="ket">Berlaku sampai</span>
            <b class="num tanggal">{{ tanggal(berlakuSampai) }}</b>
          </div>
          <div class="text-right">
            <span class="ket">{{ props.kartuCetak?.subkontraktor ? 'Subkontraktor' : 'Perusahaan' }}</span>
            <b class="perus">{{ props.kartuCetak?.subkontraktor || props.orang?.perusahaan || '—' }}</b>
          </div>
        </footer>
      </article>

      <!-- ═══════════ BELAKANG ═══════════ -->
      <article class="kartu kartu-balik">
        <header class="kartu-kepala tipis" :style="{ background: nada.tua }">
          <p class="jenis">{{ simper ? 'UNIT YANG BOLEH DIKEMUDIKAN' : 'DASAR PENERBITAN' }}</p>
        </header>

        <!-- SIMPER: unit beserta kewenangannya -->
        <div v-if="simper" class="balik-isi">
          <table v-if="unitLulus.length" class="unit">
            <thead>
              <tr><th>Auth.</th><th>Unit</th><th>Type / merk</th></tr>
            </thead>
            <tbody>
              <tr v-for="(u, i) in unitLulus" :key="i">
                <td class="num auth">{{ u.authority || '—' }}</td>
                <td>{{ u.unit }}</td>
                <td class="tipis-teks">{{ u.typeMerk || '—' }}</td>
              </tr>
            </tbody>
          </table>

          <!--
            Kartu SIMPER tanpa satu pun unit lulus bukan kartu kosong
            melainkan kartu yang tidak memberi kewenangan apa pun —
            dan itu harus tertulis, bukan disimpulkan dari tabel kosong.
          -->
          <p v-else class="kosong">
            Belum ada unit yang dinyatakan lulus. Kartu ini belum memberi
            kewenangan mengemudikan unit mana pun.
          </p>

          <dl class="sim">
            <div><dt>Kelas SIM</dt><dd>{{ props.kartuCetak?.jenisSim || '—' }}</dd></div>
            <div><dt>No. SIM</dt><dd class="num">{{ props.kartuCetak?.simPolisi || '—' }}</dd></div>
            <div><dt>SIM berlaku</dt><dd class="num">{{ tanggal(props.kartuCetak?.simExpired) }}</dd></div>
          </dl>
        </div>

        <!-- Permit: dasar penerbitan dan kontak darurat -->
        <div v-else class="balik-isi">
          <dl class="sim">
            <div v-if="props.dasar?.mcu">
              <dt>MCU</dt>
              <dd class="num">{{ tanggal(props.dasar.mcu.tanggal) }} · {{ props.dasar.mcu.hasil }}</dd>
            </div>
            <div v-if="props.dasar?.mcu">
              <dt>MCU berlaku</dt><dd class="num">{{ tanggal(props.dasar.mcu.tglExpired) }}</dd>
            </div>
            <div v-if="props.dasar?.induksi">
              <dt>Induksi</dt>
              <dd class="num">{{ tanggal(props.dasar.induksi.tanggal) }} · {{ props.dasar.induksi.jenis }}</dd>
            </div>
            <div v-if="props.kartuCetak?.area">
              <dt>Area berlaku</dt><dd>{{ props.kartuCetak.area }}</dd>
            </div>
          </dl>

          <dl class="sim">
            <div v-if="props.kartuCetak?.telepon">
              <dt>Telepon</dt><dd class="num">{{ props.kartuCetak.telepon }}</dd>
            </div>
            <div v-if="props.kartuCetak?.kontakDarurat">
              <dt>Darurat</dt><dd>{{ props.kartuCetak.kontakDarurat }}</dd>
            </div>
          </dl>
        </div>

        <footer class="kartu-kaki kecil">
          <p>
            Kartu ini gugur bersama berkas dasarnya yang habis lebih dulu<span v-if="dibatasiOleh">
            — saat ini dibatasi <b>{{ dibatasiOleh }}</b></span>. Wajib dikembalikan saat
            hubungan kerja berakhir.
          </p>
        </footer>
      </article>
    </div>
  </PrintShell>
</template>

<style scoped>
/* ISO/IEC 7810 ID-1 — ukuran KTP. Digambar 2× supaya terbaca di layar,
   lalu dicetak pada ukuran sebenarnya. */
.kartu {
  width: 171.2mm;
  min-height: 108mm;
  border-radius: 10px;
  border: 1px solid rgba(27, 32, 36, .12);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
}

.kartu-balik { background: #FAFAF9 }

.kartu-kepala {
  display: flex; align-items: center; justify-content: space-between; gap: 10px;
  padding: 10px 14px; color: #fff;
}
.kartu-kepala.tipis { padding: 7px 14px }

.perusahaan { font-size: 11.5px; font-weight: 700; letter-spacing: .01em; opacity: .92 }
.jenis      { font-size: 17px; font-weight: 800; letter-spacing: .06em }

.lencana {
  font-size: 10.5px; font-weight: 800; letter-spacing: .05em;
  padding: 3px 9px; border-radius: 999px; white-space: nowrap;
}

.kartu-isi { display: flex; gap: 14px; padding: 14px; flex: 1 }

.foto {
  width: 30mm; height: 40mm; flex: none;
  border-radius: 6px; overflow: hidden;
  background: #E7E5E4; border: 1px solid rgba(27, 32, 36, .12);
  display: grid; place-items: center;
  font-size: 10px; color: #A8A29E; letter-spacing: .1em;
}
.foto img { width: 100%; height: 100%; object-fit: cover }

.nama    { font-size: 19px; font-weight: 800; line-height: 1.15; color: #0F1720 }
.jabatan { font-size: 12px; color: #57534E; margin-top: 2px }

.rinci { margin-top: 10px; display: grid; gap: 4px }
.rinci div { display: flex; gap: 8px; font-size: 11.5px }
.rinci dt { width: 68px; flex: none; color: #78716C }
.rinci dd { font-weight: 700; color: #0F1720 }

.authority { display: flex; align-items: center; gap: 6px; padding: 0 14px 10px }
.authority .label { font-size: 9.5px; font-weight: 800; letter-spacing: .12em; color: #78716C }
.authority .huruf {
  min-width: 20px; text-align: center; color: #fff;
  font-size: 11.5px; font-weight: 800; border-radius: 4px; padding: 2px 5px;
}

.kartu-kaki {
  display: flex; justify-content: space-between; gap: 10px;
  padding: 9px 14px; border-top: 1px solid rgba(27, 32, 36, .1);
  background: rgba(255, 255, 255, .55);
}
.kartu-kaki .ket   { display: block; font-size: 9.5px; color: #78716C; letter-spacing: .04em }
.kartu-kaki .tanggal { font-size: 14px; font-weight: 800; color: #0F1720 }
.kartu-kaki .perus { font-size: 11.5px; font-weight: 700; color: #0F1720 }
.kartu-kaki.kecil  { display: block; font-size: 9.5px; color: #57534E; line-height: 1.45 }

.balik-isi { padding: 12px 14px; flex: 1; display: grid; gap: 10px; align-content: start }

.unit { width: 100%; border-collapse: collapse; font-size: 11px }
.unit th {
  text-align: left; font-size: 9.5px; letter-spacing: .06em; color: #78716C;
  border-bottom: 1px solid rgba(27, 32, 36, .12); padding-bottom: 3px;
}
.unit td { padding: 4px 6px 4px 0; border-bottom: 1px solid rgba(27, 32, 36, .06) }
.unit .auth { font-weight: 800; width: 42px }
.tipis-teks { color: #57534E }

.kosong { font-size: 11px; color: #B91C1C; line-height: 1.45 }

.sim { display: grid; gap: 3px }
.sim div { display: flex; gap: 8px; font-size: 11px }
.sim dt { width: 76px; flex: none; color: #78716C }
.sim dd { font-weight: 700; color: #0F1720 }

/* Dicetak pada ukuran sebenarnya: yang di layar 2× hanya supaya terbaca
   saat diperiksa sebelum dicetak. */
@media print {
  .kartu { width: 85.6mm; min-height: 54mm; border-radius: 3mm; page-break-inside: avoid }
  .foto  { width: 15mm; height: 20mm }
  .nama  { font-size: 10.5pt }
  .jenis { font-size: 9pt }
  .jabatan, .rinci div, .sim div, .unit { font-size: 6.5pt }
  .kartu-kaki .tanggal { font-size: 8pt }
}
</style>
