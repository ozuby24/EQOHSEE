<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="min-h-screen bg-cam-bg text-cam-ink">
    <header class="brand-gradient text-white">
      <div class="max-w-3xl mx-auto px-5 py-5 flex items-center gap-3">
        <Link href="/" class="inline-flex items-center gap-2.5">
          <img src="/brand/eqohsee-mark-white.svg" alt="EQOHSEE" class="h-6 w-auto">
          <span class="font-extrabold tracking-wide text-lg">E<span class="text-cam-orange">Q</span>OHSEE</span>
        </Link>
      </div>
    </header>

    <main class="max-w-3xl mx-auto px-5 py-7">
      <slot />
    </main>

    <footer class="max-w-3xl mx-auto px-5 pb-8 text-center text-[11px] text-stone-400">
      Jawaban bersifat anonim dan digunakan hanya untuk penilaian PTPKKP.
    </footer>
  </div>
</template>
