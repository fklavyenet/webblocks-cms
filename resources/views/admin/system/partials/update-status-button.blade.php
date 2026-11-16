<button type="button" class="wb-btn wb-btn-primary" data-pc-update-status aria-haspopup="dialog"
        aria-controls="pc-update-progress-dialog" aria-expanded="false" @if (! $trackedOperation) hidden @endif>
  {{ $adminText('updates.show_status') }}
</button>
