<script setup lang="ts">
/**
 * Isian satu sesi observasi.
 *
 * Aktual CT, selisih, dan status dihitung LANGSUNG saat komponen
 * diketik, supaya observer melihat hasilnya selagi masih berdiri di
 * front dan dapat langsung memberi umpan balik kepada operatornya.
 * Hitungan di sini hanya pratinjau; yang tersimpan dan yang dipakai
 * seluruh laporan adalah hitungan server (App\Support\Frop\Penilaian).
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { bertanda, detik, persen } from './fmt';
import './frop.css';

type Isian = Record<string, string>;

const props = defineProps<{
  judul: string;
  awal: Isian;
  sunting: boolean;
  opsi: {
    level: Array<{ kunci: string; label: string; contoh: string; plan: number }>;
    rasio: Record<string, number>;
    kondisi_mesin: string[]; mode_kerja: string[]; operating_condition: string[];
    metode_loading: string[]; mto: string[]; kondisi_permukaan: string[]; cuaca: string[];
    status_ca: string[];
    operator: string[]; unit: string[]; material: string[]; metode_posisi: string[]; orang: string[];
    ambang: { loading: number; passing: number; spotting: number; digging: number; pty: number };
  };
  tautan: { simpan: string; batal: string };
}>();

const f = useForm<Isian>({ ...props.awal });

const kirim = () =>
  props.sunting
    ? f.transform((d) => ({ ...d, _method: 'put' })).post(props.tautan.simpan, { preserveScroll: true })
    : f.post(props.tautan.simpan, { preserveScroll: true });

const angka = (v: string): number | null => (v === '' || v === null || isNaN(Number(v)) ? null : Number(v));

const levelKini = computed(() => props.opsi.level.find((l) => l.kunci === f.level));
const plan = computed(() => angka(f.plan_ct) ?? levelKini.value?.plan ?? null);

const KOMPONEN = [
  ['digging', 'Digging'], ['swl', 'Swing Loaded'], ['dump', 'Dump'], ['swe', 'Swing Empty'],
] as const;

const ct = computed(() => {
  let n = 0;
  for (const [k] of KOMPONEN) {
    const v = angka(f[k]);
    if (v === null) return null;
    n += v;
  }
  return Math.round(n * 100) / 100;
});
const selisih = computed(() => (ct.value === null || plan.value === null ? null : Math.round((ct.value - plan.value) * 100) / 100));
const on = computed(() => (selisih.value === null ? null : selisih.value <= 0));

const jumlahRasio = computed(() => Object.values(props.opsi.rasio).reduce((a, b) => a + b, 0));
const planKomponen = (k: string) =>
  plan.value === null ? null : Math.round((plan.value * (props.opsi.rasio[k] ?? 0)) / jumlahRasio.value * 10) / 10;

const pty = computed(() => {
  const t = angka(f.target_pty), a = angka(f.aktual_pty);
  return t && a !== null ? a / t : null;
});

const detikLoading = computed(() => {
  const t = (f.loading ?? '').trim();
  const m = t.match(/^(\d{1,2}):([0-5]\d)$/);
  if (m) return Number(m[1]) * 60 + Number(m[2]);
  return /^\d{1,4}$/.test(t) ? Number(t) : null;
});
const passing = computed(() => {
  const m = (f.n_passing ?? '').match(/\d+/g);
  return m ? Math.max(...m.map(Number)) : null;
});
</script>

<template>
  <Head :title="judul" />

  <form class="space-y-4" @submit.prevent="kirim">
    <div v-if="Object.keys(f.errors).length" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[12px] text-red-700">
      <b>Sesi belum tersimpan.</b> Periksa isian bertanda merah di bawah.
    </div>

    <div class="fr-kisi fr-kisi-3-1">
      <div class="space-y-4">
        <!-- Waktu & identitas -->
        <section class="fr-kartu">
          <div class="fr-kartu-kepala"><h3>Waktu &amp; Identitas</h3></div>
          <div class="fr-isi grid gap-3 sm:grid-cols-3">
            <div>
              <label class="fr-label" for="tanggal">Tanggal *</label>
              <input id="tanggal" v-model="f.tanggal" type="date" class="fr-isian">
              <p v-if="f.errors.tanggal" class="fr-galat">{{ f.errors.tanggal }}</p>
            </div>
            <div>
              <label class="fr-label" for="shift">Shift</label>
              <select id="shift" v-model="f.shift" class="fr-isian">
                <option value="">–</option><option value="1">1</option><option value="2">2</option><option value="3">3</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="jam">Jam observasi</label>
              <input id="jam" v-model="f.jam_observasi" class="fr-isian" placeholder="09.00 – 10.00">
            </div>
            <div class="sm:col-span-2">
              <label class="fr-label" for="operator">Operator *</label>
              <input id="operator" v-model="f.operator" class="fr-isian" list="fr-operator" autocomplete="off">
              <datalist id="fr-operator"><option v-for="o in opsi.operator" :key="o" :value="o" /></datalist>
              <p v-if="f.errors.operator" class="fr-galat">{{ f.errors.operator }}</p>
            </div>
            <div>
              <label class="fr-label" for="unit">CN Unit *</label>
              <input id="unit" v-model="f.unit" class="fr-isian" list="fr-unit" placeholder="Ex 699 / PC 1250" autocomplete="off">
              <datalist id="fr-unit"><option v-for="u in opsi.unit" :key="u" :value="u" /></datalist>
              <p v-if="f.errors.unit" class="fr-galat">{{ f.errors.unit }}</p>
            </div>
            <div>
              <label class="fr-label" for="gl">GL Front</label>
              <input id="gl" v-model="f.gl_front" class="fr-isian" list="fr-orang" autocomplete="off">
            </div>
            <div>
              <label class="fr-label" for="observer">Observer</label>
              <input id="observer" v-model="f.observer" class="fr-isian" list="fr-orang" autocomplete="off">
            </div>
            <div>
              <label class="fr-label" for="verified">Verified by</label>
              <input id="verified" v-model="f.verified_by" class="fr-isian" list="fr-orang" autocomplete="off"
                     placeholder="GL / Superintendent">
            </div>
            <datalist id="fr-orang"><option v-for="o in opsi.orang" :key="o" :value="o" /></datalist>
          </div>
        </section>

        <!-- Kondisi & operasi -->
        <section class="fr-kartu">
          <div class="fr-kartu-kepala"><h3>Kondisi &amp; Operasi</h3></div>
          <div class="fr-isi grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-3">
              <span class="fr-label">Jenis material (menentukan Plan CT) *</span>
              <div class="grid gap-2 sm:grid-cols-3">
                <label v-for="l in opsi.level" :key="l.kunci"
                       class="relative cursor-pointer rounded-xl border p-3 transition"
                       :class="f.level === l.kunci ? 'fr-pilihan' : 'border-stone-200 hover:border-stone-300'">
                  <input v-model="f.level" type="radio" :value="l.kunci" class="sr-only">
                  <span class="flex items-baseline justify-between">
                    <b class="text-[13px]">{{ l.label }}</b>
                    <span class="num text-[12px] font-bold text-orange-700">Plan {{ l.plan }} dtk</span>
                  </span>
                  <span class="mt-1 block text-[10.5px] leading-snug text-stone-500">{{ l.contoh }}</span>
                </label>
              </div>
              <p v-if="f.errors.level" class="fr-galat">{{ f.errors.level }}</p>
            </div>
            <div>
              <label class="fr-label" for="material">Material</label>
              <input id="material" v-model="f.material" class="fr-isian" list="fr-material" autocomplete="off">
              <datalist id="fr-material"><option v-for="m in opsi.material" :key="m" :value="m" /></datalist>
            </div>
            <div>
              <label class="fr-label" for="posisi">Metode posisi</label>
              <input id="posisi" v-model="f.metode_posisi" class="fr-isian" list="fr-posisi" autocomplete="off">
              <datalist id="fr-posisi"><option v-for="m in opsi.metode_posisi" :key="m" :value="m" /></datalist>
            </div>
            <div>
              <label class="fr-label" for="mloading">Metode loading</label>
              <select id="mloading" v-model="f.metode_loading" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.metode_loading" :key="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="mesin">Kondisi mesin</label>
              <select id="mesin" v-model="f.kondisi_mesin" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.kondisi_mesin" :key="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="mode">Mode kerja</label>
              <select id="mode" v-model="f.mode_kerja" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.mode_kerja" :key="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="opcon">Operating condition</label>
              <select id="opcon" v-model="f.operating_condition" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.operating_condition" :key="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="mto">MTO</label>
              <select id="mto" v-model="f.mto" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.mto" :key="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="jenjang">Tinggi jenjang (m)</label>
              <input id="jenjang" v-model="f.tinggi_jenjang" type="number" step="0.1" min="0" class="fr-isian num">
            </div>
            <div>
              <label class="fr-label" for="lebar">Lebar front (m)</label>
              <input id="lebar" v-model="f.lebar_front" type="number" step="0.1" min="0" class="fr-isian num">
            </div>
          </div>
        </section>

        <!-- Front condition -->
        <section class="fr-kartu">
          <div class="fr-kartu-kepala">
            <h3>Front Condition</h3>
            <span class="fr-ket">kondisi yang paling sering menjelaskan CT yang lewat plan</span>
          </div>
          <div class="fr-isi grid gap-3 sm:grid-cols-4">
            <div>
              <label class="fr-label" for="permukaan">Kondisi permukaan</label>
              <select id="permukaan" v-model="f.kondisi_permukaan" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.kondisi_permukaan" :key="o">{{ o }}</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="boulder">Boulder</label>
              <select id="boulder" v-model="f.boulder" class="fr-isian">
                <option value="">–</option><option value="1">Ada</option><option value="0">Tidak ada</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="sudut">Sudut pass pertama</label>
              <input id="sudut" v-model="f.sudut_pass" class="fr-isian" placeholder="90°, &lt;90°">
            </div>
            <div>
              <label class="fr-label" for="cuaca">Cuaca</label>
              <select id="cuaca" v-model="f.cuaca" class="fr-isian">
                <option value="">–</option><option v-for="o in opsi.cuaca" :key="o">{{ o }}</option>
              </select>
            </div>
          </div>
        </section>

        <!-- Komponen CT -->
        <section class="fr-kartu">
          <div class="fr-kartu-kepala">
            <h3>Komponen Cycle Time (detik)</h3>
            <span class="fr-ket">rata-rata beberapa siklus yang diukur dengan stopwatch</span>
          </div>
          <div class="fr-isi grid gap-3 grid-cols-2 sm:grid-cols-5">
            <div>
              <label class="fr-label" for="spotting">Spotting</label>
              <input id="spotting" v-model="f.spotting" type="number" step="0.1" min="0" class="fr-isian num"
                     :class="{ 'border-amber-400': (angka(f.spotting) ?? 0) > opsi.ambang.spotting }">
              <p class="fr-ket mt-1">tidak masuk CT</p>
              <p v-if="f.errors.spotting" class="fr-galat">{{ f.errors.spotting }}</p>
            </div>
            <div v-for="[k, nama] in KOMPONEN" :key="k">
              <label class="fr-label" :for="k">{{ nama }} *</label>
              <input :id="k" v-model="f[k]" type="number" step="0.1" min="0" class="fr-isian num"
                     :class="{ 'border-red-400': angka(f[k]) !== null && planKomponen(k) !== null && angka(f[k])! > planKomponen(k)! }">
              <p class="fr-ket mt-1 num">plan {{ detik(planKomponen(k), 1) }}</p>
              <p v-if="f.errors[k]" class="fr-galat">{{ f.errors[k] }}</p>
            </div>
          </div>
          <div class="fr-isi pt-0 grid gap-3 sm:grid-cols-3">
            <div>
              <label class="fr-label" for="plan">Plan CT (dtk)</label>
              <input id="plan" v-model="f.plan_ct" type="number" step="0.1" min="1" class="fr-isian num"
                     :placeholder="levelKini ? String(levelKini.plan) + ' — mengikuti material' : ''">
              <p class="fr-ket mt-1">Kosongkan agar mengikuti jenis material.</p>
              <p v-if="f.errors.plan_ct" class="fr-galat">{{ f.errors.plan_ct }}</p>
            </div>
          </div>
        </section>

        <!-- Loading & PTY -->
        <section class="fr-kartu">
          <div class="fr-kartu-kepala"><h3>Loading &amp; Produktivitas</h3></div>
          <div class="fr-isi grid gap-3 grid-cols-2 sm:grid-cols-5">
            <div>
              <label class="fr-label" for="loading">Loading time</label>
              <input id="loading" v-model="f.loading" class="fr-isian num" placeholder="m:ss, mis. 1:25"
                     :class="{ 'border-amber-400': (detikLoading ?? 0) > opsi.ambang.loading }">
              <p class="fr-ket mt-1">standar ≤ 1:30 per hauler</p>
              <p v-if="f.errors.loading" class="fr-galat">{{ f.errors.loading }}</p>
            </div>
            <div>
              <label class="fr-label" for="passing">N passing</label>
              <input id="passing" v-model="f.n_passing" class="fr-isian num" placeholder="5 atau 5-6"
                     :class="{ 'border-amber-400': (passing ?? 0) > opsi.ambang.passing }">
              <p class="fr-ket mt-1">standar ≤ {{ opsi.ambang.passing }}</p>
              <p v-if="f.errors.n_passing" class="fr-galat">{{ f.errors.n_passing }}</p>
            </div>
            <div>
              <label class="fr-label" for="heap">Bucket heap</label>
              <select id="heap" v-model="f.bucket_heap" class="fr-isian">
                <option value="">–</option><option value="1">✅ Heap</option><option value="0">❌ Tidak heap</option>
              </select>
            </div>
            <div>
              <label class="fr-label" for="tpty">Target PTY (BCM)</label>
              <input id="tpty" v-model="f.target_pty" type="number" min="0" class="fr-isian num">
              <p v-if="f.errors.target_pty" class="fr-galat">{{ f.errors.target_pty }}</p>
            </div>
            <div>
              <label class="fr-label" for="apty">Aktual PTY (BCM)</label>
              <input id="apty" v-model="f.aktual_pty" type="number" min="0" class="fr-isian num">
              <p class="fr-ket mt-1">dari data tim Engineering</p>
              <p v-if="f.errors.aktual_pty" class="fr-galat">{{ f.errors.aktual_pty }}</p>
            </div>
          </div>
        </section>

        <!-- Temuan & CA -->
        <section class="fr-kartu">
          <div class="fr-kartu-kepala">
            <h3>Temuan &amp; Corrective Action</h3>
            <span class="fr-ket">pisahkan tiap temuan dengan titik koma</span>
          </div>
          <div class="fr-isi grid gap-3 sm:grid-cols-2">
            <div>
              <label class="fr-label" for="temuan">Catatan / temuan</label>
              <textarea id="temuan" v-model="f.temuan" rows="3" maxlength="2000" class="fr-isian"
                        placeholder="Exc menggantung; front undulating; material keras"></textarea>
              <p v-if="f.errors.temuan" class="fr-galat">{{ f.errors.temuan }}</p>
            </div>
            <div>
              <label class="fr-label" for="ca">Corrective action</label>
              <textarea id="ca" v-model="f.corrective_action" rows="3" maxlength="2000" class="fr-isian"></textarea>
              <p v-if="f.errors.corrective_action" class="fr-galat">{{ f.errors.corrective_action }}</p>
            </div>
            <div class="grid gap-3 grid-cols-3 sm:col-span-2">
              <div>
                <label class="fr-label" for="pic">PIC</label>
                <input id="pic" v-model="f.pic_ca" class="fr-isian" list="fr-orang" autocomplete="off">
              </div>
              <div>
                <label class="fr-label" for="deadline">Deadline</label>
                <input id="deadline" v-model="f.deadline_ca" type="date" class="fr-isian">
              </div>
              <div>
                <label class="fr-label" for="status">Status CA</label>
                <select id="status" v-model="f.status_ca" class="fr-isian">
                  <option v-for="o in opsi.status_ca" :key="o">{{ o }}</option>
                </select>
              </div>
            </div>
            <div class="sm:col-span-2">
              <label class="fr-label" for="catatan">Catatan lain</label>
              <textarea id="catatan" v-model="f.catatan" rows="2" maxlength="2000" class="fr-isian"></textarea>
            </div>
          </div>
        </section>
      </div>

      <!-- Pratinjau hasil -->
      <aside>
        <div class="fr-kartu lg:sticky lg:top-20">
          <div class="fr-kartu-kepala"><h3>Hasil Sementara</h3></div>
          <div class="fr-isi space-y-3">
            <div class="text-center">
              <div class="fr-ket uppercase font-bold tracking-wide">Aktual CT</div>
              <div class="num text-[34px] font-extrabold leading-tight"
                   :class="on === null ? 'text-stone-400' : on ? 'fr-teks-baik' : 'fr-teks-gawat'">
                {{ detik(ct) }}<small class="text-[14px]"> dtk</small>
              </div>
              <div class="fr-ket num">plan {{ detik(plan, 1) }} dtk · selisih {{ bertanda(selisih) }}</div>
              <span class="fr-lencana mt-2" :class="'fr-' + (on === null ? 'netral' : on ? 'baik' : 'gawat')">
                {{ on === null ? 'Isi keempat komponen' : on ? 'ON TARGET' : 'OVER TARGET' }}
              </span>
            </div>

            <div v-for="[k, nama] in KOMPONEN" :key="k" class="text-[11.5px]">
              <div class="flex justify-between"><span>{{ nama }}</span>
                <span class="num" :class="angka(f[k]) !== null && planKomponen(k) !== null && angka(f[k])! > planKomponen(k)! ? 'fr-teks-gawat' : ''">
                  {{ detik(angka(f[k])) }} / {{ detik(planKomponen(k), 1) }}
                </span>
              </div>
              <div class="fr-pita mt-1">
                <div :style="{ width: Math.min(100, ((angka(f[k]) ?? 0) / (planKomponen(k) || 1)) * 100) + '%',
                               background: angka(f[k]) !== null && planKomponen(k) !== null && angka(f[k])! > planKomponen(k)! ? '#DC2626' : '#16A34A' }"></div>
              </div>
            </div>

            <dl class="fr-dl pt-2 border-t border-stone-100">
              <dt>Pencapaian PTY</dt>
              <dd :class="pty === null ? 'fr-redup' : pty >= opsi.ambang.pty ? 'fr-teks-baik' : 'fr-teks-ingat'">{{ persen(pty) }}</dd>
              <dt>Loading</dt>
              <dd :class="(detikLoading ?? 0) > opsi.ambang.loading ? 'fr-teks-ingat' : ''">
                {{ detikLoading === null ? '–' : detikLoading + ' dtk' }}
              </dd>
            </dl>

            <div class="flex flex-wrap gap-2 pt-2">
              <button type="submit" class="eq-btn-utama" style="flex:none;padding:9px 20px" :disabled="f.processing">
                {{ f.processing ? 'Menyimpan…' : 'Simpan Sesi' }}
              </button>
              <Link :href="tautan.batal" class="eq-btn-mini">Batal</Link>
            </div>
          </div>
        </div>
      </aside>
    </div>
    <!-- Di layar sempit panel hasil turun ke bawah isian; bilah ini
         menjaga CT tetap terlihat selagi komponennya diketik. -->
    <div class="fr-bilah-bawah lg:hidden fixed inset-x-0 bottom-0 z-20 flex items-center gap-3 border-t border-stone-200 bg-white/95 px-4 py-2.5 backdrop-blur">
      <div class="min-w-0">
        <div class="fr-ket uppercase font-bold">Aktual CT</div>
        <div class="num text-[18px] font-extrabold leading-tight"
             :class="on === null ? 'text-stone-400' : on ? 'fr-teks-baik' : 'fr-teks-gawat'">
          {{ detik(ct) }} <small class="text-[11px] font-semibold text-stone-500">/ {{ detik(plan, 1) }} · {{ bertanda(selisih) }}</small>
        </div>
      </div>
      <button type="submit" class="eq-btn-utama ml-auto" style="flex:none" :disabled="f.processing">
        {{ f.processing ? 'Menyimpan…' : 'Simpan' }}
      </button>
    </div>
    <div class="h-16 lg:hidden" aria-hidden="true"></div>
  </form>
</template>
