<x-filament::page class="space-y-6">

    {{-- Back Button --}}
    <div class="flex items-center space-x-2">
        <button
            onclick="history.back()"
            class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg text-sm flex items-center space-x-1">
            <!-- Optional icon -->
            <span>←</span>
            <span>Back</span>
        </button>
        <h2 class="text-lg font-semibold">Chat</h2>
    </div>

    <div x-data x-init="$el.scrollTop = $el.scrollHeight"
        class="flex flex-col space-y-4 max-h-[500px] overflow-y-auto p-4 border rounded bg-white">

        @forelse ($record->messages->sortBy('id') as $message)
            @php
                $role = strtolower($message->role);
                $isUser = $role === 'user';

                $audioAsset = $message->audioAsset;

                $audioPath = $audioAsset ? Storage::url($audioAsset->path) : null;
                if ($role !== 'user' && $audioPath) {
                    $audioPath = str_replace('/storage/htt', 'htt', $audioPath);
                }
            @endphp

            <div class="flex rounded {{ $isUser ? 'justify-end' : 'justify-start' }}">
                <div class="max-w-[75%]">

                    {{-- Text bubble --}}
                    @if($message->text)
                        <div class="px-4 py-2 rounded-lg flex flex-col
                                    {{ $isUser
                                        ? 'bg-green-500 text-white rounded-br-none'
                                        : 'bg-gray-200 text-black rounded-bl-none' }}">
                            <div class="whitespace-pre-wrap">{{ $message->text }}</div>

                            {{-- Audio player --}}
                            @if($audioPath)
                                <div x-data="audioPlayer('{{ $audioPath }}')"
                                     class="mt-2 flex {{ $isUser ? 'justify-start' : 'justify-end' }}">
                                    <div class="flex items-center space-x-2 cursor-pointer" @click="togglePlay($event)">
                                        <!-- Play / Pause Icon (no background) -->
                                        <div class="text-lg text-green-600">
                                            <template x-if="!isPlaying">
                                                <span>▶</span>
                                            </template>
                                            <template x-if="isPlaying">
                                                <span>⏸</span>
                                            </template>
                                        </div>

                                        <!-- Animated waveform bars -->
                                        <div class="flex items-end space-x-1 h-4">
                                            <span class="w-1 bg-green-600 rounded"
                                                  :class="isPlaying ? 'animate-wave-1' : ''"></span>
                                            <span class="w-1 bg-green-600 rounded"
                                                  :class="isPlaying ? 'animate-wave-2' : ''"></span>
                                            <span class="w-1 bg-green-600 rounded"
                                                  :class="isPlaying ? 'animate-wave-3' : ''"></span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- Footer (role + time) --}}
                    <div class="text-xs text-gray-500 mt-1 {{ $isUser ? 'text-right' : 'text-left' }}">
                        {{ ucfirst($message->role) }}
                        @if($message->created_at)
                            • {{ $message->created_at->format('H:i') }}
                        @endif
                    </div>
                </div>
            </div>

        @empty
            <p class="text-gray-500 text-center">No messages yet.</p>
        @endforelse

    </div>

    {{-- Alpine.js logic --}}
    <script>
        // Global variable to track currently playing audio
        let currentAudio = null;

        function audioPlayer(src) {
            return {
                player: null,
                isPlaying: false,
                togglePlay() {
                    // Stop the currently playing audio if it exists
                    if (currentAudio && currentAudio !== this) {
                        currentAudio.player.pause();
                        currentAudio.isPlaying = false;
                    }

                    if (!this.player) {
                        this.player = new Audio(src);
                        this.player.addEventListener('ended', () => {
                            this.isPlaying = false;
                            currentAudio = null;
                        });
                    }

                    if (this.isPlaying) {
                        this.player.pause();
                        this.isPlaying = false;
                        currentAudio = null;
                    } else {
                        this.player.play();
                        this.isPlaying = true;
                        currentAudio = this;
                    }
                }
            }
        }
    </script>

    {{-- Custom CSS for waveform --}}
    <style>
        @keyframes wave {
            0%, 100% { height: 4px; }
            50% { height: 16px; }
        }

        .animate-wave-1 {
            animation: wave 1s infinite ease-in-out;
        }
        .animate-wave-2 {
            animation: wave 1s infinite ease-in-out 0.2s;
        }
        .animate-wave-3 {
            animation: wave 1s infinite ease-in-out 0.4s;
        }
    </style>
</x-filament::page>
