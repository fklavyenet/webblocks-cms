<button {{ $attributes->merge(['type' => 'button', 'class' => 'wb-btn wb-btn-secondary']) }}>
    {{ $slot }}
</button>
