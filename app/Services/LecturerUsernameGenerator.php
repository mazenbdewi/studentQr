<?php

namespace App\Services;

use App\Models\Lecturer;
use App\Models\User;

class LecturerUsernameGenerator
{
    private const PREFERRED = [
        'ندى' => 'nada', 'امجد' => 'amjad', 'مازن' => 'mazen', 'محمد' => 'mohammad', 'محمود' => 'mahmoud',
        'يارا' => 'yara', 'نور' => 'nour', 'رائد' => 'raed', 'جمال' => 'jamal', 'جهاد' => 'jihad',
        'سوزان' => 'souzan', 'غياد' => 'ghiath', 'يوسف' => 'yousef', 'عبدالله' => 'abdullah', 'عبدالرحمن' => 'abdurrahman',
    ];

    public function proposal(Lecturer $lecturer): array
    {
        $name = $this->normalized((string) $lecturer->name);
        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = implode('', array_slice($words, 0, isset($words[1]) && in_array($words[0].$words[1], ['عبدالله', 'عبدالرحمن'], true) ? 2 : 1));
        $source = isset(self::PREFERRED[$first]) ? 'dictionary' : 'character fallback';
        $latin = self::PREFERRED[$first] ?? $this->transliterate($first);
        $latin = preg_replace('/[^a-z]/', '', strtolower($latin)) ?? '';
        $userId = (int) ($lecturer->user_id ?? 0);
        /** @var User|null $linkedUser */
        $linkedUser = $lecturer->user;
        $current = $linkedUser ? (string) $linkedUser->login_username : '';
        $suffix = preg_match('/^lec0*(\d+)$/', $current, $matches) ? (int) $matches[1] : null;

        return [
            'lecturer_id' => (int) $lecturer->id,
            'linked_user_id' => $userId ?: null,
            'proposed_username' => $latin === '' || ! $userId ? null : $latin.$userId,
            'transliteration_source' => $latin === '' ? 'manual' : $source,
            'current_numeric_suffix' => $suffix,
            'suffix_matches_user_id' => $suffix === null ? null : $suffix === $userId,
            'classification' => ! $userId ? 'missing_linked_user' : (($suffix !== null && $suffix !== $userId) ? 'expected_legacy_suffix_migration' : ($latin === '' || strlen($latin) < 2 ? 'invalid_output' : 'ready')),
            'requires_manual_review' => $latin === '' || strlen($latin) < 2,
            'review_reason' => $latin === '' || strlen($latin) < 2 ? 'invalid_output' : null,
            'duplicate' => $latin !== '' && $userId && User::withTrashed()->where('login_username', $latin.$userId)->whereKeyNot($lecturer->user_id)->exists(),
        ];
    }

    public function usernameFor(string $name, int $userId): ?string
    {
        $lecturer = new Lecturer(['name' => $name, 'user_id' => $userId]);
        $lecturer->setRelation('user', new User(['id' => $userId]));

        return $this->proposal($lecturer)['proposed_username'];
    }

    private function normalized(string $name): string
    {
        $name = preg_replace('/[ـ\p{Mn}\p{P}\p{S}]/u', '', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', str_replace(['أ', 'إ', 'آ'], 'ا', $name)) ?? $name);
    }

    private function transliterate(string $value): string
    {
        return strtr($value, ['ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'th', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ي' => 'y', 'ى' => 'a', 'ة' => 'a', 'ء' => '']);
    }
}
