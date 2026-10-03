<?php

// Overrides for messages users can trigger from the UI; the rest come from Laravel's English defaults.
return [
    'custom' => [
        'description' => [
            'required' => 'A description is required.',
            'min' => 'The description needs at least :min characters; explain the steps taken.',
            'max' => 'The description may not exceed :max characters.',
        ],
        'target_date' => [
            'required' => 'A target date is required.',
            'after_or_equal' => 'The target date cannot be in the past.',
            'before' => 'The target date must be within one year.',
            'date_format' => 'The target date is not a valid date.',
        ],
        'files' => [
            'required' => 'Attach at least one evidence file.',
            'max' => 'At most :max files.',
        ],
        'files.*' => [
            'max' => 'Each file may be at most 5 MB.',
            'mimetypes' => 'Files must be PDF, PNG or JPG.',
        ],
        'decision' => ['required' => 'Choose a decision.'],
        'note' => [
            'required_if' => 'A note is required when returning a submission.',
            'max' => 'The note may not exceed :max characters.',
        ],
    ],
];
