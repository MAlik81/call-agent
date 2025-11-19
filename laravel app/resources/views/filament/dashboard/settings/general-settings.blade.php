<x-filament::page class="space-y-6">

    <div x-data="{ activeTab: 'profile' }" class="space-y-4">

        {{-- Tabs navigation --}}
        <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
            <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" role="tablist">

                {{-- Profile --}}
                <li class="mr-2" role="presentation">
                    <button
                        :class="activeTab === 'profile' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 p-4 border-b-2 rounded-t-lg"
                        @click="activeTab = 'profile'" type="button" role="tab">
                        <x-heroicon-s-user class="w-5 h-5" /> Profile
                    </button
                </li>

                {{-- General Settings --}}
                <li class="mr-2" role="presentation">
                    <button
                        :class="activeTab === 'general' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 p-4 border-b-2 rounded-t-lg"
                        @click="activeTab = 'general'" type="button" role="tab">
                        <x-heroicon-s-cog class="w-5 h-5" /> General Settings
                    </button>
                </li>

                {{-- Settings --}}
                <li class="mr-2" role="presentation">
                    <button
                        :class="activeTab === 'settings' ? 'border-purple-600 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-600 dark:text-gray-400 dark:hover:text-gray-300'"
                        class="inline-flex items-center gap-2 p-4 border-b-2 rounded-t-lg"
                        @click="activeTab = 'settings'" type="button" role="tab">
                        <x-heroicon-s-chart-bar class="w-5 h-5" /> Settings
                    </button>
                </li>


            </ul>
        </div>

        {{-- Tabs content --}}
        <div>
            {{-- Profile --}}
            <div x-show="activeTab === 'profile'" class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">User Profile</h3>
                <p class="text-gray-500 dark:text-gray-400">Manage your profile information and account settings.</p>
            </div>

            {{-- General Settings --}}
            <div x-show="activeTab === 'general'" class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">General Settings Overview</h3>
                <p class="text-gray-500 dark:text-gray-400">View and modify global application settings.</p>
            </div>

            {{-- Settings --}}
            <div x-show="activeTab === 'settings'" class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm">
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-2">Application Settings</h3>
                <p class="text-gray-500 dark:text-gray-400">Customize your application preferences and options.</p>
            </div>


        </div>

    </div>

</x-filament::page>
