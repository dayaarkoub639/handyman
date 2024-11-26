<?php

namespace App\Traits;

trait TranslationTrait
{
    /**
     * Generate an array of languages with name and flag path.
     *
     * @return array
     */
    public function getLanguageArray()
    {
        // Get language options from the session or set default languages
        $language_option = sitesetupSession('get')->language_option ?? ["nl", "fr", "it", "pt", "es", "en"];
       
        // Generate language array with title and flag path
        $language_array = [];
        foreach ($language_option as $lang_id) {
            $language_array[] = [
                'id' => $lang_id,
                'title' => strtoupper($lang_id),
                'flag_path' => file_exists(public_path('/images/flags/' . $lang_id . '.png'))
                    ? asset('/images/flags/' . $lang_id . '.png')
                    : asset('/images/language.png')
            ];
        }

        return $language_array;
    }
    public function saveTranslations(array $data, array $attributes, array $language_option, $primary_locale)
    {
        foreach ($language_option as $locale) {
            foreach ($attributes as $attribute) {
                // Skip the primary locale since it's stored in the main table
                if ($locale === $primary_locale) {
                    continue;
                }

                $value = $data["{$attribute}_{$locale}"] ?? null;

                // Save or update translation
                $this->translations()->updateOrCreate(
                    ['locale' => $locale, 'attribute' => $attribute],
                    ['value' => $value]
                );
            }
        }
    }
}
