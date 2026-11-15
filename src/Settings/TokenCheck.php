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

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Odpytanie Google o ocenę znacznika.
 *
 * Zwraca `true` (przepuszczam), `false` (odrzucam) albo `null` (nie mam zdania).
 * Trzecia odpowiedź jest tu najważniejsza: gdy Google nie odpowiada albo moduł
 * nie jest skonfigurowany, sklep ma dalej przyjmować zamówienia i zapytania.
 * Zapora, która przy własnej awarii zamyka sklep, jest gorsza niż jej brak,
 * a zgłoszenie i tak przeszło już przez pułapkę, czas wypełnienia i limit
 * powtórzeń po stronie silnika.
 */
final class TokenCheck
{
    private const STANDARD_URL = 'https://www.google.com/recaptcha/api/siteverify';
    private const ENTERPRISE_URL = 'https://recaptchaenterprise.googleapis.com/v1/projects/';

    /** Po tylu sekundach przestajemy czekać na Google. */
    private const TIMEOUT = 5;

    public function __construct(private readonly RecaptchaSettings $settings)
    {
    }

    /**
     * @param string $token  znacznik przysłany przez przeglądarkę
     * @param string $action nazwa formularza zadeklarowana przy pobraniu znacznika
     */
    public function verify(string $token, string $action = ''): ?bool
    {
        if (!$this->settings->isReady()) {
            return null;
        }

        if (trim($token) === '') {
            return false;
        }

        $s = $this->settings->all();

        $odpowiedz = $s['MODE'] === RecaptchaSettings::MODE_ENTERPRISE
            ? $this->askEnterprise($token, $action, $s)
            : $this->askStandard($token, $s);

        // Celowo przepuszczamy (fail open): awaria albo brak łączności z Google
        // nie może odciąć sklepu od zamówień. Patrz opis klasy.
        if ($odpowiedz === null) {
            return null;
        }

        [$poprawny, $akcja, $ocena, $host] = $odpowiedz;

        if (!$poprawny) {
            return false;
        }

        // Znacznik rozwiązany na obcej stronie z tym samym kluczem witryny
        // nie jest dowodem, że to klient tego sklepu.
        if ($host !== null && $host !== '' && !in_array(strtolower($host), $this->shopHosts(), true)) {
            return false;
        }

        // Znacznik pobrany dla innego formularza znaczy, że ktoś przenosi go
        // między formularzami. Legalna wysyłka zawsze pasuje do swojej akcji.
        if ($action !== '' && $akcja !== null && $akcja !== $action) {
            return false;
        }

        return $ocena >= $this->settings->threshold();
    }

    /**
     * @param array<string,string> $s
     *
     * @return array{0:bool,1:?string,2:float,3:?string}|null
     */
    private function askStandard(string $token, array $s): ?array
    {
        $dane = $this->post(self::STANDARD_URL, http_build_query([
            'secret' => $s['SECRET_KEY'],
            'response' => $token,
        ]), 'application/x-www-form-urlencoded');

        if ($dane === null) {
            return null;
        }

        return [
            (bool) ($dane['success'] ?? false),
            isset($dane['action']) ? (string) $dane['action'] : null,
            // Brak oceny to brak dowodu, a nie pełne zaufanie.
            (float) ($dane['score'] ?? 0.0),
            isset($dane['hostname']) ? (string) $dane['hostname'] : null,
        ];
    }

    /**
     * @param array<string,string> $s
     *
     * @return array{0:bool,1:?string,2:float,3:?string}|null
     */
    private function askEnterprise(string $token, string $action, array $s): ?array
    {
        $url = self::ENTERPRISE_URL . rawurlencode($s['PROJECT_ID'])
            . '/assessments?key=' . rawurlencode($s['API_KEY']);

        $zdarzenie = ['token' => $token, 'siteKey' => $s['SITE_KEY']];

        if ($action !== '') {
            $zdarzenie['expectedAction'] = $action;
        }

        $dane = $this->post($url, (string) json_encode(['event' => $zdarzenie]), 'application/json');

        if ($dane === null) {
            return null;
        }

        $wlasciwosci = $dane['tokenProperties'] ?? [];

        return [
            (bool) ($wlasciwosci['valid'] ?? false),
            isset($wlasciwosci['action']) ? (string) $wlasciwosci['action'] : null,
            (float) ($dane['riskAnalysis']['score'] ?? 0.0),
            isset($wlasciwosci['hostname']) ? (string) $wlasciwosci['hostname'] : null,
        ];
    }

    /**
     * Domeny bieżącego sklepu, także dodatkowe adresy z „Adresy URL sklepu".
     *
     * @return string[] małymi literami, bez portu
     */
    private function shopHosts(): array
    {
        $domeny = [\Tools::getShopDomain(false), \Tools::getShopDomainSsl(false)];
        $idShop = (int) \Context::getContext()->shop->id;

        if ($idShop > 0) {
            $wiersze = \Db::getInstance()->executeS(
                'SELECT `domain`, `domain_ssl` FROM `' . _DB_PREFIX_ . 'shop_url` WHERE `active` = 1 AND `id_shop` = ' . $idShop
            ) ?: [];

            foreach ($wiersze as $wiersz) {
                $domeny[] = (string) $wiersz['domain'];
                $domeny[] = (string) $wiersz['domain_ssl'];
            }
        }

        $hosty = [];

        foreach ($domeny as $domena) {
            $host = strtolower(trim(explode(':', (string) $domena)[0]));

            if ($host !== '') {
                $hosty[] = $host;
            }
        }

        return array_values(array_unique($hosty));
    }

    /**
     * @return array<string,mixed>|null null, gdy nie udało się zapytać lub zrozumieć odpowiedzi
     */
    private function post(string $url, string $body, string $contentType): ?array
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
            CURLOPT_HTTPHEADER => ['Content-Type: ' . $contentType],
        ]);

        $odpowiedz = curl_exec($ch);
        curl_close($ch);

        if (!is_string($odpowiedz)) {
            return null;
        }

        $dane = json_decode($odpowiedz, true);

        return is_array($dane) ? $dane : null;
    }
}
