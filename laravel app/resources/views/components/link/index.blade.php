<a
    {{ $attributes->merge(['class' => 'leading-6 text-sm px-4 py-2 text-center hover:opacity-90 border border-primary-500 rounded-full']) }}
    {{ $attributes }}
>
    {{ $slot }}
</a>
