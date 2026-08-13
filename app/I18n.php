<?php

declare(strict_types=1);

const APP_LANGUAGES = ['fr', 'en', 'de', 'it', 'es'];

function normalize_language(?string $language): ?string
{
    if ($language === null) return null;
    $language = strtolower(substr(trim($language), 0, 2));
    return in_array($language, APP_LANGUAGES, true) ? $language : null;
}

function language_names(): array
{
    return ['fr'=>'Français','en'=>'English','de'=>'Deutsch','it'=>'Italiano','es'=>'Español'];
}

function set_login_language(?string $language): void
{
    $valid = normalize_language($language);
    if ($valid !== null) $_SESSION['login_language'] = $valid;
}

function detect_browser_language(): string
{
    foreach (explode(',', (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $candidate) {
        $valid = normalize_language(explode(';', $candidate, 2)[0]);
        if ($valid !== null) return $valid;
    }
    return 'fr';
}

function initialize_i18n(PDO $pdo): void
{
    if (isset($_GET['lang'])) set_login_language((string)$_GET['lang']);
    $language = normalize_language((string)($_SESSION['login_language'] ?? '')) ?? detect_browser_language();
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId > 0) {
        $statement = $pdo->prepare("SELECT language FROM users WHERE id=? AND account_status='active'");
        $statement->execute([$userId]);
        $preferred = normalize_language((string)($statement->fetchColumn() ?: ''));
        if ($preferred !== null) $language = $preferred;
    }
    $GLOBALS['app_language'] = $language;
}

function current_language(): string
{
    return normalize_language((string)($GLOBALS['app_language'] ?? '')) ?? 'fr';
}

function use_language(string $language): void
{
    $GLOBALS['app_language'] = normalize_language($language) ?? 'fr';
}

function translations_for(string $language): array
{
    static $translations = [];
    $language = normalize_language($language) ?? 'fr';
    if ($language === 'fr') return [];
    if (!isset($translations[$language])) {
        $file = __DIR__ . '/translations/' . $language . '.php';
        $catalogue = require __DIR__ . '/translations/common.php';
        $shared = [];
        foreach ($catalogue as $french => $values) {
            if (isset($values[$language])) $shared[$french] = $values[$language];
        }
        $translations[$language] = array_replace($shared, is_file($file) ? require $file : []);
    }
    return $translations[$language];
}

function t(string $french, array $parameters = [], ?string $language = null): string
{
    $language = normalize_language($language) ?? current_language();
    $translated = $language === 'fr' ? $french : (translations_for($language)[$french] ?? $french);
    foreach ($parameters as $name => $value) {
        $translated = str_replace(':' . $name, (string)$value, $translated);
    }
    return $translated;
}

function locale_code(): string
{
    return ['fr'=>'fr_CH','en'=>'en_GB','de'=>'de_CH','it'=>'it_CH','es'=>'es_ES'][current_language()];
}

function js_i18n(): array
{
    return [
        'pages' => t(':count page(s)'),
        'confirm_page' => t('Écraser cette page et remplacer tous ses blocs et tags ?'),
        'confirm_course' => t('Écraser ce parcours ? Ses anciennes étapes, progressions et encouragements liés seront supprimés.'),
        'confirm_students' => t('Écraser, pour les élèves importés, leurs inscriptions à vos parcours ?'),
        'teacher_identifier' => t('Identifiant'),
        'student_identifier' => t('Code élève'),
        'teacher_placeholder' => t('Ex. nora'),
        'student_placeholder' => t('Ex. LIROS'),
        'locked_by' => t('Modification en cours par :name'),
        'lock_active' => t('Zone réservée pour votre modification'),
        'lock_error' => t('Impossible de réserver cette zone'),
    ];
}
