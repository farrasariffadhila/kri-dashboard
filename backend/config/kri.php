<?php

/*
|--------------------------------------------------------------------------
| Key Risk Indicator scoring
|--------------------------------------------------------------------------
|
| Every KRI is mapped to a 0-100 risk score (higher = worse) by linear
| interpolation between four anchors taken from the bank's risk vocabulary:
|
|   appetite  -> 0    risk we accept; at or better than this scores 0
|   tolerance -> 50   start of the Warning band
|   limit     -> 75   start of the Critical band
|   max       -> 100  saturation point; anything worse scores 100
|
| Anchors may be ascending (more is worse) or descending (less is worse, e.g.
| uptime). The weighted average of the KRI scores is the Composite Risk Score.
| Full rationale: docs/RISK_METHODOLOGY.md
|
*/

return [

    // The month the dashboard reports on (YYYY-MM). Pinned instead of now() so the
    // dashboard always describes a closed reporting period.
    'reporting_month' => env('KRI_REPORTING_MONTH', '2026-09'),

    'history_months' => 6,

    // Portfolio demo: password-less "sign in as" for accounts flagged is_demo. Keep false in production.
    'demo_mode' => (bool) env('KRI_DEMO_MODE', false),

    // Optional password for the demo accounts, to also try the normal sign-in form.
    // When empty, DemoUserSeeder sets a random password nobody knows.
    'demo_password' => env('DEMO_USER_PASSWORD'),

    // Folder with the CSV extracts imported by KriCsvSeeder.
    'csv_path' => env('KRI_CSV_PATH', base_path('../data-generator/output')),

    /*
    | Where each KRI comes from. In production these would be live integrations; in this
    | portfolio project every source is simulated by data-generator/generate.py, which the UI states.
    | Display values are given in both UI languages (id, en).
    */
    'sources' => [
        'unpatched_servers' => [
            'system' => ['id' => 'Vulnerability scanner dan CMDB', 'en' => 'Vulnerability scanner and CMDB'],
            'owner' => ['id' => 'Divisi Operasional TI', 'en' => 'IT Operations Division'],
            'cadence' => ['id' => 'Pemindaian harian, rekap per akhir bulan', 'en' => 'Daily scans, summarised at month end'],
            'tables' => ['servers', 'server_compliance_snapshots'],
        ],
        'open_high_audits' => [
            'system' => ['id' => 'Aplikasi GRC, modul temuan audit', 'en' => 'GRC application, audit findings module'],
            'owner' => ['id' => 'Satuan Kerja Audit Intern', 'en' => 'Internal Audit'],
            'cadence' => ['id' => 'Diperbarui saat temuan dibuka atau ditutup', 'en' => 'Updated whenever a finding is opened or closed'],
            'tables' => ['audit_issues'],
        ],
        'uptime' => [
            'system' => ['id' => 'Monitoring aplikasi dan ITSM', 'en' => 'Application monitoring and ITSM'],
            'owner' => ['id' => 'Divisi Operasional TI', 'en' => 'IT Operations Division'],
            'cadence' => ['id' => 'Rekap bulanan per aplikasi kritikal', 'en' => 'Monthly summary per critical application'],
            'tables' => ['incidents'],
        ],
        'phishing_failure' => [
            'system' => ['id' => 'Platform simulasi phishing', 'en' => 'Phishing simulation platform'],
            'owner' => ['id' => 'Divisi Keamanan Informasi', 'en' => 'Information Security Division'],
            'cadence' => ['id' => 'Satu kampanye per departemen per bulan', 'en' => 'One campaign per department per month'],
            'tables' => ['phishing_tests'],
        ],
    ],
    'sources_simulated' => true,

    'weights' => [
        'unpatched_servers' => 0.35,
        'open_high_audits' => 0.25,
        'uptime' => 0.25,
        'phishing_failure' => 0.15,
    ],

    'thresholds' => [
        // % of servers that are non-compliant (missing patches or EOL OS)
        'unpatched_servers' => ['appetite' => 2.0, 'tolerance' => 10.0, 'limit' => 20.0, 'max' => 40.0],
        // Number of open High-risk audit findings
        'open_high_audits' => ['appetite' => 0, 'tolerance' => 5, 'limit' => 10, 'max' => 20],
        // Average monthly availability of critical applications, in %
        'uptime' => ['appetite' => 99.95, 'tolerance' => 99.9, 'limit' => 99.5, 'max' => 99.0],
        // % of employees who failed the monthly phishing simulation
        'phishing_failure' => ['appetite' => 3.0, 'tolerance' => 10.0, 'limit' => 20.0, 'max' => 30.0],
    ],

    // Composite score bands used by the heatmap.
    'bands' => [
        'critical' => 75, // score > 75
        'warning' => 50,  // 50 <= score <= 75; below 50 is Safe
    ],

];
