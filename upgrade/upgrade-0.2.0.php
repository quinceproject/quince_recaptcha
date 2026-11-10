<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 0.2.0 — zapis „chronione przez reCAPTCHA" przy chronionych formularzach.
 *
 * Moduł zakłada miejsce na zapis i się na nie zapisuje. Znaczek Google chowa
 * arkusz ładowany razem ze skryptem, więc tutaj nie ma nic więcej do zrobienia.
 *
 * @param Quince_Recaptcha $module
 */
function upgrade_module_0_2_0($module): bool
{
    return $module->zalozMiejsceZapisu()
        && $module->registerHook(\Quince\Engine\Form\FormGuard::HOOK_NOTICE);
}
