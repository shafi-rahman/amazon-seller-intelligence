<template>
    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-6">
        <div class="text-center max-w-md">
            <div class="text-8xl font-bold text-indigo-200 mb-4">404</div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Page not found</h1>
            <p class="text-gray-500 mb-8">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <RouterLink to="/dashboard"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Back to Dashboard
            </RouterLink>
        </div>
    </div>
</template>
