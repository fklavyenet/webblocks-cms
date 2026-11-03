<div id="cms-job-progress" class="wb-modal" role="dialog" aria-modal="true" aria-labelledby="cms-job-progress-title"
     data-wb-job-modal data-preparing="{{ $j('preparing') }}" data-error="{{ $j('error') }}"
     data-pausing="{{ $j('pausing') }}" data-pause-error="{{ $j('pause_error') }}" data-results-label="{{ $j('view_results') }}">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="cms-job-progress-title">{{ $j('progress_title') }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $j('close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body wb-stack wb-stack-3">
            <p class="wb-m-0" data-wb-job-keep-open>{{ $j('keep_open') }}</p>
            <div class="wb-progress-bar wb-progress-bar-indeterminate" role="progressbar" aria-labelledby="cms-job-progress-state" data-wb-job-bar>
                <div class="wb-progress-bar-fill" data-wb-job-fill></div>
            </div>
            <div class="wb-table-wrap"><table class="wb-table"><tbody>
                <tr><th scope="row">{{ $j('operation') }}</th><td data-wb-job-name></td></tr>
                <tr><th scope="row">{{ $j('state') }}</th><td id="cms-job-progress-state" role="status" aria-live="polite" data-wb-job-modal-state>{{ $j('preparing') }}</td></tr>
                <tr><th scope="row">{{ $j('progress') }}</th><td data-wb-job-count>{{ $j('preparing') }}</td></tr>
                <tr><th scope="row">{{ $j('elapsed_session') }}</th><td><span role="timer" aria-live="off" data-wb-job-elapsed>0:00</span></td></tr>
            </tbody></table></div>
            <p class="wb-alert" role="status" aria-live="polite" data-wb-job-outcome hidden></p>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-job-pause>{{ $j('pause_exit') }}</button>
            <a class="wb-btn wb-btn-primary" data-wb-job-results hidden>{{ $j('view_results') }}</a>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal" data-wb-job-close hidden>{{ $j('close') }}</button>
        </div>
    </div>
</div>
