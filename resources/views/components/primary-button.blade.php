<button {{ $attributes->merge(['type' => 'submit', 'class' => 'wb-btn wb-btn-primary']) }}>
    {{ $slot }}
</button>
