<script setup lang="ts">
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">A</span>
                    </div>
                    <span class="text-xl font-bold text-gray-900">ASIP</span>
                </div>
            </div>
        </div>
        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-4 shadow rounded-lg sm:px-10">
                <RouterView />
            </div>
        </div>
    </div>
</template>
