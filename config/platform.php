<?php

return [
    // Explicit, expiring exception for one authorized local QA operator only.
    'local_qa_mfa' => [
        'user_id' => (int) env('PLATFORM_LOCAL_QA_MFA_USER_ID', 0),
        'email_sha256' => (string) env('PLATFORM_LOCAL_QA_MFA_EMAIL_SHA256', ''),
        'expires_at' => (string) env('PLATFORM_LOCAL_QA_MFA_EXPIRES_AT', ''),
    ],
    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('PLATFORM_ADMIN_EMAILS', '')),
    ))),
];
