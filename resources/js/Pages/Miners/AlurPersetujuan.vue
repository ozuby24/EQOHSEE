<script setup lang="ts">
/**
 * Alur Persetujuan — layar `admin-sistem/alur` Safe Track.
 *
 * Satu kartu per IUP: penyetuju bernama untuk Dokter MCU, HSE
 * (verifikasi), dan KTT, beserta penerima tambahan dan tembusan surelnya.
 * Selama belum lengkap, kartunya menyebut peran yang masih kosong — dan
 * pengajuan IUP itu tidak dapat dikirim.
 */
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { propHalaman } from '../../halaman';
import KepalaHalaman from '../../Components/Miners/KepalaHalaman.vue';
import Kartu from '../../Components/Miners/Kartu.vue';
import Medan from '../../Components/Miners/Medan.vue';

const props = propHalaman();

const kartu = computed<any[]>(() => (props.kartu ?? []) as any[]);

/* Satu formulir per kartu, dibuat sekali. */
const forms = Object.fromEntries(kartu.value.map((k) => [k.iup, useForm({ ...k.isi })]));

function simpan(k: any) {
  forms[k.iup].put(`/miners/alur-persetujuan/${k.iup}`, { preserveScroll: true });
}

const nomor = (i: number | string) => Number(i) + 1;
</script>

<template>
  <div>
    <KepalaHalaman judul="Alur Persetujuan" :remah="[['Home', '/miners/dasbor'], ['Administrasi', null], ['Alur Persetujuan', null]]" />

    <div class="mn-alert mn-alert-info">
      <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/><path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/></svg>
      <div>
        Urutan persetujuan: <b>Pengaju → Dokter MCU → HSE (verifikasi) → KTT</b>.
        <b>Selama belum lengkap, pengajuan tidak bisa dikirim</b> — pengguna diberi tahu peran mana yang masih kosong.
        Alamat pada kolom penerima tambahan dan tembusan boleh dipisah koma atau titik koma.
        Menyimpan alur memindahkan langkah yang sedang menunggu ke penyetuju baru.
        <ul class="mt-1.5 grid gap-x-6 sm:grid-cols-2">
          <li v-for="r in props.rantai" :key="r.kunci"><b>{{ r.label }}</b>: {{ r.peran.join(' → ') }}</li>
        </ul>
      </div>
    </div>

    <div class="grid gap-x-5 lg:grid-cols-2">
      <Kartu v-for="k in kartu" :key="k.iup">
        <h2 class="mn-kartu-judul flex items-center gap-2 mb-3">
          <img v-if="k.logo" :src="k.logo" alt="" class="mn-logo" @error="($event.target as HTMLImageElement).style.display = 'none'">
          <span>{{ k.kode }}</span>
          <small>{{ k.nama }}</small>
        </h2>

        <div v-if="k.kurang.length" class="mn-alert mn-alert-peringatan">
          <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5m.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
          <span>Belum lengkap: {{ k.kurang.join(', ') }}<template v-if="k.iup && !k.sendiri"> — sementara memakai alur umum.</template></span>
        </div>
        <div v-else class="mn-alert mn-alert-sukses">
          <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0m-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>
          <span>Alur lengkap, pengajuan bisa diproses.</span>
        </div>

        <form @submit.prevent="simpan(k)">
          <div v-for="(p, i) in k.peran" :key="p.kunci" class="mb-3 pb-3 mn-garis-bawah">
            <Medan :label="`${nomor(i)}. ${p.label}`" :untuk="`${p.kunci}-${k.iup}`" :galat="forms[k.iup].errors[p.kolom]">
              <select :id="`${p.kunci}-${k.iup}`" v-model="forms[k.iup][p.kolom]" class="mn-isian mb-2" :title="p.label">
                <option :value="null">— belum ditentukan —</option>
                <option v-for="u in k.pengguna" :key="u.id" :value="u.id">
                  {{ u.nama }} ({{ u.email }}){{ u.peran.length ? ' · ' + u.peran.join(', ') : '' }}
                </option>
              </select>
            </Medan>

            <Medan v-if="p.penerima.to" label="Penerima tambahan — ikut menerima seperti penerima utama"
                   :untuk="`${p.penerima.to}-${k.iup}`" :galat="forms[k.iup].errors[p.penerima.to]">
              <input :id="`${p.penerima.to}-${k.iup}`" v-model="forms[k.iup][p.penerima.to]" class="mn-isian mb-2"
                     placeholder="alamat1@contoh.com, alamat2@contoh.com">
            </Medan>

            <Medan v-if="p.penerima.cc" label="Tembusan (CC) — hanya diberi tahu, tidak menyetujui"
                   :untuk="`${p.penerima.cc}-${k.iup}`" :galat="forms[k.iup].errors[p.penerima.cc]">
              <input :id="`${p.penerima.cc}-${k.iup}`" v-model="forms[k.iup][p.penerima.cc]" class="mn-isian"
                     placeholder="alamat@contoh.com, lainnya@contoh.com">
            </Medan>
          </div>

          <button type="submit" class="mn-btn mn-btn-utama" :disabled="forms[k.iup].processing">
            Simpan alur {{ k.kode }}
          </button>
        </form>
      </Kartu>
    </div>

    <p v-if="!kartu.length" class="mn-kosong">Tidak ada IUP yang dapat Anda atur.</p>
  </div>
</template>
