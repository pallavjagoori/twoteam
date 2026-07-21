<script setup lang="ts">
const apiUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
</script>

<template>
  <main>
    <p class="eyebrow">Twoteam</p>
    <h1>Compatibility platform foundation</h1>
    <p>
      The Vue host is ready. The Chatwoot frontend integration begins in the
      next roadmap increment.
    </p>
    <p class="api">API: {{ apiUrl }}</p>
  </main>
</template>
