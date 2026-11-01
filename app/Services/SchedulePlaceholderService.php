<?php

namespace App\Services;

use App\Models\Hall;
use App\Models\Lecturer;

class SchedulePlaceholderService
{
    public function theoreticalLecturer(): Lecturer
    {
        return $this->lecturer(
            Lecturer::PLACEHOLDER_THEORETICAL,
            'مدرس نظري غير محدد',
            'Unassigned Theoretical Lecturer',
        );
    }

    public function practicalLecturer(): Lecturer
    {
        return $this->lecturer(
            Lecturer::PLACEHOLDER_PRACTICAL,
            'مدرس عملي غير محدد',
            'Unassigned Practical Lecturer',
        );
    }

    public function unassignedHall(): Hall
    {
        $hall = Hall::withTrashed()
            ->where('placeholder_type', Hall::PLACEHOLDER_UNASSIGNED)
            ->orWhere('code', 'X0')
            ->first();

        if (! $hall instanceof Hall) {
            $hall = Hall::query()->createOrFirst(
                ['placeholder_type' => Hall::PLACEHOLDER_UNASSIGNED],
                [
                    'code' => 'X0',
                    'name' => 'قاعة غير محددة',
                    'name_en' => 'Unassigned Hall',
                    'hall_type' => Hall::TYPE_OTHER,
                    'is_active' => true,
                ],
            );
        }

        $hall->forceFill([
            'code' => 'X0',
            'name' => 'قاعة غير محددة',
            'name_en' => 'Unassigned Hall',
            'placeholder_type' => Hall::PLACEHOLDER_UNASSIGNED,
            'hall_type' => Hall::TYPE_OTHER,
            'is_active' => true,
            'deleted_at' => null,
        ])->save();

        return $hall->refresh();
    }

    private function lecturer(string $type, string $arabicName, string $englishName): Lecturer
    {
        $lecturer = Lecturer::query()->createOrFirst(
            ['placeholder_type' => $type],
            [
                'name' => $arabicName,
                'name_en' => $englishName,
                'canonical_name' => $arabicName,
                'is_active' => true,
            ],
        );

        $lecturer->forceFill([
            'user_id' => null,
            'name' => $arabicName,
            'name_en' => $englishName,
            'canonical_name' => $arabicName,
            'placeholder_type' => $type,
            'is_active' => true,
        ])->save();

        return $lecturer->refresh();
    }
}
