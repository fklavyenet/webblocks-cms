<strong>
    @isset($href)
        <a class="wb-link" href="{{ $href }}">{{ $name }}</a>
    @else
        <button type="button" class="wb-link wb-cms-row-title-button" data-wb-toggle="modal" data-wb-target="#{{ $target }}"
                aria-controls="{{ $target }}" aria-haspopup="dialog" @isset($language) lang="{{ $language }}" @endisset>{{ $name }}</button>
    @endisset
</strong>
<div class="wb-text-sm wb-text-muted"><code>{{ $identifier }}</code></div>
