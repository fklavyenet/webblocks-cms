<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Docs\DocumentationCatalog;
use Illuminate\Http\Response;
use Illuminate\View\View;

class DocumentationController extends Controller
{
    public function __construct(
        private readonly DocumentationCatalog $documentation,
    ) {}

    public function index(): View
    {
        return view('admin.docs.index', [
            'documents' => $this->documentation->all(),
        ]);
    }

    public function show(string $document): View
    {
        $selectedDocument = $this->documentation->find($document);

        abort_unless($selectedDocument !== null, Response::HTTP_NOT_FOUND);

        return view('admin.docs.show', [
            'document' => $this->documentation->withRenderedContent($selectedDocument),
            'documents' => $this->documentation->all(),
        ]);
    }
}
