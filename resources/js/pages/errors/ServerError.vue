<template>
    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-6">
        <div class="text-center max-w-md">
            <div class="text-8xl font-bold text-red-200 mb-4">500</div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Something went wrong</h1>
            <p class="text-gray-500 mb-8">
                An unexpected error occurred on the server. Please try again.
            </p>
            <div class="flex gap-3 justify-center">
                <button @click="$router.go(-1)"
                    class="px-5 py-2.5 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition-colors">
                    Go Back
                </button>
                <RouterLink to="/dashboard"
                    class="px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                    Dashboard
                </RouterLink>
            </div>
        </div>
    </div>
</template>
