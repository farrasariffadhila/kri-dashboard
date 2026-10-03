<?php

return [
    'plan_not_allowed' => 'An action plan cannot be submitted at this stage.',
    'evidence_not_allowed' => 'Remediation evidence can only be submitted after the action plan is approved.',
    'auditor_only' => 'Only an internal auditor can review submissions.',
    'own_submission' => 'You cannot review your own submission.',
    'already_reviewed' => 'This submission has already been reviewed.',
    'owner_only' => 'Only the unit owner of this department can submit remediation.',
    'issue_closed' => 'This finding is already closed.',
    'attachment_forbidden' => 'You are not authorised to open this file.',
    'no_work_queue' => 'Your role has no follow-up queue.',
    'dispute_owner_only' => 'Only a unit owner can dispute figures of their own department.',
    'dispute_not_disputable' => 'This indicator cannot be disputed. Audit findings are corrected through the remediation workflow.',
    'dispute_same_value' => 'The proposed figure is the same as the current one.',
    'dispute_already_open' => 'There is already an open dispute for this indicator.',
    'dispute_out_of_range' => 'The figure must be between 0 and :max, and a whole number for server or employee counts.',
    'dispute_reviewer_only' => 'Only IT Risk can review disputes.',
    'dispute_forbidden' => 'You are not authorised to open another department\'s dispute.',
];
