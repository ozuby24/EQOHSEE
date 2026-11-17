<script setup lang="ts">
/**
 * Kutipan penutup halaman.
 *
 * SATU KALIMAT SAJA, dan tidak pernah berisi angka maupun tautan.
 * Bagian ini menutup halaman — apa pun yang perlu ditindak tidak
 * boleh mendarat di sini, sebab pembaca yang sudah sampai ke bawah
 * sedang berhenti membaca, bukan mulai.
 */
withDefaults(defineProps<{ teks: string; kanan?: string | null }>(), { kanan: null });
</script>

<template>
  <section class="kutip">
    <span class="kutip-ikon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
           stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 20A7 7 0 0 1 4 13V8a4 4 0 0 1 4-4h1a11 11 0 0 1 2 16Z"/>
        <path d="M11 20c6 0 9-4 9-9"/>
      </svg>
    </span>

    <p class="kutip-teks">{{ teks }}</p>

    <span v-if="kanan" class="kutip-kanan">{{ kanan }}</span>
  </section>
</template>

<style>
.kutip {
  display: flex;
  align-items: center;
  gap: .7rem;
  border-radius: 1rem;
  padding: .8rem 1.1rem;
  background: var(--kutip-latar, #F3FBF6);
  border: 1px solid var(--kutip-garis, #DDF0E4);
}

.kutip-ikon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; flex: none;
  color: var(--kutip-ikon, #15803D);
}

.kutip-ikon svg { width: 18px; height: 18px; }

.kutip-teks {
  margin: 0;
  font-size: 12.5px;
  font-style: italic;
  color: var(--kutip-teks, #3F5A49);
}

.kutip-kanan {
  margin-left: auto;
  font-size: 10.5px;
  text-align: right;
  line-height: 1.3;
  color: var(--kutip-kanan, #8AA396);
  white-space: pre-line;
}

:root[data-tema="gelap"] .kutip {
  --kutip-latar: #12211A;
  --kutip-garis: #1D3328;
  --kutip-ikon: #7FD6A6;
  --kutip-teks: #A8C9B6;
  --kutip-kanan: #6C8A79;
}

@media (max-width: 640px) { .kutip-kanan { display: none; } }
</style>
