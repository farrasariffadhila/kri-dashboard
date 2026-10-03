<?php

namespace App\Http\Controllers;

use App\Models\AuditIssue;
use App\Models\RemediationSubmission;
use App\Services\AttachmentStore;
use App\Services\RemediationWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemediationController extends Controller
{
    public function __construct(private readonly RemediationWorkflow $workflow)
    {
    }

    /** POST /api/audit-issues/{auditIssue}/plans */
    public function submitPlan(Request $request, AuditIssue $auditIssue): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            'target_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before:+1 year'],
            ...$this->fileRules(required: false),
        ]);

        $submission = $this->workflow->submitPlan(
            $auditIssue, $request->user(), $data['description'], $data['target_date'], $request->file('files', []),
        );

        return response()->json(['id' => $submission->id], 201);
    }

    /** POST /api/audit-issues/{auditIssue}/evidence */
    public function submitEvidence(Request $request, AuditIssue $auditIssue): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:20', 'max:2000'],
            ...$this->fileRules(required: true),
        ]);

        $submission = $this->workflow->submitEvidence($auditIssue, $request->user(), $data['description'], $request->file('files', []));

        return response()->json(['id' => $submission->id], 201);
    }

    /** POST /api/remediation-submissions/{submission}/review */
    public function review(Request $request, RemediationSubmission $submission): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,return'],
            'note' => ['required_if:decision,return', 'nullable', 'string', 'max:2000'],
        ]);

        $submission = $this->workflow->review($submission, $request->user(), $data['decision'] === 'approve', $data['note'] ?? null);

        return response()->json(['id' => $submission->id, 'status' => $submission->status]);
    }

    private function fileRules(bool $required): array
    {
        return [
            'files' => [$required ? 'required' : 'nullable', 'array', 'max:'.AttachmentStore::MAX_FILES],
            // `mimetypes` checks the sniffed content type, so a renamed executable is rejected.
            'files.*' => ['file', 'max:'.AttachmentStore::MAX_KILOBYTES, 'mimetypes:'.implode(',', AttachmentStore::ALLOWED_MIME_TYPES)],
        ];
    }

}
