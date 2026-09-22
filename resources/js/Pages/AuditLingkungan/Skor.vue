<script setup lang="ts">
/**
 * Kartu skor audit: nilai akhir, predikat, dan peringkat warna.
 *
 * Predikat dan peringkat sengaja DIPISAH, karena syaratnya berbeda:
 * peringkat warna hanya melihat angkanya, sedangkan predikat
 * penghargaan menuntut nilai PENUH pada bagian Administrasi dan
 * Implementasi. Perusahaan berskor 92 yang kehilangan satu poin di
 * bagian A mendapat peringkat EMAS tetapi TIDAK mendapat ADITAMA —
 * dan tanpa alasannya disebut, yang membacanya menyimpulkan
 * perhitungannya rusak.
 */
import CincinPersen from '../../Components/CincinPersen.vue';

defineProps<{
  skor: {
    akhir: number; pemenuhan: number; pengurang: number; belum: number; kriteria: number;
    predikat: { nama: string | null; alasan: string | null };
    peringkat: { nama: string; kriteria: string; warna: string };
    rincianKurang: Array<{ kunci: string; label: string; poin: number }>;
  };
  ringkas?: boolean;
}>();
</script>

<template>
  <section class="akl-skor">
    <CincinPersen :persen="skor.akhir" :ukuran="ringkas ? 110 : 146" :tebal="ringkas ? 11 : 14"
                  ket="skor akhir" />

    <div class="min-w-0 flex-1">
      <div class="akl-baris">
        <span class="akl-label">Predikat Penghargaan</span>
        <span v-if="skor.predikat.nama" class="akl-predikat">{{ skor.predikat.nama }}</span>
        <span v-else class="akl-kosong">tidak terbit</span>
      </div>

      <p v-if="skor.predikat.alasan" class="akl-alasan">{{ skor.predikat.alasan }}</p>

      <div class="akl-baris mt-3">
        <span class="akl-label">Peringkat</span>
        <span class="akl-peringkat" :style="{ background: skor.peringkat.warna }">
          {{ skor.peringkat.nama }}
        </span>
        <span class="akl-kriteria">{{ skor.peringkat.kriteria }}</span>
      </div>

      <dl class="akl-hitung">
        <div>
          <dt>Persentase pemenuhan</dt>
          <dd class="num">{{ skor.pemenuhan.toFixed(2) }}</dd>
        </div>
        <div>
          <dt>Nilai pengurang</dt>
          <dd class="num" :class="{ 'is-kurang': skor.pengurang > 0 }">
            {{ skor.pengurang > 0 ? '− ' + skor.pengurang : '—' }}
          </dd>
        </div>
        <div class="akl-akhir">
          <dt>Skor akhir</dt>
          <dd class="num">{{ skor.akhir.toFixed(2) }}</dd>
        </div>
      </dl>

      <ul v-if="skor.rincianKurang.length" class="akl-kurang">
        <li v-for="k in skor.rincianKurang" :key="k.kunci">
          <b class="num">−{{ k.poin }}</b> {{ k.label }}
        </li>
      </ul>

      <!-- Berapa kriteria yang belum diisi. Skor sementara yang tidak
           menyebutkannya dibaca sebagai skor akhir. -->
      <p v-if="skor.belum" class="akl-belum">
        <b class="num">{{ skor.belum }}</b> dari <b class="num">{{ skor.kriteria }}</b> kriteria belum
        diverifikasi — kriteria yang belum diisi dihitung nol, jadi skor di atas masih sementara.
      </p>
      <p v-else class="akl-lengkap">Seluruh <b class="num">{{ skor.kriteria }}</b> kriteria sudah diverifikasi.</p>
    </div>
  </section>
</template>

<style scoped>
.akl-skor {
  display: flex; align-items: flex-start; gap: 1.4rem; flex-wrap: wrap;
}

.akl-baris { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
.akl-label { font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #A8A29E; }

.akl-predikat {
  font-size: 20px; font-weight: 800; letter-spacing: -.01em; color: #0F1720;
}
.akl-kosong { font-size: 14px; font-style: italic; color: #A8A29E; }

.akl-peringkat {
  color: #fff; font-size: 12px; font-weight: 800; letter-spacing: .05em;
  padding: .18rem .7rem; border-radius: 999px;
}
.akl-kriteria { font-size: 11px; font-weight: 700; color: #78716C; letter-spacing: .03em; }

.akl-alasan {
  margin-top: .4rem; font-size: 11.5px; line-height: 1.55; color: #B45309;
  background: #FFF7E6; border: 1px solid #FDE68A; border-radius: .7rem; padding: .5rem .65rem;
}

.akl-hitung {
  display: flex; flex-wrap: wrap; gap: .3rem 1.6rem;
  margin-top: .9rem; padding-top: .7rem; border-top: 1px solid #F0EFEE;
}
.akl-hitung dt { font-size: 10.5px; color: #A8A29E; }
.akl-hitung dd { font-size: 16px; font-weight: 800; color: #44403C; margin: 0; }
.akl-hitung .is-kurang { color: #DC2626; }
.akl-akhir dd { color: #0F1720; font-size: 19px; }

.akl-kurang { margin-top: .5rem; display: grid; gap: .15rem; }
.akl-kurang li { font-size: 11px; color: #78716C; }
.akl-kurang b { color: #DC2626; margin-right: .3rem; }

.akl-belum {
  margin-top: .7rem; font-size: 11.5px; line-height: 1.5; color: #B45309;
}
.akl-lengkap { margin-top: .7rem; font-size: 11.5px; color: #15803D; }
</style>
