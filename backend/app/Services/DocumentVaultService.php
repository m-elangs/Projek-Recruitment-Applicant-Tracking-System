<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentVaultService
{
    public function __construct(
        private readonly AuditLogService $auditLog
    ) {}

    public function store(
        Candidate $candidate,
        UploadedFile $file,
        string $documentType,
        bool $makePrimaryCv,
        User $actor
    ): CandidateDocument {
        return DB::transaction(function () use ($candidate, $file, $documentType, $makePrimaryCv, $actor) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $safeUuidName = Str::uuid()->toString().'.'.$extension;
            $directory = "candidates/{$candidate->id}";
            $disk = 'private';

            $checksum = hash_file('sha256', $file->getRealPath());
            $storedPath = $file->storeAs($directory, $safeUuidName, $disk);

            if (! $storedPath) {
                throw new BusinessRuleException('FILE_STORAGE_FAILED', 'Unable to store uploaded document.', 500);
            }

            $isPrimary = false;
            if ($documentType === 'cv') {
                $hasExistingPrimary = $candidate->documents()
                    ->where('document_type', 'cv')
                    ->where('is_primary_cv', true)
                    ->exists();

                $isPrimary = $makePrimaryCv || ! $hasExistingPrimary;

                if ($isPrimary) {
                    $candidate->documents()
                        ->where('document_type', 'cv')
                        ->update(['is_primary_cv' => false]);
                }
            }

            $document = CandidateDocument::query()->create([
                'candidate_id' => $candidate->id,
                'uploaded_by' => $actor->id,
                'document_type' => $documentType,
                'original_name' => $file->getClientOriginalName(),
                'storage_disk' => $disk,
                'file_path' => $storedPath,
                'mime_type' => $file->getClientMimeType() ?: 'application/pdf',
                'file_size_bytes' => (int) $file->getSize(),
                'checksum_sha256' => $checksum,
                'is_primary_cv' => $isPrimary,
            ]);

            $this->auditLog->log(
                eventType: 'document.uploaded',
                auditable: $document,
                description: "Document '{$document->original_name}' ({$document->document_type}) uploaded for {$candidate->full_name}",
                actor: $actor,
                newValues: ['document_type' => $documentType, 'is_primary_cv' => $isPrimary]
            );

            return $document;
        });
    }

    public function download(CandidateDocument $document, User $actor): StreamedResponse
    {
        $disk = Storage::disk($document->storage_disk);

        if (! $disk->exists($document->file_path)) {
            throw new BusinessRuleException('DOCUMENT_FILE_MISSING', 'The requested document file could not be found in private storage.', 404);
        }

        $this->auditLog->log(
            eventType: 'document.downloaded',
            auditable: $document,
            description: "Private document '{$document->original_name}' accessed by {$actor->email}",
            actor: $actor
        );

        return $disk->download($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    public function delete(CandidateDocument $document, User $actor): void
    {
        $attachedToActiveApplication = Application::query()
            ->where('cv_document_id', $document->id)
            ->where('is_active', true)
            ->exists();

        if ($attachedToActiveApplication && ! $actor->isAdmin()) {
            throw new BusinessRuleException(
                'DOCUMENT_IN_USE',
                'This CV is attached to an active job application and cannot be deleted.',
                422
            );
        }

        DB::transaction(function () use ($document, $actor) {
            $wasPrimary = $document->is_primary_cv;
            $candidateId = $document->candidate_id;

            $this->auditLog->log(
                eventType: 'document.deleted',
                auditable: $document,
                description: "Document '{$document->original_name}' deleted",
                actor: $actor
            );

            $document->delete();

            if ($wasPrimary) {
                $replacement = CandidateDocument::query()
                    ->where('candidate_id', $candidateId)
                    ->where('document_type', 'cv')
                    ->latest('id')
                    ->first();

                $replacement?->update(['is_primary_cv' => true]);
            }
        });
    }
}
