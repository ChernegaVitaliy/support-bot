<?php

namespace App\Services;

use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class Translator implements TranslatorInterface, LocaleAwareInterface
{
    private array $languages = [];
    private Logger $logger;
    private string $languagesPath;
    private string $defaultLanguage = 'uk';

    private array $supportedLanguages = [
        'uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'zh_TW', 'ja',
        'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro',
        'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el',
        'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'
    ];

    private array $languageAliases = [
        'zh-tw' => 'zh_TW',
        'zh-hant' => 'zh_TW',
        'zh-hk' => 'zh_TW',
        'zh-cn' => 'zh',
        'zh-hans' => 'zh',
    ];

    public function __construct(string $languagesPath, Logger $logger)
    {
        $this->languagesPath = $languagesPath;
        $this->logger = $logger;
        $this->loadLanguages();
    }

    private function loadLanguages(): void
    {
        foreach ($this->supportedLanguages as $lang) {
            $filePath = $this->languagesPath . "/{$lang}.json";

            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $this->languages[$lang] = json_decode($content, true) ?? [];
            } else {
                $this->logger->warning("Файл мови не знайдено: $filePath");
            }
        }

        $this->logger->info("Завантажено перекладів для " . count($this->languages) . " мов");
    }

    public function translate(string $key, string $lang = 'uk', array $params = []): string
    {
        // 1. Try to find key in selected language
        $translation = $this->getNestedTranslation($this->languages[$lang] ?? [], $key);

        // 2. If not found, try default language
        if ($translation === null && $lang !== $this->defaultLanguage) {
            $translation = $this->getNestedTranslation($this->languages[$this->defaultLanguage] ?? [], $key);
        }

        // 3. If still not found, return key
        $translation = $translation ?? $key;

        if (!empty($params)) {
            // Check if translation has placeholders before vsprintf to avoid errors
            if (strpos($translation, '%') !== false) {
                $translation = vsprintf($translation, $params);
            }
        }

        return $translation;
    }

    private function getNestedTranslation(array $data, string $key): ?string
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $parts = explode('.', $key);
        $current = $data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return null;
            }
            $current = $current[$part];
        }

        return is_string($current) ? $current : null;
    }

    public function detectLanguage(array $from, ?DatabaseService $db = null, ?TelegramService $telegramService = null): string
    {
        $userId = (string)($from['id'] ?? '');

        if ($userId) {
            $currentLanguage = $db ? $db->getUserLanguage($userId) : null;
            $detectedLanguage = $this->autoDetectLanguage($from, $telegramService);

            if ($currentLanguage !== $detectedLanguage && $db) {
                $db->updateUserLanguage($userId, $detectedLanguage);
                $this->logger->info("🔄 Мова оновлена для $userId: $currentLanguage -> $detectedLanguage");
            }

            return $detectedLanguage;
        }

        return $this->defaultLanguage;
    }

    private function autoDetectLanguage(array $from, ?TelegramService $telegramService = null): string
    {
        if (isset($from['id'])) {
            $apiLang = $telegramService ? $telegramService->getUserLanguageFromApi((string)$from['id']) : null;

            if ($apiLang) {
                $this->logger->debug("Мова з API: " . $apiLang);

                $alias = $this->resolveLanguageAlias($apiLang);
                if ($alias) {
                    return $alias;
                }

                foreach ($this->supportedLanguages as $lang) {
                    if (strpos($apiLang, $lang) === 0) {
                        return $lang;
                    }
                }
            }
        }

        if (isset($from['language_code'])) {
            $langCode = strtolower($from['language_code']);
            $this->logger->debug("Мова з повідомлення: " . $langCode);

            $alias = $this->resolveLanguageAlias($langCode);
            if ($alias) {
                return $alias;
            }

            foreach ($this->supportedLanguages as $lang) {
                if (strpos($langCode, $lang) === 0) {
                    return $lang;
                }
            }
        }

        $this->logger->debug("Мова не визначена, використовується за замовчуванням");
        return $this->defaultLanguage;
    }

    private function resolveLanguageAlias(string $code): ?string
    {
        $normalized = strtolower(str_replace('_', '-', $code));
        $normalized = preg_replace('/^([a-z]{2}).*$/', '$1', $normalized);

        if (isset($this->languageAliases[$normalized])) {
            return $this->languageAliases[$normalized];
        }

        // Try matching the full code against aliases (e.g. zh-tw)
        $full = strtolower(str_replace('_', '-', $code));
        if (isset($this->languageAliases[$full])) {
            return $this->languageAliases[$full];
        }

        return null;
    }

    public function hasLanguage(string $lang): bool
    {
        return isset($this->languages[$lang]);
    }

    public function getSupportedLanguages(): array
    {
        return $this->supportedLanguages;
    }

    public function getLoadedLanguages(): array
    {
        return array_keys($this->languages);
    }

    public function getDefaultLanguage(): string
    {
        return $this->defaultLanguage;
    }

    public function setDefaultLanguage(string $lang): void
    {
        if (in_array($lang, $this->supportedLanguages)) {
            $this->defaultLanguage = $lang;
        }
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translate($id, $locale ?? $this->defaultLanguage, $parameters);
    }

    public function getLocale(): string
    {
        return $this->defaultLanguage;
    }

    public function setLocale(string $locale): void
    {
        $this->setDefaultLanguage($locale);
    }
}
