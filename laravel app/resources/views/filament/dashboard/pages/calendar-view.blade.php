{{-- filament/dashboard/pages/calendar-view.blade.php --}}
<x-filament::page>
    <div class="mx-auto w-full max-w-3xl">
        @php
            // Try to fetch from variable, then fallback to DB, then default email (optional)
            $calendarId = $calendarId
                ?? optional(\App\Models\GoogleCalendarApi::first())->calendar_id
                ?? null;

            $embedSrc = $calendarId
                ? 'https://calendar.google.com/calendar/embed?src=' . urlencode($calendarId)
                    . '&ctz=' . urlencode(config('app.timezone', 'UTC'))
                : null;
        @endphp

        @if ($embedSrc)
            <div class="rounded-xl shadow overflow-hidden border border-gray-200">
                <iframe
                    src="{{ $embedSrc }}"
                    style="border:0"
                    width="100%"
                    height="300"
                    frameborder="0"
                    scrolling="no"
                    loading="lazy">
                </iframe>
            </div>

            @isset($url)
                <div class="mt-4 text-center">
                    <a href="{{ $url }}" target="_blank"
                       class="inline-block px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                        Add this appointment in Google Calendar
                    </a>
                </div>
            @endisset

            <div class="mt-3 text-xs text-gray-500">
                ⚠️ If you see “You don’t have permission to view events”, make sure the
                calendar is <strong>public</strong> or <strong>shared with the correct service account</strong>.
            </div>
        @else
            <div class="p-4 text-sm text-gray-600 border rounded bg-gray-50">
                Calendar not configured. Please set a <code>calendar_id</code> in the
                <code>google_calendar_apis</code> table.
            </div>
        @endif
    </div>
</x-filament::page>
