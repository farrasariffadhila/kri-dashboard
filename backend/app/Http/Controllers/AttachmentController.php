<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\RemediationSubmission;
use App\Services\AttachmentStore;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AttachmentController extends Controller
{
    /** GET /api/attachments/{attachment}: authorised download, always as a file (never rendered inline). */
    public function show(Request $request, Attachment $attachment, AttachmentStore $store): BinaryFileResponse
    {
        $user = $request->user();
        $departmentId = $this->departmentOf($attachment);

        // Need to know: a unit owner only sees evidence of their own department.
        if ($user->hasRole(Role::UnitOwner) && $user->department_id !== $departmentId) {
            throw new AccessDeniedHttpException(__('workflow.attachment_forbidden'));
        }

        ActivityLog::record('attachment.downloaded', $attachment, ['name' => $attachment->original_name], $user);

        return response()->download($store->absolutePath($attachment), $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function departmentOf(Attachment $attachment): ?int
    {
        $owner = $attachment->attachable;

        return match (true) {
            $owner instanceof RemediationSubmission => $owner->auditIssue->department_id,
            default => null,
        };
    }
}
