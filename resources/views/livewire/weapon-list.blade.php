<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-3xl font-bold text-gray-900">Dostępna broń</h2>
            <p class="mt-2 text-gray-600">Przeglądaj naszą ofertę broni i wystaw wybrane pozycje na sprzedaż</p>
        </div>

        <div class="flex items-center space-x-4">
            @if($isLoggedIn)
                <div class="flex items-center space-x-2 text-green-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span class="font-medium">Zalogowano</span>
                </div>
            @else
                <button
                    wire:click="loginToOtobron"
                    wire:loading.attr="disabled"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-3 rounded-lg transition-colors duration-200 flex items-center space-x-2 disabled:opacity-50"
                >
                    <svg wire:loading.remove wire:target="loginToOtobron" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                    </svg>
                    <svg wire:loading wire:target="loginToOtobron" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="loginToOtobron">Zaloguj się</span>
                    <span wire:loading wire:target="loginToOtobron">Logowanie...</span>
                </button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($weapons as $weapon)
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                <!-- Image -->
                <div class="relative h-64 bg-gray-200 overflow-hidden">
                    @php
                        $imageUrl = $this->getWeaponImageUrl($weapon->photos);
                    @endphp

                    @if($imageUrl)
                        <img
                            src="{{ $imageUrl }}"
                            alt="{{ $weapon->name }}"
                            class="w-full h-full object-cover"
                            loading="lazy"
                        >
                    @else
                        <div class="flex items-center justify-center h-full text-gray-400">
                            <svg class="w-20 h-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif

                    <!-- Photo Count Badge -->
                    @if($weapon->photos && count($weapon->photos) > 1)
                        <div class="absolute top-3 right-3 bg-black bg-opacity-70 text-white px-3 py-1 rounded-full text-sm flex items-center space-x-1">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ count($weapon->photos) }}</span>
                        </div>
                    @endif
                </div>

                <!-- Content -->
                <div class="p-5">
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ $weapon->name }}</h3>
                    <p class="text-gray-600 text-sm line-clamp-2 mb-4">{{ $weapon->description }}</p>

                    <div class="flex items-center justify-between mb-4">
                        <span class="text-2xl font-bold text-green-600">${{ number_format($weapon->price, 2) }}</span>
                    </div>

                    <!-- Error Alert -->
                    @if($this->hasError($weapon->id))
                        @php
                            $error = $this->getError($weapon->id);
                            $responseUrl = $this->getResponseUrl($weapon->id);
                        @endphp
                        <div class="mb-3 bg-red-50 border border-red-200 rounded-lg p-3">
                            <div class="flex items-start space-x-2">
                                <svg class="w-5 h-5 text-red-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm text-red-800 font-medium">{{ $error['error'] }}</p>
                                    @if($responseUrl)
                                        <a href="{{ $responseUrl }}" target="_blank" class="text-xs text-red-600 hover:text-red-800 underline flex items-center space-x-1 mt-1">
                                            <span>Zobacz szczegóły odpowiedzi</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Listed URL -->
                    @if($this->isListed($weapon->id) && $this->getListingUrl($weapon->id))
                        <a
                            href="{{ $this->getListingUrl($weapon->id) }}"
                            target="_blank"
                            class="mb-3 flex items-center justify-center space-x-2 text-green-600 hover:text-green-700 font-medium text-sm"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>Zobacz ogłoszenie na otobron.pl</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    @endif

                    <!-- Action Button -->
                    <div wire:loading.remove wire:target="listWeapon({{ $weapon->id }})">
                        @if($this->isListed($weapon->id))
                            <button
                                wire:click="listWeapon({{ $weapon->id }})"
                                class="w-full bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                <span>Wystaw ponownie</span>
                            </button>
                        @else
                            <button
                                wire:click="listWeapon({{ $weapon->id }})"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors duration-200 flex items-center justify-center space-x-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span>Wystaw na sprzedaż</span>
                            </button>
                        @endif
                    </div>

                    <!-- Loading State -->
                    <div wire:loading wire:target="listWeapon({{ $weapon->id }})" class="w-full">
                        <button disabled class="w-full bg-blue-400 text-white font-semibold py-3 px-4 rounded-lg flex items-center justify-center space-x-2">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>Wysyłanie...</span>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Brak broni</h3>
                <p class="mt-1 text-sm text-gray-500">Nie znaleziono żadnej broni w bazie danych.</p>
            </div>
        @endforelse
    </div>
</div>

@push('scripts')
<script>
    window.addEventListener('weapon-listed', event => {
        console.log('✅ Weapon listed successfully:', event.detail.weaponId);
        console.log('🔗 Listing URL:', event.detail.listingUrl);
        console.log('📄 Response saved to:', event.detail.responseFile);
        // No alert - success is visible in the UI
    });

    window.addEventListener('weapon-listing-error', event => {
        console.error('❌ Failed to list weapon:', event.detail.weaponId);
        console.error('Error:', event.detail.error);
        console.log('📄 Response saved to:', event.detail.responseFile);
        // Error is displayed in the UI
    });

    window.addEventListener('login-success', event => {
        console.log('✅ Login successful');
        // Success - page will reload to show logged in state
    });

    window.addEventListener('login-error', event => {
        console.error('❌ Login failed:', event.detail.message);
        alert('Błąd logowania:\n\n' + event.detail.message);
    });
</script>
@endpush
