<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    @php($columns = $this->columns())

    @if ($columns->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Choose a project to see its board.
            </p>
        </x-filament::section>
    @else
        <div class="mt-6 flex gap-4 overflow-x-auto pb-4">
            @foreach ($columns as $column)
                <div wire:key="col-{{ $column->status->id }}"
                     class="flex w-72 shrink-0 flex-col gap-3 rounded-xl bg-gray-100 p-3 dark:bg-white/5">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                            {{ $column->status->name }}
                        </span>
                        <span class="rounded-full bg-gray-200 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">
                            {{ $column->tickets->count() }}
                        </span>
                    </div>

                    @foreach ($column->tickets as $ticket)
                        @php($moves = $this->transitionsFor($ticket->id))
                        <div wire:key="card-{{ $ticket->id }}"
                             class="rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-xs font-medium text-primary-600 dark:text-primary-400">
                                    {{ $ticket->key }}
                                </span>
                                <x-filament::badge size="sm">
                                    {{ $ticket->priority->value }}
                                </x-filament::badge>
                            </div>
                            <p class="mt-1 text-sm text-gray-800 dark:text-gray-100">
                                {{ $ticket->title }}
                            </p>
                            @if ($ticket->assignee)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ $ticket->assignee->name }}
                                </p>
                            @endif

                            @if (! empty($moves))
                                <div class="mt-3 flex flex-wrap gap-1">
                                    @foreach ($moves as $statusId => $label)
                                        <x-filament::button
                                            size="xs"
                                            color="gray"
                                            wire:key="move-{{ $ticket->id }}-{{ $statusId }}"
                                            wire:click="move({{ $ticket->id }}, {{ $statusId }})">
                                            {{ $label }}
                                        </x-filament::button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
