<div class="w-full max-w-full space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 w-full">

        {{-- Minutes Used --}}
        <div class="p-4 rounded-lg bg-green-100 dark:bg-green-900 shadow">
            <h4 class="text-lg font-medium text-green-900 dark:text-green-100">Used This Month / Limit</h4>
            <div class="text-2xl font-semibold text-green-800 dark:text-green-200">{{ number_format($minutes) }} / {{ number_format($minutes_limit) }}</div>
            <div class="w-full bg-gray-200 rounded-md h-4 overflow-hidden mt-2">
                <div class="bg-green-500 h-full" style="width: {{ $minutes_percent }}%"></div>
            </div>
        </div>

        {{-- OpenAI Tokens --}}
        <div class="p-4 rounded-lg bg-purple-100 dark:bg-purple-900 shadow">
            <h4 class="text-lg font-medium text-purple-900 dark:text-purple-100">OpenAI Tokens Used</h4>
            <div class="text-2xl font-semibold text-purple-800 dark:text-purple-200">{{ number_format($openai_tokens) }} / {{ number_format($openai_tokens_limit) }}</div>
            <div class="w-full bg-gray-200 rounded-md h-4 overflow-hidden mt-2">
                <div class="bg-purple-500 h-full" style="width: {{ $openai_tokens_percent }}%"></div>
            </div>
        </div>

        {{-- ElevenLabs Characters --}}
        <div class="p-4 rounded-lg bg-blue-100 dark:bg-blue-900 shadow">
            <h4 class="text-lg font-medium text-blue-900 dark:text-blue-100">Used Eleven Lab Characters</h4>
            <div class="text-2xl font-semibold text-blue-800 dark:text-blue-200">{{ number_format($elevenlabs_characters) }} / {{ number_format($elevenlabs_characters_limit) }}</div>
            <div class="w-full bg-gray-200 rounded-md h-4 overflow-hidden mt-2">
                <div class="bg-blue-600 h-full" style="width: {{ $elevenlabs_characters_percent }}%"></div>
            </div>
        </div>

    </div>

<div class="flex space-x-4 mt-16">
    <button class="px-6 py-2 w-72 text-lg bg-green-500 border border-green-700 text-white rounded hover:bg-green-600">
        Upgrade Plan
    </button>
    <button class="px-6 py-2 w-72 text-lg bg-green-300 border border-green-700 text-white rounded hover:bg-green-500">
        Buy Add-On Minutes
    </button>
</div>




</div>






