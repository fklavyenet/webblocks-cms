<button {{ $attributes->merge(['type' => 'submit', 'class' => 'wb-btn wb-btn-danger']) }}>
    {{ $slot }}
</button>
