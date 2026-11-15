<div class="wb-popover" data-wb-popover data-wb-slot-block-locale-help data-wb-placement="top-center">
    <button type="button" class="wb-action-btn" data-wb-toggle="popover"
        popovertarget="wb-slot-block-locale-help" aria-controls="wb-slot-block-locale-help" aria-expanded="false"
        aria-label="{{ $helpLabel }}" aria-describedby="wb-slot-block-locale-help"
        data-wb-tooltip="{{ $editingHelp }} {{ $translationsHelp }}" data-wb-tooltip-wrap>
        <i class="wb-icon wb-icon-info" aria-hidden="true"></i>
    </button>
    <div id="wb-slot-block-locale-help" class="wb-popover-panel wb-admin-slot-block-help-panel" popover="auto">
        <div class="wb-popover-body">{{ $editingHelp }} {{ $translationsHelp }}</div>
    </div>
</div>
