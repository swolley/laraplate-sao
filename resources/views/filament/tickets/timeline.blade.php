@php($entries = $getState() ?? [])

<ol class="space-y-2 text-sm">
    @forelse ($entries as $entry)
        <li @class([
            'rounded-lg px-3 py-2',
            'bg-gray-50 dark:bg-white/5' => $entry['kind'] !== 'comment',
            'bg-primary-50 dark:bg-primary-500/10' => $entry['kind'] === 'comment',
        ])>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ $entry['occurred_at'] }} · {{ $entry['who'] }} · {{ $entry['kind'] }}
            </div>
            @if ($entry['body'] !== null)
                <div class="mt-1 whitespace-pre-line text-gray-950 dark:text-white">{{ $entry['body'] }}</div>
            @elseif ($entry['fields'] !== [])
                <div class="mt-1 text-gray-700 dark:text-gray-300">{{ implode(', ', $entry['fields']) }}</div>
            @endif
        </li>
    @empty
        <li class="text-gray-500 dark:text-gray-400">No history yet.</li>
    @endforelse
</ol>
