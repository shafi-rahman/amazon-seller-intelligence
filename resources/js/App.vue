<script setup lang="ts">
import AppToast from '@/components/AppToast.vue'
// Session is restored by the router's beforeEach guard on first navigation.
// Calling fetchUser() here would race with the guard and cause a redirect loop.
</script>

<template>
    <RouterView />
    <AppToast />
</template>
