<x-filament::page>
    <div class="grid grid-cols-2 gap-6 w-full">

        {{-- LEFT SIDE: Form + Save Button --}}
        <div class="border-2 border-green-500 rounded-lg p-6 bg-white mt-4 shadow-sm">
            {{ $this->form }}

            <div class="mt-4">
                <button type="button" wire:click="submit"
                    class="px-4 py-2 bg-green-500 mt-4 text-white rounded hover:bg-green-600 w-32">
                    Save
                </button>
            </div>
        </div>

        {{-- RIGHT SIDE: Status Button --}}
        <div class="flex items-center justify-center">
            <div x-data="{ open: false }">
                <button type="button" @click="open = true"
                    class="px-6 py-3 mt-4 mb-36 bg-green-500 text-white rounded hover:bg-green-600 w-48">
                    Status Websocket
                </button>

                <!-- Modal -->
                <div x-show="open" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div @click.away="open = false" class="bg-white p-6 rounded-lg shadow-lg w-96">
                        <h2 class="text-2xl font-bold mb-4 text-center">WebSocket Server Status</h2>

                        <p class="mb-0 text-center">
                            <strong>Status:</strong>
                            <span
                                class="px-2 py-1 rounded font-semibold {{ $this->status === 'Server Running' ? 'bg-green-300' : 'bg-red-300' }} text-black">
                                {{ $this->status }}
                            </span>

                        </p>


                        <p class="text-center mt-1"><strong>Active Calls:</strong> {{ $this->activeCalls }}</p>

                        <div class="mt-4 flex justify-center">
                            <button @click="open = false"
                                class="px-4 py-2 bg-green-500 text-white rounded hover:bg-gray-600">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament::page>
