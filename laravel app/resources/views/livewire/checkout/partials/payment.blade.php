<x-heading.h2 class="text-primary-900 !text-xl">
    {{ __('Pay with') }}
</x-heading.h2>

<div class="rounded-2xl border border-natural-300 mt-4 overflow-hidden">

    @foreach($paymentProviders as $paymentProvider)
        <div class="border-b border-natural-300 p-4">
            <div class="form-control">
                <label class="label cursor-pointer">
                    <span class="label-text ps-4 flex flex-col gap-3 me-2">
                        <span class="text-xl flex flex-row gap-3">
                            <span>
                                {{ $paymentProvider->getName() }}
                            </span>
                            <span class="-m-2">
                                <img src="{{asset('images/payment-providers/' . $paymentProvider->getSlug() . '.png')}}" alt="{{ $paymentProvider->getName() }}" class="h-6 grayscale">
                            </span>
                        </span>
                        @if ($paymentProvider->isRedirectProvider())
                            <span class="">{{ __('You will be redirected to complete your payment.') }}</span>
                        @elseif ($paymentProvider->isOverlayProvider())
                            <span class="">{{ __('You will be asked to enter your payment details in a secure overlay.') }}</span>
                        @else
                            <span class="">{{ __('You will get an email with payment instructions.') }}</span>
                        @endif
                    </span>
                    <input type="radio"
                           value="{{ $paymentProvider->getSlug() }}"
                           class="radio checked:bg-primary-500"
                           name="paymentProvider"
                           wire:model="paymentProvider"
                    />

                </label>
            </div>
        </div>

    @endforeach


    @foreach($paymentProviders as $paymentProvider)
        @includeIf('payment-providers.' . $paymentProvider->getSlug())
    @endforeach

</div>
