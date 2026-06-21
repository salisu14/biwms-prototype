<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($this->getNavigationCards() as $card)
            <a href="{{ $card['url'] }}" class="flex flex-col p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm hover:ring-2 hover:ring-primary-500 transition">
                <div class="flex items-center gap-4 mb-4">
                    <div class="p-3 rounded-lg bg-{{ $card['color'] }}-100 dark:bg-{{ $card['color'] }}-900">
                        @svg($card['icon'], 'w-6 h-6 text-' . $card['color'] . '-600 dark:text-' . $card['color'] . '-400')
                    </div>
                    <h2 class="text-lg font-bold">{{ $card['title'] }}</h2>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ $card['description'] }}
                </p>
                <div class="mt-4 text-primary-600 font-medium text-sm inline-flex items-center">
                    Open Records
                    <x-heroicon-m-chevron-right class="w-4 h-4 ml-1" />
                </div>
            </a>
        @endforeach
    </div>
</x-filament-panels::page>
