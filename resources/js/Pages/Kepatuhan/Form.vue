<script setup lang="ts">
/**
 * Identitas kewajiban — mengikuti kepala tabel dokumen aslinya.
 *
 * Medan yang tampil menyesuaikan sumbernya: peraturan perlu jenis dan
 * instansi penerbit, klausul ISO perlu standarnya, dokumen terkendali
 * perlu dokumen mana. Menampilkan ketiganya sekaligus berarti dua per
 * tiga medan di layar tidak pernah diisi siapa pun.
 */
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import type { HalamanKepatuhanForm } from '../../types';

const props = defineProps<HalamanKepatuhanForm>();

const f = useForm({ ...props.awal });

const kirim = () =>
  props.sunting
    ? f.transform((d) => ({ ...d, _method: 'put' })).post(props.tautan.simpan)
    : f.post(props.tautan.simpan);

const peraturan = computed(() => f.sumber === 'Peraturan');
const iso       = computed(() => f.sumber === 'ISO');
const dokumen   = computed(() => f.sumber === 'Dokumen');

const isian = 'ring-focus w-full rounded-xl border border-stone-200 px-3 py-2 text-[12.5px] transition';
const label = 'block text-[10.5px] font-bold uppercase tracking-wide text-stone-500 mb-1';
</script>

<template>
  <Head :title="judul" />

  <form class="max-w-4xl space-y-5" @submit.prevent="kirim">
    <div class="bg-white rounded-2xl shadow-card border border-stone-100 p-5 space-y-4">
      <div>
        <label :class="label" for="sumber">Sumber kewajiban</label>
        <select id="sumber" v-model="f.sumber" :class="isian" :disabled="sunting">
          <option v-for="s in opsi.sumber" :key="s" :value="s">{{ opsi.sumberNama[s] }}</option>
        </select>
        <p v-if="sunting" class="text-[11px] text-stone-400 mt-1">
          Sumber tidak diubah sesudah tersimpan — kodenya sudah terbit dan dirujuk.
        </p>
        <p v-if="f.errors.sumber" class="text-[11px] text-red-600 mt-1">{{ f.errors.sumber }}</p>
      </div>

      <div class="grid gap-3 sm:grid-cols-[200px_1fr]">
        <div v-if="peraturan">
          <label :class="label" for="jenis">Jenis</label>
          <select id="jenis" v-model="f.jenis" :class="isian">
            <option value="">— pilih —</option>
            <option v-for="j in opsi.jenis" :key="j" :value="j">{{ j }}</option>
          </select>
        </div>

        <div v-else-if="iso">
          <label :class="label" for="iso_kode">Standar</label>
          <select id="iso_kode" v-model="f.iso_kode" :class="isian">
            <option value="">— pilih —</option>
            <option v-for="s in opsi.iso" :key="s.kode" :value="s.kode">{{ s.nama }}</option>
          </select>
        </div>

        <div v-else>
          <label :class="label" for="document_id">Dokumen</label>
          <select id="document_id" v-model="f.document_id" :class="isian">
            <option value="">— pilih —</option>
            <option v-for="d in opsi.dokumen" :key="d.id" :value="String(d.id)">{{ d.nama }}</option>
          </select>
        </div>

        <div>
          <label :class="label" for="nomor">Nomor / Penanda <span class="text-red-500">*</span></label>
          <input id="nomor" v-model="f.nomor" :class="isian"
                 :placeholder="peraturan ? 'mis. Undang-Undang Nomor 1 Tahun 1970'
                               : iso ? 'mis. ISO 14001:2026' : 'mis. SOP-K3-01'">
          <p v-if="f.errors.nomor" class="text-[11px] text-red-600 mt-1">{{ f.errors.nomor }}</p>
        </div>
      </div>

      <div>
        <label :class="label" for="judul">Judul <span class="text-red-500">*</span></label>
        <textarea id="judul" v-model="f.judul" rows="2" :class="isian"
                  placeholder="Tentang apa kewajibannya"></textarea>
        <p v-if="f.errors.judul" class="text-[11px] text-red-600 mt-1">{{ f.errors.judul }}</p>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div v-if="peraturan">
          <label :class="label" for="tanggal_terbit">Tanggal terbit</label>
          <input id="tanggal_terbit" v-model="f.tanggal_terbit" type="date" :class="isian">
        </div>
        <div v-if="peraturan">
          <label :class="label" for="instansi">Instansi penerbit</label>
          <input id="instansi" v-model="f.instansi" :class="isian" placeholder="mis. Kementerian ESDM">
        </div>
        <div>
          <label :class="label" for="aspek">Aspek</label>
          <select id="aspek" v-model="f.aspek" :class="isian">
            <option v-for="a in opsi.aspek" :key="a.nilai" :value="a.nilai">{{ a.nama }}</option>
          </select>
        </div>
      </div>

      <div>
        <label :class="label" for="ruang_lingkup">Ruang lingkup</label>
        <textarea id="ruang_lingkup" v-model="f.ruang_lingkup" rows="2" :class="isian"
                  placeholder="Kegiatan perusahaan mana yang tersentuh kewajiban ini"></textarea>
      </div>

      <div>
        <label :class="label" for="rangkuman">Rangkuman</label>
        <textarea id="rangkuman" v-model="f.rangkuman" rows="3" :class="isian"
                  placeholder="Ringkas apa yang diatur — satu paragraf sudah cukup"></textarea>
      </div>

      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label :class="label" for="company_id">Perusahaan</label>
          <select id="company_id" v-model="f.company_id" :class="isian">
            <option value="">Berlaku umum</option>
            <option v-for="c in opsi.perusahaan" :key="c.id" :value="String(c.id)">{{ c.nama }}</option>
          </select>
          <p class="text-[11px] text-stone-400 mt-1">
            "Berlaku umum" dibaca seluruh perusahaan; pakai untuk peraturan yang mengikat semuanya.
          </p>
        </div>
        <div>
          <label :class="label" for="tahun">Tahun evaluasi <span class="text-red-500">*</span></label>
          <select id="tahun" v-model="f.tahun" :class="isian">
            <option v-for="t in opsi.tahun" :key="t" :value="String(t)">{{ t }}</option>
          </select>
        </div>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <button type="submit" class="eq-btn-utama" style="flex:none;padding:9px 20px" :disabled="f.processing">
        {{ f.processing ? 'Menyimpan…' : 'Simpan' }}
      </button>
      <a :href="tautan.batal" class="eq-btn-mini">Batal</a>
    </div>
  </form>
</template>
