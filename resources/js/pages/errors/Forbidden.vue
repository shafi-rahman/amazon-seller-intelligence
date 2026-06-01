<template>
    <div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-6">
        <div class="text-center max-w-md">
            <div class="text-8xl font-bold text-orange-200 mb-4">403</div>
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Access denied</h1>
            <p class="text-gray-500 mb-8">
                You don't have permission to access this resource.
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
