<x-layouts.app>
    {{-- <x-slot name="title">
        {{ __('AI-call Agent') }}
    </x-slot> --}}

    <x-section.hero class="w-full mb-8 md:mb-72">

        <div class="mx-auto text-center h-160 md:h-180 px-4">
            <x-heading.h1 class="mt-2 text-primary-50 font-bold">
                {{ __('Empower Your CallS with') }}
                <br class="hidden sm:block">
                {{ __('Smart AI') }}

            </x-heading.h1>
                <p class="text-primary-50 m-3">
                {{ __('AIcallAgent connects speech-to-text, custom LLM intelligence, and lifelike AI voices into one seamless workflow-') }}
                <br class="hidden sm:block">
                {{ __('automating real-time customer conversations effortlessly.') }}
                </p>

            <div class="flex flex-wrap gap-4 justify-center flex-col md:flex-row mt-6">
                <x-effect.glow></x-effect.glow>
                <x-button-link.secondary elementType="a" href="#pricing">{{ __('Start Free Trial') }}</x-button-link.secondary>
                <x-link class="md:block text-primary-50" href="">{{ __('Watch Demo') }}</x-link>
            </div>
    <div class="mx-auto w-full px-4 md:max-w-3xl lg:max-w-5xl">
                <img class="w-full max-w-full drop-shadow-2xl mt-8 mb-4 border-8 sm:border-[12px] md:border-[16px] border-white transition hover:scale-101 rounded-2xl"
                    src="{{URL::asset('/images/features/hero-image2.gif')}}" />
            </div>

        </div>
    </x-section.hero>
        <div class="text-center mt-16" x-intersect="$el.classList.add('slide-in-top')">
            <x-heading.h2 class="text-primary-900 font-bold">
                {{ __('Features') }}
            </x-heading.h2>
        </div>
    <x-section.columns class="max-w-none md:max-w-6xl pt-16" id="features">

        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500">
                    {{ __('Centralized Insights & Control') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Dashboard') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __('AIcallAgent’s Dashboard brings every part of your communication system together in one intelligent interface. Monitor ongoing calls, review conversation histories, and track performance analytics in real time. Customize agent behaviors, manage workflows, and view AI insights—all from a single screen.') }}
            </p>
            <p class="mt-4">
                {{ __('The dashboard allows you to fine-tune LLM interactions, control voice settings, and manage integrations with ease. Designed for clarity and speed, it helps you respond faster and optimize performance. From setup to analysis, every step is visual, actionable, and efficient. ') }}
            </p>

        </x-section.column>

        <x-section.column>
            <img src="{{URL::asset('/images/features/feature1.png')}}" dir="right"></img>
        </x-section.column>
    </x-section.columns>
    <x-section.columns class="max-w-none md:max-w-6xl  flex-wrap-reverse">
        <x-section.column>
            <img src="{{URL::asset('/images/features/feature2.png')}}" />
        </x-section.column>

        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500">
                    {{ __('AI That Understands You') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Custom Instream LLM') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __('Empower your business with a personalized language model that learns from your context. With AIcallAgent’s Instream LLM, you can build tailored conversational experiences by defining tone, domain-specific vocabulary, and intent rules. It listens, adapts, and refines its responses continuously, ensuring accuracy and brand consistency. Whether you’re managing customer support or scheduling automation, the LLM adapts to the conversation flow in real time.') }}
            </p>

            <p class="mt-4">
                {{ __('Configure custom prompts, instructions, and voice alignment—all within your dashboard. Your AI becomes smarter, more natural, and uniquely yours.') }}
            </p>
        </x-section.column>

    </x-section.columns>
    <x-section.columns class="max-w-none md:max-w-6xl mt-6">
        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500">
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Twilio Integration') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
            </p>
            <p class="mt-4">
                {{ __('AIcallAgent integrates seamlessly with Twilio to manage both inbound and outbound calls with enterprise-grade reliability. It connects your voice channels directly to AI, ensuring minimal latency and superior call quality. From phone verification to large-scale call automation, Twilio handles the communication backbone while AIcallAgent manages the intelligence. ') }}
            </p>
            <p class="mt-4">
                {{ __('You can route calls, log interactions, and maintain clear records without switching platforms. The setup is secure, scalable, and perfectly optimized for global connectivity. Every conversation starts and ends with flawless communication.') }}
            </p>
        </x-section.column>

        <x-section.column>
            <img src="{{URL::asset('/images/features/feature3.png')}}" class="rounded-2xl" />
        </x-section.column>

    </x-section.columns>
    <x-section.columns class="max-w-none md:max-w-6xl mt-6 flex-wrap-reverse">
        <x-section.column>
            <img src="{{URL::asset('/images/features/feature4.png')}}" class="rounded-2xl" />
        </x-section.column>

        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500">
                    {{ __('Human-Like AI Voices') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('ElevenLabs Voice Engine') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __(' Bring your AI conversations to life with ElevenLabs’ advanced voice synthesis. AIcallAgent allows you to create custom voices that sound human, expressive, and emotionally aware. Whether calm, energetic, or professional, each tone can be tailored to match your brand identity. Responses are generated in real time with natural pauses and inflection for maximum realism.') }}
            </p>
            <p class="mt-4">
                {{ __(' Adjust language, pitch, and speed directly through your dashboard. Combined with your custom LLM, the result is a voice assistant that feels truly alive. It’s communication that speaks like you—literally. ') }}
            </p>
        </x-section.column>

    </x-section.columns>
     <x-section.columns class="max-w-none md:max-w-6xl mt-6">
        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500">
                    {{ __('Instant Voice Intelligence') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('TTS - STT') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __('AIcallAgent seamlessly bridges speech and text with real-time conversion powered by advanced STT (Speech-to-Text) and TTS (Text-to-Speech) technology. Every spoken word is captured accurately, processed by your LLM, and returned as clear, natural-sounding audio. This instant translation enables dynamic, human-like dialogue between callers and AI.') }}
            </p>

            <p class="mt-4">
                {{ __(' It supports multiple languages, accents, and speaking speeds for global communication. With ultra-low latency and precise context understanding, conversations stay fluid and engaging. Your AI never misses a word—or a beat. ') }}
            </p>
        </x-section.column>

        <x-section.column>
            <img src="{{URL::asset('/images/features/feature5.png')}}" class="rounded-2xl" />
        </x-section.column>

    </x-section.columns>

    <x-section.columns class="max-w-none md:max-w-6xl mt-6 flex-wrap-reverse">
        <x-section.column>
            <img src="{{URL::asset('/images/features/feature6.png')}}" class="rounded-2xl" />
        </x-section.column>

        <x-section.column>
            <div x-intersect="$el.classList.add('slide-in-top')">
                <x-heading.h6 class="text-primary-500">
                    {{ __('Smart Scheduling Integration') }}
                </x-heading.h6>
                <x-heading.h2 class="text-primary-900">
                    {{ __('Google Calendar') }}
                </x-heading.h2>
            </div>

            <p class="mt-4">
                {{ __(' With AIcallAgent’s Google Calendar integration, scheduling becomes fully automated and effortless. The system can create, update, or cancel meetings directly during a call, ensuring perfect time coordination. Whether booking appointments, follow-ups, or reminders, everything syncs instantly across your devices. It works in harmony with your AI assistant, eliminating manual input and double bookings.') }}
            </p>
            <p class="mt-4">
                {{ __('You can also configure smart triggers for call summaries or meeting links post-conversation. It’s a powerful bridge between voice interactions and your daily productivity tools. Stay organized without lifting a finger. ') }}
            </p>
        </x-section.column>

    </x-section.columns>

    <section id="pricing" class="mt-4 md:mt-8 w-full bg-primary-50 bg-opacity-80 py-16">
        <x-section.columns class="max-w-none md:max-w-6xl mt-6 flex-wrap">

            <x-section.column>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-primary-500">
                        {{ __('One plan. Unlimited potential.') }}
                    </p>
                    <x-heading.h2 class="mt-4 text-primary-900">
                        {{ __('Simple Pricing for Smarter Calls') }}
                    </x-heading.h2>
                    <p class="mt-4 max-w-xl text-sm text-neutral-600 md:text-base">
                        {{ __('Experience enterprise-grade AI calling without complex tiers or hidden fees. Pay once per month and unlock every feature you need to automate, analyze, and optimize your voice workflows � all from one dashboard.') }}
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-6">
                        <div class="flex items-end gap-2">
                            <span class="text-5xl font-extrabold text-primary-600 md:text-6xl">
                                {{ __('$49') }}
                            </span>
                            <span class="text-sm font-semibold text-primary-800 md:text-base">
                                {{ __(' / month') }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-8 flex flex-wrap items-center gap-4">

                            <x-button-link.secondary elementType="a" href="">{{ __('Subsribe now') }}</x-button-link.secondary>
                            <x-link class="md:block text-primary-800" href="">{{ __('Free Trial') }}</x-link>
                    </div>
                </div>
            </x-section.column>
            <x-section.column>
                <div class="rounded-3xl bg-primary-600 p-8 text-white shadow-2xl md:p-10">
                    <p class="text-lg font-semibold">
                        {{ __('Everything Included:') }}
                    </p>
                    <ul class="mt-6 space-y-4 text-sm md:text-base">
                        @foreach ([
                            __('Real-time AI call handling'),
                            __('Custom Instream LLM (prompt control + fine-tuning)'),
                            __('Twilio call integration'),
                            __('ElevenLabs voice customization'),
                            __('Speech-to-Text & Text-to-Speech engine'),
                            __('Google Calendar sync for meetings'),
                            __('Access to full analytics dashboard'),
                            __('Priority support & updates'),
                        ] as $feature)
                            <li class="flex items-start gap-3">
                                <span class=" flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-900">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-7.25 7.25a1 1 0 01-1.414 0l-3-3a1 1 0 011.414-1.414l2.293 2.293 6.543-6.543a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                <span>{{ $feature }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </x-section.column>

        </x-section.columns>
    </section>
    <div class="text-center mt-16" x-intersect="$el.classList.add('slide-in-top')">
        <x-heading.h6 class="text-primary-500">
            {{ __('Oh, we\'re not done yet') }}
        </x-heading.h6>
        <x-heading.h2 class="text-primary-900">
            {{ __('And a whole lot more') }}
        </x-heading.h2>
    </div>
    @php
        $extendedFeatures = [
            [
                'title' => __('Call Recording & Logs'),
                'description' => __('Automatically record and store all AI-driven calls with timestamps, transcripts, and summaries for easy review and quality assurance.'),
                'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                    <rect x="3" y="6" width="18" height="12" rx="2.5"></rect>
                    <circle cx="9" cy="12" r="2" fill="currentColor"></circle>
                    <circle cx="15" cy="12" r="2" fill="currentColor"></circle>
                    <path stroke-linecap="round" d="M8 18h8"></path>
                </svg>
                SVG,
            ],
            [
                'title' => __('Custom Voice Profiles'),
                'description' => __('Design unique AI voices for different departments or scenarios sales, support, or outbound campaigns with adjustable tone and pitch.'),
                'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                    <rect x="9" y="4" width="6" height="10" rx="3"></rect>
                    <path stroke-linecap="round" d="M6.5 10v1a5.5 5.5 0 0 0 11 0v-1"></path>
                    <path stroke-linecap="round" d="M12 14v3"></path>
                    <path stroke-linecap="round" d="M9.75 20h4.5"></path>
                </svg>
                SVG,
            ],
            [
                'title' => __('Real-Time Analytics'),
                'description' => __('Track live call metrics, sentiment analysis, and AI accuracy in one clean dashboard. Gain insights that help you optimize performance instantly.'),
                'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 15.5l3-4 4 3 5-7"></path>
                    <circle cx="9" cy="11.5" r="1" fill="currentColor"></circle>
                    <circle cx="13" cy="14.5" r="1" fill="currentColor"></circle>
                    <circle cx="17" cy="8.5" r="1" fill="currentColor"></circle>
                </svg>
                SVG,
            ],
            [
                'title' => __('Prompt Library'),
                'description' => __('Save, reuse, and manage your favorite LLM prompts directly inside the platform. Easily switch AI behavior between use cases with one click.'),
                'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                    <rect x="8" y="4" width="10" height="16" rx="2"></rect>
                    <path stroke-linecap="round" d="M8 7H7a2 2 0 0 0-2 2v9.5A1.5 1.5 0 0 0 6.5 20H18"></path>
                    <path stroke-linecap="round" d="M11 9.5h5"></path>
                    <path stroke-linecap="round" d="M11 13h5"></path>
                </svg>
                SVG,
            ],
            [
                'title' => __('Multi-Agent Support'),
                'description' => __('Run multiple AI agents simultaneously each trained for different purposes. Manage, assign, and monitor them effortlessly.'),
                'icon' => <<<'SVG'
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                    <circle cx="12" cy="12" r="3.5"></circle>
                    <circle cx="6" cy="7.5" r="2.5"></circle>
                    <circle cx="18" cy="7.5" r="2.5"></circle>
                    <circle cx="7.5" cy="18" r="2.5"></circle>
                    <circle cx="16.5" cy="18" r="2.5"></circle>
                    <path stroke-linecap="round" d="M8.3 9.3l2.4 1.4"></path>
                    <path stroke-linecap="round" d="M15.7 9.3l-2.4 1.4"></path>
                    <path stroke-linecap="round" d="M9.4 16.1l1.6-2"></path>
                    <path stroke-linecap="round" d="M14.6 16.1l-1.6-2"></path>
                </svg>
                SVG,
            ],
            [
                'title' => __('Secure Data Handling'),
                'description' => __('All conversations, audio files, and text data are encrypted end-to-end to ensure maximum privacy and compliance.'),
                'icon' => <<<'SVG'
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                        <path stroke-linejoin="round" d="M12 4.25 18 6.82v4.43c0 3.88-2.63 7.58-6 8.86-3.37-1.28-6-4.98-6-8.86V6.82z"></path>
                        <rect x="9" y="11.5" width="6" height="4.5" rx="1.5"></rect>
                        <path stroke-linecap="round" d="M12 11.5V10a1.5 1.5 0 0 1 3 0v1.5"></path>
                    </svg>
                    SVG,
            ],
            [
                'title' => __('Multi-Language Support'),
                'description' => __('Communicate globally with AI that understands and speaks multiple languages fluently, supporting real-time translation on calls.'),
                'icon' => <<<'SVG'
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                            <circle cx="12" cy="12" r="8"></circle>
                            <path stroke-linecap="round" d="M4 12h16"></path>
                            <path stroke-linecap="round" d="M12 4a12 12 0 0 1 0 16"></path>
                            <path stroke-linecap="round" d="M12 4a12 12 0 0 0 0 16"></path>
                        </svg>
                        SVG,
            ],
            [
                'title' => __('24/7 AI Availability'),
                'description' => __('Your AIcallAgent never sleeps. Provide continuous customer service and appointment handling day and night without downtime.'),
                'icon' => <<<'SVG'
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                            <circle cx="12" cy="12" r="8"></circle>
                            <path stroke-linecap="round" d="M12 7v5l3 2"></path>
                            <path stroke-linecap="round" d="M6 4.5 4.5 3"></path>
                            <path stroke-linecap="round" d="M18 4.5 19.5 3"></path>
                        </svg>
                        SVG,
            ],
            [
                'title' => __('Seamless CRM Integration'),
                'description' => __('Connect AIcallAgent with your favorite CRM tools to sync call data, notes, and meeting outcomes automatically.'),
                'icon' => <<<'SVG'
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-8 w-8">
                        <rect x="4" y="11" width="7" height="9" rx="2"></rect>
                        <rect x="13" y="5" width="7" height="9" rx="2"></rect>
                        <path stroke-linecap="round" d="M11 14h2"></path>
                        <path stroke-linecap="round" d="M11 12h2"></path>
                        <path stroke-linecap="round" d="M13 9h2"></path>
                        <path stroke-linecap="round" d="M13 16h3a2 2 0 0 0 2-2v-1"></path>
                        <path stroke-linecap="round" d="M9 11v-2.5A2.5 2.5 0 0 1 11.5 6H13"></path>
                    </svg>
                    SVG,
            ],
        ];
    @endphp
    <div class="mx-auto mt-12 grid max-w-6xl gap-10 px-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($extendedFeatures as $feature)
            <div class="flex flex-col items-center text-center">
                <span class="flex h-20 w-20 items-center justify-center rounded-full bg-primary-100 text-primary-500 shadow-lg shadow-primary-200">
                    {!! $feature['icon'] !!}
                </span>
                <h3 class="mt-6 text-lg font-semibold text-primary-900">
                    {{ $feature['title'] }}
                </h3>
                    {{ $feature['description'] }}
                </p>
            </div>
        @endforeach
    </div>
   <section class="mt-20 md:mt-28 pb-16 md:pb-24">
    <div class="relative overflow-hidden bg-primary-600 px-6 py-16 pb-24 text-primary-50 md:px-12 md:pb-32">
        <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-primary-400 via-primary-300 to-primary-500 opacity-50"></div>

        <x-section.columns class="max-w-none md:max-w-6xl mt-6 flex-wrap">
            <x-section.column class="w-full">
                <div class="relative text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.35em] text-primary-200">
                        {{ __("Don't Take Our Word For It") }}
                    </p>
                    <x-heading.h2 class="mt-4 text-primary-50 font-bold">
                        {{ __('See What Our Customers Say') }}
                    </x-heading.h2>
                </div>

                @php
                    $testimonials = [
                        [
                            'initials' => 'AR',
                            'name' => 'Ahsan R.',
                            'role' => 'CTO, CloudLink Systems',
                            'quote' => 'AIcallAgent transformed how we handle inbound support calls. The voice quality feels human, and our scheduling now runs entirely through Google Calendar automation. Truly a next-gen experience.',
                        ],
                        [
                            'initials' => 'JK',
                            'name' => 'Julia K.',
                            'role' => 'Operations Manager, Finovate',
                            'quote' => 'We replaced two separate tools with AIcallAgent - now everything runs from one dashboard. Twilio integration and real-time analytics make managing our agents much simpler. Love it!',
                        ],
                        [
                            'initials' => 'TD',
                            'name' => 'Tebo Doe',
                            'role' => 'CTO, CloudLink Systems',
                            'quote' => 'The custom LLM feature is game-changing. It lets us train our AI with product-specific knowledge, and the responses sound natural through ElevenLabs voices. Fantastic product.',
                        ],
                        [
                            'initials' => 'JD',
                            'name' => 'John Doe',
                            'role' => 'CTO, CloudLink Systems',
                            'quote' => 'AIcallAgent transformed how we handle inbound support calls. The voice quality feels human, and our scheduling now runs entirely through Google Calendar automation. Truly next-gen.',
                        ],
                    ];
                @endphp

                <div class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($testimonials as $testimonial)
                        <article class="flex h-full flex-col rounded-2xl bg-white p-8 text-left text-primary-900 shadow-xl">
                            <div class="flex items-center gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-100 text-lg font-semibold text-primary-600 shadow-md">
                                    {{ $testimonial['initials'] }}
                                </div>
                                <div>
                                    <p class="text-base font-semibold">{{ $testimonial['name'] }}</p>
                                    <p class="text-sm text-neutral-500">{{ __($testimonial['role']) }}</p>
                                </div>
                            </div>
                            <div class="mt-4 flex items-center gap-1 text-yellow-400">
                                @foreach (range(1, 5) as $star)
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="gold" class="h-4 w-4">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.062 3.263a1 1 0 00.95.69h3.43c.969 0 1.371 1.24.588 1.81l-2.774 2.016a1 1 0 00-.364 1.118l1.062 3.263c.3.921-.755 1.688-1.539 1.118l-2.774-2.016a1 1 0 00-1.176 0l-2.774 2.016c-.784.57-1.838-.197-1.539-1.118l1.062-3.263a1 1 0 00-.364-1.118L3.02 8.69c-.783-.57-.38-1.81.588-1.81h3.43a1 1 0 00.95-.69l1.062-3.263z" />
                                    </svg>
                                @endforeach
                            </div>
                            <p class="mt-6 text-sm leading-relaxed text-neutral-600">
                                {{ __($testimonial['quote']) }}
                            </p>
                        </article>
                    @endforeach
                </div>
            </x-section.column>
        </x-section.columns>
    </div>
</section>

    <section class="mx-2 mt-24" id="faq">
        <div class="text-center">
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-primary-500">
                {{ __('FAQ') }}
            </p>
            <x-heading.h2 class="mt-3 text-primary-900">
                {{ __('Got a Question?') }}
            </x-heading.h2>
            <p class="mt-3 text-sm text-neutral-500 md:text-base">
                {{ __('Here are the most common questions to help you with your decision.') }}
            </p>
             <p class="mt-3 text-sm text-neutral-500 md:text-base">
                {{ __('you with your decision.') }}
            </p>
        </div>

        @php
            $faqItems = [
                [
                    'question' => __('What is AIcallAgent?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('AIcallAgent is an end-to-end AI calling platform that automates customer conversations with real-time speech recognition, natural AI dialogue, call recording, and an intuitive dashboard to manage every interaction.'),
                        ],
                    ],
                ],
                [
                    'question' => __('How does AIcallAgent handle voice calls?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('Every call is routed through secure telephony providers like Twilio, transcribed instantly, processed by your custom LLM, and answered with lifelike text-to-speech responses all in a continuous, human-sounding flow.'),
                        ],
                    ],
                ],
                [
                    'question' => __('Can I customize my AI assistant\'s tone and responses?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('Yes. Define prompts, guardrails, and personality traits inside the dashboard, then tune the assistant with custom voices from providers like ElevenLabs so every response matches your brand.'),
                        ],
                    ],
                ],
                            [
                    'question' => __('What integrations are included?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('Twilio telephony and SIP providers for global call handling.'),
                        ],
                        [
                            'type' => 'paragraph',
                            'text' => __('Google Calendar syncing for scheduling and follow-ups.'),
                        ],
                        [
                            'type' => 'paragraph',
                            'text' => __('CRM and analytics hooks to track customer journeys.'),
                        ],
                        [
                            'type' => 'paragraph',
                            'text' => __('Flexible webhooks and APIs to plug into your stack.'),
                        ],
                    ],
                ],
                [
                    'question' => __('Is my data secure?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('AIcallAgent encrypts call data in transit and at rest, provides granular role-based access, and lets you choose compliant hosting so sensitive information stays protected.'),
                        ],
                    ],
                ],
                [
                    'question' => __('Do I need coding knowledge to use AIcallAgent?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('No. Prebuilt workflows, visual setup wizards, and guided templates make it easy to launch and manage agents without writing code developers can extend it further if needed.'),
                        ],
                    ],
                ],
                [
                    'question' => __('Can AIcallAgent make outbound calls automatically?'),
                    'answer' => [
                        [
                            'type' => 'paragraph',
                            'text' => __('Absolutely. Schedule campaigns, trigger follow-up calls, and automate reminders with personalized scripts that adapt in real time to each customer conversation.'),
                        ],
                    ],
                ],
            ];
        @endphp
        <div class="mx-auto mt-10 max-w-4xl">
            <div class="rounded-3xl bg-white  ring-1 ring-primary-100/80">
                <ul class="divide-y divide-primary-100">
                    @foreach ($faqItems as $index => $item)
                        <li x-data="{ open: false }" class="px-2 py-1">
                            <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-6 rounded-2xl px-4 py-4 text-left text-primary-900 transition duration-200 hover:bg-primary-50/60 hover:text-primary-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-400 md:px-6 md:py-5">
                                <span class="text-base font-semibold md:text-lg">{{ $item['question'] }}</span>
                                <span class="text-2xl font-semibold text-primary-600">
                                    <span x-show="!open">+</span>
                                    <span x-show="open" x-cloak>&minus;</span>
                                </span>
                            </button>
                            <div x-show="open" x-cloak x-transition class="px-4 pb-4 text-sm text-neutral-600 md:px-6 md:pb-6 md:text-base">
                                @foreach ($item['answer'] as $block)
                                    @if (($block['type'] ?? 'paragraph') === 'list')
                                        <ul class="mt-3 list-disc ps-5">
                                            @foreach ($block['items'] as $entry)
                                                <li class="mt-2 first:mt-0">{{ $entry }}</li>
                                            @endforeach
                                        </ul>
                                  @elseif (($block['type'] ?? 'paragraph') === 'html')
                                        @else
                                            <p class="mt-3">{{ $block['text'] ?? '' }}</p>
                                        @endif
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>
</x-layouts.app>
