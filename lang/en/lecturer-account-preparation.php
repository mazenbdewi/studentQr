<?php

return [
    'title' => 'Lecturer account preparation',
    'saved' => 'Lecturer account preparation saved',
    'fields' => [
        'lecturer_name' => 'Lecturer name in Arabic',
        'linked_account' => 'Linked account',
        'email' => 'Email',
        'account_status' => 'Account status',
        'course_lecturer_role_status' => 'Course lecturer role status',
        'weekly_slots_count' => 'Weekly slots count',
        'readiness_status' => 'Generation readiness status',
        'password' => 'Temporary password',
        'password_confirmation' => 'Confirm temporary password',
    ],
    'actions' => [
        'create_account' => 'Create lecturer login account',
        'link_existing_account' => 'Link lecturer to existing account',
        'grant_course_lecturer_role' => 'Grant course lecturer role',
    ],
    'statuses' => [
        'missing_account' => 'No linked account',
        'broken_link' => 'Broken account link',
        'duplicate_link' => 'Account linked to more than one lecturer',
        'deleted_account' => 'Deleted account',
        'inactive_account' => 'Inactive account',
        'active_account' => 'Active account',
        'role_granted' => 'Course lecturer role granted',
        'role_missing' => 'Course lecturer role missing',
        'missing_course_lecturer_role' => 'Missing course lecturer role',
        'ready' => 'Ready for generation',
    ],
    'readiness_filter' => [
        'missing_account' => 'No linked account',
        'linked' => 'Linked account',
    ],
    'validation' => [
        'lecturer_already_linked' => 'This lecturer is already linked to a login account.',
        'user_already_linked' => 'This account is already linked to another lecturer.',
        'no_linked_user' => 'This lecturer has no linked account.',
    ],
];
