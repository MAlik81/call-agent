<footer class="bg-primary-500 text-white mt-12">
    <div class="mx-auto w-full max-w-screen-xl p-6 lg:py-10">
        <!-- TOP GRID -->
        <div class="grid grid-cols-12 gap-8 md:grid-flow-col">
            <!-- Brand + short blurb -->
            <div class="md:col-span-4">
                <a href="/" class="inline-flex items-center">
                    <img src="{{ URL::asset('/images/features/logo.png') }}" alt="Logo" class="h-14 w-auto md:h-16">
                </a>
                <p class="mt-4 text-sm/6 text-primary-100">
                    AICallAgent — your all-in-one AI voice automation platform. <br>
                    From smart LLM-driven conversations to lifelike voice synthesis, AIcallAgent helps businesses
                    automate calls, manage schedules, and deliver seamless customer interactions 24/7.
                </p>
            </div>

            <!-- Product -->
            <div class="md:col-span-2">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-white/80">Product</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="#features"
                            class="text-primary-100 hover:text-white transition">{{ __('Features') }}</a></li>
                    <li><a href="#pricing" class="text-primary-100 hover:text-white transition">{{ __('Pricing') }}</a>
                    </li>
                    <li><a href="#faq" class="text-primary-100 hover:text-white transition">{{ __('FAQs') }}</a>
                    </li>
                    <li><a href=""
                            class="text-primary-100 hover:text-white transition">{{ __('Documentation') }}</a></li>
                    <li><a href=""
                            class="text-primary-100 hover:text-white transition">{{ __('Intro Blog') }}</a></li>
                </ul>
            </div>

            <!-- Company -->
            <div class="md:col-span-2">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-white/80">Company</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="#features"
                            class="text-primary-100 hover:text-white transition">{{ __('About Us') }}</a></li>
                    <li><a href="#" class="text-primary-100 hover:text-white transition">Careers</a></li>
                    <li><a href="#" class="text-primary-100 hover:text-white transition">Partners</a></li>
                    <li><a href="#faq" class="text-primary-100 hover:text-white transition">{{ __('Contact') }}</a>
                    </li>
                </ul>
            </div>

            <!-- Support -->
            <div class="md:col-span-2">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-white/80">Support</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    <li><a href="#faq"
                            class="text-primary-100 hover:text-white transition">{{ __('Help Center') }}</a></li>
                    <li><a href="#faq"
                            class="text-primary-100 hover:text-white transition">{{ __('System Status') }}</a></li>
                    <li><a href="#faq" class="text-primary-100 hover:text-white transition">{{ __('API Docs') }}</a>
                    </li>
                    <li><a href="#faq"
                            class="text-primary-100 hover:text-white transition">{{ __('Privacy Policy') }}</a></li>
                    <li><a href="{{ route('terms-of-service') }}"
                            class="text-primary-100 hover:text-white transition">{{ __('Terms of Service') }}</a></li>
                </ul>
            </div>

            <!-- Social + Newsletter -->
            <div class="md:col-span-2">
                <h3 class="text-sm font-semibold uppercase tracking-wider text-white/80">Get in touch</h3>
                <div class="mt-4 flex flex-wrap gap-3">

                    <a href="mailto:info@keyaccny.com" aria-label="Email"
                        class="flex h-7 w-7 items-center justify-center rounded-full border border-white/40 text-white transition hover:bg-white/10">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                            aria-hidden="true">
                            <rect x="3" y="5" width="18" height="14" rx="2" />
                            <path d="M3 7.5 12 13l9-5.5" />
                        </svg>
                    </a>

                </div>

                <h3 class="mt-8 text-sm font-semibold uppercase tracking-wider text-white/80">Newsletter</h3>
                <p class="mt-3 text-xs text-primary-100">Stay up to date with our latest news.</p>
                <form class="mt-3 flex w-full items-center gap-2" action="#" method="POST">
                    <input type="email" name="email" placeholder="Email"
                        class="w-full rounded-full bg-white px-4 py-2.5 text-sm text-primary-700 placeholder:text-primary-400 outline-none focus:ring-2 focus:ring-white/60">
                    <x-button-link.secondary elementType="a"
                        href="#">{{ __('Subcribe') }}</x-button-link.secondary>

                </form>
            </div>
        </div>

        <!-- Divider -->
        <hr class="my-8 border-white/20">

        <!-- Bottom bar -->
        <div class="flex flex-col items-center justify-center gap-4">
            <span class="text-xs text-primary-100">
                © {{ date('Y') }}
                <a href="/" class="hover:underline text-primary-50">AIcallAgent</a>.
                {{ __('All rights reserved.') }}
            </span>
        </div>
    </div>
</footer>
