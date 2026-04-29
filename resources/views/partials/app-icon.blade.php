@php
    $icon = $icon ?? 'grid';
    $class = $class ?? '';
@endphp

<span class="app-icon {{ $class }}" aria-hidden="true">
    @switch($icon)
        @case('dashboard')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                <rect x="14" y="3" width="7" height="4" rx="1.5"></rect>
                <rect x="14" y="10" width="7" height="11" rx="1.5"></rect>
                <rect x="3" y="13" width="7" height="8" rx="1.5"></rect>
            </svg>
            @break

        @case('property')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 10.5 12 3l9 7.5"></path>
                <path d="M5.5 9.8V21h13V9.8"></path>
                <path d="M9.5 21v-6.5h5V21"></path>
            </svg>
            @break

        @case('security')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3 5 6v5.3c0 4.4 2.9 8.4 7 9.7 4.1-1.3 7-5.3 7-9.7V6l-7-3Z"></path>
                <path d="M9.7 11.8 11.3 13.4 14.8 9.9"></path>
            </svg>
            @break

        @case('shield')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3 5 6v5.3c0 4.4 2.9 8.4 7 9.7 4.1-1.3 7-5.3 7-9.7V6l-7-3Z"></path>
                <path d="M12 8v8"></path>
                <path d="M8.5 12H15.5"></path>
            </svg>
            @break

        @case('invite')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 6h16v12H4z"></path>
                <path d="m4 7 8 6 8-6"></path>
                <path d="M18 3v6"></path>
                <path d="M15 6h6"></path>
            </svg>
            @break

        @case('unit')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="4" width="16" height="16" rx="2"></rect>
                <path d="M9 4v16"></path>
                <path d="M15 4v16"></path>
                <path d="M4 9h16"></path>
                <path d="M4 15h16"></path>
            </svg>
            @break

        @case('tenant')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 19a4 4 0 0 0-8 0"></path>
                <circle cx="12" cy="11" r="4"></circle>
                <path d="M5 19a7 7 0 0 1 14 0"></path>
            </svg>
            @break

        @case('lease')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 3h7l5 5v13H7z"></path>
                <path d="M14 3v5h5"></path>
                <path d="M10 12h6"></path>
                <path d="M10 16h6"></path>
            </svg>
            @break

        @case('finance')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3v18"></path>
                <path d="M17 7.5c0-1.7-2.2-3-5-3s-5 1.3-5 3 1.5 2.4 5 3 5 1.3 5 3-2.2 3-5 3-5-1.3-5-3"></path>
            </svg>
            @break

        @case('logout')
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 7V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-2"></path>
                <path d="M9 12h12"></path>
                <path d="m18 7 5 5-5 5"></path>
            </svg>
            @break

        @default
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="8"></circle>
            </svg>
    @endswitch
</span>