{{-- The bell in the menu bar. --}}
@php($notifications = auth()->check() ? app(\TornadoEngine\Notifications\Notifications::class)->for(auth()->user()) : [])
<x-dropdown align="right" width="w-80 max-w-[calc(100vw-2rem)]" contentClasses="py-1 bg-white dark:bg-gray-700">
    <x-slot name="trigger">
        <button type="button" class="relative inline-flex size-11 items-center justify-center rounded-full text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                aria-label="{{ trans_choice('Notifications (:count)|Notifications (:count)', count($notifications), ['count' => count($notifications)]) }}">
            <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
            @if ($notifications)
                <span class="absolute right-1 top-1 flex min-w-5 h-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-semibold text-white">{{ count($notifications) }}</span>
            @endif
        </button>
    </x-slot>
    <x-slot name="content">
        <p class="px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ __('Notifications') }}</p>
        @forelse ($notifications as $notification)
            <a href="{{ $notification['url'] ?? '#' }}" class="block border-t border-gray-100 dark:border-gray-600 px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-800">
                <span class="block text-sm font-medium text-gray-900 dark:text-gray-100">{{ $notification['title'] }}</span>
                @if ($notification['text'])
                    <span class="block text-sm text-gray-600 dark:text-gray-300">{{ $notification['text'] }}</span>
                @endif
            </a>
        @empty
            <p class="border-t border-gray-100 dark:border-gray-600 px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ __('Nothing new.') }}</p>
        @endforelse
    </x-slot>
</x-dropdown>
