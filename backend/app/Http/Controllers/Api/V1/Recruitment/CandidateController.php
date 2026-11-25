<?php

namespace App\Http\Controllers\Api\V1\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\StoreCandidateRequest;
use App\Http\Requests\Recruitment\UploadDocumentRequest;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Services\CandidateService;
use App\Services\DocumentVaultService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CandidateController extends Controller
{
    public function __construct(
        private readonly CandidateService $candidateService,
        private readonly DocumentVaultService $documentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Candidate::class);
        $paginator = $this->candidateService->paginate($request->all(), $request->user());

        return ApiResponse::paginated($paginator, null, 'Candidates retrieved.');
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $this->authorize('create', Candidate::class);

        $result = $this->candidateService->detectDuplicates(
            $request->query('email'),
            $request->query('phone'),
            $request->query('first_name'),
            $request->query('last_name'),
            $request->query('ignore_id') ? (int) $request->query('ignore_id') : null
        );

        return ApiResponse::success($result, 'Duplicate check completed.');
    }

    public function store(StoreCandidateRequest $request): JsonResponse
    {
        $this->authorize('create', Candidate::class);
        $candidate = $this->candidateService->create($request->validated(), $request->user());

        return ApiResponse::created($candidate, 'Candidate profile created successfully.');
    }

    public function show(Candidate $candidate): JsonResponse
    {
        $this->authorize('view', $candidate);

        $candidate->load([
            'skills',
            'experiences',
            'educations',
            'documents',
            'applications.job.department',
            'applications.currentStage',
        ]);

        return ApiResponse::success($candidate, 'Candidate profile retrieved.');
    }

    public function update(StoreCandidateRequest $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $updated = $this->candidateService->update($candidate, $request->validated(), $request->user());

        return ApiResponse::success($updated, 'Candidate profile updated.');
    }

    public function uploadDocument(UploadDocumentRequest $request, Candidate $candidate): JsonResponse
    {
        if (! $request->user()->can('upload', [CandidateDocument::class, $candidate])) {
            abort(403, 'You are not authorized to upload documents for this candidate.');
        }

        $doc = $this->documentService->store(
            candidate: $candidate,
            file: $request->file('file'),
            documentType: $request->input('document_type', 'cv'),
            makePrimaryCv: (bool) $request->boolean('is_primary_cv', true),
            actor: $request->user()
        );

        return ApiResponse::created($doc, 'Document uploaded securely.');
    }

    public function downloadDocument(Request $request, CandidateDocument $document): StreamedResponse
    {
        $document->loadMissing('candidate');
        $this->authorize('view', $document);

        return $this->documentService->download($document, $request->user());
    }

    public function destroyDocument(Request $request, CandidateDocument $document): JsonResponse
    {
        $document->loadMissing('candidate');
        $this->authorize('delete', $document);

        $this->documentService->delete($document, $request->user());

        return ApiResponse::success(null, 'Document deleted successfully.');
    }
}
