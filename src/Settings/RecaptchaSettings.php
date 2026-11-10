<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Recaptcha\Settings;

use Configuration;

/**
 * Ustawienia reCAPTCHA.
 *
 * Moduł obsługuje dwa warianty Google: klasyczny reCAPTCHA v3 (klucz witryny +
 * klucz tajny) i wariant Enterprise (klucz witryny + klucz API + identyfikator
 * projektu). Różnią się tylko sposobem odpytania — ocena wraca w obu tak samo,
 * jako liczba od 0 do 1.
 *
 * Próg oceny zostaje w ustawieniach, bo zależy od sklepu: sklep zasypywany
 * spamem podnosi go i godzi się na odrzucenie części ludzi, sklep spokojny
 * obniża i przepuszcza wszystko poza oczywistymi maszynami.
 */
final class RecaptchaSettings
{
    private const PREFIX = 'QUINCE_RECAPTCHA_';

    public const MODE_STANDARD = 'standard';
    public const MODE_ENTERPRISE = 'enterprise';

    /** @var array<string,string> klucz => wartość domyślna */
    private const SCHEMA = [
        'ENABLED' => '0',
        'MODE' => self::MODE_STANDARD,

        'SITE_KEY' => '',
        'SECRET_KEY' => '',

        // Enterprise pyta inaczej: klucz API konta Google Cloud i projekt, w którym
        // klucz witryny został założony.
        'API_KEY' => '',
        'PROJECT_ID' => '',

        'THRESHOLD' => '0.5',
    ];

    /** @return array<string,string> */
    public function all(): array
    {
        $out = [];

        foreach (self::SCHEMA as $key => $default) {
            $value = Configuration::get(self::PREFIX . $key);
            $out[$key] = ($value === false || $value === null || $value === '') ? $default : (string) $value;
        }

        return $out;
    }

    /**
     * Czy moduł ma się w cokolwiek wtrącać.
     *
     * Włączony, ale bez kluczy, to stan przejściowy — sprzedawca zapisał ekran,
     * zanim wkleił klucze z konsoli Google. Wtedy milczymy zamiast odrzucać
     * wszystkie zgłoszenia w sklepie.
     */
    public function isReady(): bool
    {
        $s = $this->all();

        if ($s['ENABLED'] !== '1' || $s['SITE_KEY'] === '') {
            return false;
        }

        return $s['MODE'] === self::MODE_ENTERPRISE
            ? ($s['API_KEY'] !== '' && $s['PROJECT_ID'] !== '')
            : $s['SECRET_KEY'] !== '';
    }

    public function siteKey(): string
    {
        return $this->all()['SITE_KEY'];
    }

    public function threshold(): float
    {
        $prog = (float) $this->all()['THRESHOLD'];

        // Próg poza zakresem oceny przepuszczałby wszystko albo nic — jedno i
        // drugie to nie jest ustawienie, tylko awaria.
        return max(0.0, min(1.0, $prog));
    }

    /** @param array<string,mixed> $values */
    public function save(array $values): void
    {
        foreach (array_keys(self::SCHEMA) as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }

            Configuration::updateValue(self::PREFIX . $key, trim((string) $values[$key]));
        }

        // Przełącznik nie wysyła nic, gdy jest wyłączony.
        Configuration::updateValue(self::PREFIX . 'ENABLED', empty($values['ENABLED']) ? '0' : '1');
    }

    public function removeAll(): void
    {
        foreach (array_keys(self::SCHEMA) as $key) {
            Configuration::deleteByName(self::PREFIX . $key);
        }
    }

    public function installDefaults(): void
    {
        foreach (self::SCHEMA as $key => $default) {
            if (Configuration::get(self::PREFIX . $key) === false) {
                Configuration::updateValue(self::PREFIX . $key, $default);
            }
        }
    }
}
