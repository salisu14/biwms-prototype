<div class="space-y-2">
    @if($getNotifications()->count() === 0)
        <div class="text-center py-4 text-gray-500">
            <x-heroicon-o-bell class="w-12 h-12 mx-auto mb-2 opacity-50" />
            <p>No new employee assignments</p>
        </div>
    @else
        @foreach($getNotifications() as $notification)
            <div class="flex items-center justify-between p-3 bg-white rounded-lg shadow-sm border">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <x-dynamic-component
                            :component="'heroicon-' . str_replace('heroicon-o-', '', $notification->data['icon'])"
                            class="w-6 h-6 {{ $notification->data['action'] == 'assigned' ? 'text-green-500' : ($notification->data['action'] == 'removed' ? 'text-red-500' : 'text-blue-500') }}"
                        />
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">
                            {{ $notification->data['message'] }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>

                <div class="flex space-x-2">
                    <a href="{{ $notification->data['url'] }}"
                       class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                       onclick="markAsRead({{ $notification->id }})">
                        View
                    </a>
                    <button onclick="markAsRead({{ $notification->id }})"
                            class="text-gray-400 hover:text-gray-600">
                        ×
                    </button>
                </div>
            </div>
        @endforeach
    @endif
</div>

<script>
    function markAsRead(id) {
        fetch(`/notifications/${id}/mark-as-read`, { method: 'POST' })
            .then(() => location.reload());
    }
</script>
