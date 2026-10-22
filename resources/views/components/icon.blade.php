@props(['name' => 'dot', 'class' => 'h-5 w-5'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1.5" />
            <rect x="14" y="3" width="7" height="7" rx="1.5" />
            <rect x="3" y="14" width="7" height="7" rx="1.5" />
            <rect x="14" y="14" width="7" height="7" rx="1.5" />
            @break
        @case('calendar')
            <rect x="3" y="4.5" width="18" height="16" rx="2.5" />
            <path d="M8 2.75v3.5M16 2.75v3.5M3 9.5h18" />
            <path d="M8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 19 7a4 4 0 0 1-3 3.87" />
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3.5 2" />
            @break
        @case('phone')
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.77.62 2.61a2 2 0 0 1-.45 2.11L8 9.7a16 16 0 0 0 6 6l1.26-1.26a2 2 0 0 1 2.11-.45c.84.29 1.71.5 2.61.62A2 2 0 0 1 22 16.92Z" />
            @break
        @case('chart')
            <path d="M3 3v18h18" />
            <path d="M7 16v-5M12 16V7M17 16v-8" />
            @break
        @case('download')
            <path d="M12 3v12M7 10l5 5 5-5" />
            <path d="M5 21h14" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('chevron-left')
            <path d="m15 18-6-6 6-6" />
            @break
        @case('chevron-right')
            <path d="m9 18 6-6-6-6" />
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6" />
            @break
        @case('search')
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
            @break
        @case('x')
            <path d="M18 6 6 18M6 6l12 12" />
            @break
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16" />
            @break
        @case('logout')
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
            <path d="m16 17 5-5-5-5M21 12H9" />
            @break
        @case('shield')
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('arrow-right')
            <path d="M5 12h14M13 6l6 6-6 6" />
            @break
        @case('warning')
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" />
            <path d="M12 9v4M12 17h.01" />
            @break
        @case('check')
            <path d="m20 6-11 11-5-5" />
            @break
        @case('check-circle')
            <circle cx="12" cy="12" r="9" />
            <path d="m8.5 12.5 2.5 2.5 4.5-5" />
            @break
        @case('edit')
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
            @break
        @case('trash')
            <path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6" />
            @break
        @case('eye')
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
            <circle cx="12" cy="12" r="3" />
            @break
        @case('refresh')
            <path d="M21 12a9 9 0 1 1-2.64-6.36" />
            <path d="M21 3v6h-6" />
            @break
        @case('sparkles')
            <path d="m12 3 1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3ZM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z" />
            @break
        @case('briefcase')
            <rect x="2" y="7" width="20" height="14" rx="2.5" />
            <path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2M2 12h20" />
            @break
        @case('mail')
            <rect x="2" y="4" width="20" height="16" rx="2.5" />
            <path d="m3 6 9 7 9-7" />
            @break
        @case('user')
            <circle cx="12" cy="8" r="4" />
            <path d="M4 21a8 8 0 0 1 16 0" />
            @break
        @case('building')
            <path d="M3 21h18M5 21V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v16M15 9h2a2 2 0 0 1 2 2v10" />
            <path d="M9 7h2M9 11h2M9 15h2" />
            @break
        @case('filter')
            <path d="M4 5h16M7 12h10M10 19h4" />
            @break
        @case('file')
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
            <path d="M14 2v6h6M8 13h8M8 17h8" />
            @break
        @case('moon')
            <path d="M21 12.8A9 9 0 1 1 11.2 3 7 7 0 0 0 21 12.8Z" />
            @break
        @case('swap')
            <path d="M7 4 3 8l4 4M3 8h13a4 4 0 0 1 4 4M17 20l4-4-4-4M21 16H8a4 4 0 0 1-4-4" />
            @break
        @case('dots')
            <circle cx="5" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
            <circle cx="19" cy="12" r="1" fill="currentColor" stroke="none" />
            @break
        @case('bolt')
            <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z" />
            @break
        @case('inbox')
            <path d="M22 12h-6l-2 3h-4l-2-3H2" />
            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 18.76 4H5.24a2 2 0 0 0-1.79 1.11Z" />
            @break
        @default
            <circle cx="12" cy="12" r="8" />
    @endswitch
</svg>
