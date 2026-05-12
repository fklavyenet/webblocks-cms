<?php

namespace App\Http\Requests\Admin;

use App\Models\Site;
use App\Support\Users\AdminAuthorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PageImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAccessAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'site_id' => (int) $this->input('site_id'),
            'import_as_draft' => true,
        ]);
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'json_file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:2048'],
            'import_as_draft' => ['required', 'accepted'],
            '_page_import_modal' => ['nullable', 'string', 'max:255'],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $siteId = (int) $this->input('site_id');

            if ($siteId < 1) {
                return;
            }

            $site = Site::query()->find($siteId);

            if (! $site) {
                return;
            }

            /** @var AdminAuthorization $authorization */
            $authorization = app(AdminAuthorization::class);

            try {
                $authorization->abortUnlessSiteAccess($this->user(), $site);
            } catch (HttpException) {
                $validator->errors()->add('site_id', 'You do not have permission to import pages into the selected site.');
            }
        }];
    }
}
