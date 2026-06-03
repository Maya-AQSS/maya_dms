<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Services\DocumentDocxExportService;
use Symfony\Component\HttpFoundation\Response;

/**
 * .docx export.
 *
 * Import is intentionally client-side: the wizard's DocxBlockSplitter reads the
 * uploaded .docx in the browser (mammoth.js → HTML → blocks) and creates blocks
 * via the existing block API, so no server-side import endpoint is needed.
 *
 * Export renders the document's blocks (TipTap JSON) to HTML and packages it as
 * a .docx. Authorization is enforced via the `view` policy on the target document.
 */
final class DocumentDocxController extends Controller
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documentRepository,
        private readonly DocumentDocxExportService $docxExportService,
    ) {}

    public function export(string $document): Response
    {
        // Resolve document via repository with access control scope applied.
        // The `user_access` global scope filters out documents the caller may not see (→ 404).
        $documentModel = $this->documentRepository->findByIdWithAccessControl($document);

        // Policy gate covers found-but-denied case (authorization check).
        $this->authorize('view', $documentModel);

        // Delegate DOCX rendering logic to service layer.
        $export = $this->docxExportService->exportToDocx($document);

        return response($export['bin'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.addslashes($export['filename']).'"',
        ]);
    }
}
