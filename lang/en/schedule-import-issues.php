<?php

return [
    'conflict' => [
        'could_not_save' => 'The change could not be saved because of a conflict',
        'lecturer_title' => 'Lecturer is unavailable at this time',
        'hall_title' => 'Hall is already in use at this time',
        'section_title' => 'The section has an overlapping lecture',
        'multiple_title' => 'The change could not be saved because of multiple conflicts',
        'multiple_message' => 'The proposed selection creates more than one time conflict.',
        'lecturer_message' => 'The selected lecturer has another overlapping lecture at this time.',
        'hall_message' => 'The selected hall has another overlapping lecture at this time.',
        'section_message' => 'The section already has another lecture during this time.',
        'lecturer_hint' => 'Choose another lecturer or change one of the time slots.',
        'hall_hint' => 'Choose another hall or change one of the time slots.',
        'section_hint' => 'Change one of the time slots or resolve the conflicting slot first.',
        'multiple_hint' => 'Choose another resource or resolve the listed conflicts first.',
        'additional' => ':count additional conflicts exist',
        'day' => 'Day', 'time' => 'Time', 'subject' => 'Subject', 'section' => 'Section', 'lecturer' => 'Lecturer', 'hall' => 'Hall',
        'field_lecturer' => 'The selected lecturer has an overlapping lecture at this time.',
        'field_hall' => 'The selected hall is already in use at this time.',
        'notification' => 'There is a time conflict. Review the details in the dialog.',
    ],
];
