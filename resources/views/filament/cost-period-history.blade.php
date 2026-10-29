<div class="space-y-4">
    @forelse ($events as $event)
        <div class="border-b border-gray-200 pb-4 last:border-b-0 dark:border-gray-700">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="font-medium capitalize text-gray-950 dark:text-white">{{ $event->event }}</span>
                <time class="text-sm text-gray-500 dark:text-gray-400">{{ $event->occurred_at?->format('Y-m-d H:i:s T') }}</time>
            </div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                {{ $event->from_status }} &rarr; {{ $event->to_status }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $event->actor_name }} &lt;{{ $event->actor_email }}&gt;
            </p>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">No lifecycle events recorded.</p>
    @endforelse
</div>