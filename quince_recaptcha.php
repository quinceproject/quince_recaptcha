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

require_once __DIR__ . '/vendor/autoload.php';

use Quince\Recaptcha\Settings\RecaptchaSettings;
use Quince\Recaptcha\Settings\TokenCheck;

/**
 * Quince reCAPTCHA — dostawca oceny zgłoszeń dla formularzy sklepu.
 *
 * Moduł nie zna żadnego formularza z nazwy i nie wtrąca się do żadnego z nich.
 * Zgłasza się silnikowi na hook `actionQuinceFormVerify` i dostaje ostatnie
 * słowo po zaporach, które silnik ma u siebie — pułapce, czasie wypełnienia
 * i limicie powtórzeń.
 *
 * Konsekwencja tego podziału: bez tego modułu formularze działają i są
 * zabezpieczone, a wymiana reCAPTCHY na innego dostawcę to podmiana jednego
 * modułu, a nie objazd po wszystkich formularzach w sklepie.
 */
class Quince_Recaptcha extends Module
{
    public function __construct()
    {
        $this->name = 'quince_recaptcha';
        $this->tab = 'front_office_features';
        $this->version = '0.2.0';
        $this->author = 'Quince Project';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => '9.99.99'];
        $this->bootstrap = true;

        // Ekrany modułu dziedziczą po kontrolerze silnika, a szablony po jego
        // layoucie. Bez tej deklaracji PrestaShop pozwoli wyłączyć silnik,
        // a moduł przestanie otwierać swoje ekrany bez żadnego ostrzeżenia.
        $this->dependencies = ['quince_engine'];

        // Jeden wpis w gałęzi „Quince project" w menu PrestaShopa, prowadzący
        // na ekran modułu. Podstron nie zgłaszamy: moduł ma je we własnym
        // bocznym pasku, a menu sklepu urosłoby wtedy o kilkanaście pozycji.
        // Kolejność wpisów w gałęzi ustala silnik.
        $this->tabs = [
            [
                'class_name' => 'AdminQuinceRecaptcha',
                'visible' => true,
                'name' => 'reCAPTCHA',
                'parent_class_name' => 'AdminQuinceProject',
                'route_name' => 'quince_recaptcha',
                'wording' => 'reCAPTCHA',
                'wording_domain' => 'Modules.Quincerecaptcha.Admin',
            ],
        ];

        parent::__construct();

        $this->displayName = $this->trans('Quince reCAPTCHA', [], 'Modules.Quincerecaptcha.Admin');
        $this->description = $this->trans(
            'Google reCAPTCHA on shop forms, on top of what the engine checks by itself.',
            [],
            'Modules.Quincerecaptcha.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Uninstall the module? Forms keep working, without the Google check.',
            [],
            'Modules.Quincerecaptcha.Admin'
        );
    }

    public function install(): bool
    {
        (new RecaptchaSettings())->installDefaults();

        return parent::install()
            && $this->registerHook('actionQuinceRegisterFeature')
            && $this->registerHook('actionQuinceFormVerify')
            && $this->registerHook('actionFrontControllerSetMedia')
            && $this->zalozMiejsceZapisu()
            && $this->registerHook(\Quince\Engine\Form\FormGuard::HOOK_NOTICE);
    }

    /**
     * Miejsce na zapis o ochronie przy chronionych formularzach.
     *
     * `Hook::add()` przed rejestracją: PrestaShop po cichu pomija rejestrację
     * na hook, którego nie ma jeszcze w tabeli.
     */
    public function zalozMiejsceZapisu(): bool
    {
        $nazwa = \Quince\Engine\Form\FormGuard::HOOK_NOTICE;

        if (Hook::getIdByName($nazwa)) {
            return true;
        }

        $hook = new Hook();
        $hook->name = $nazwa;
        $hook->title = 'Quince — zapis dostawcy oceny przy formularzu';
        $hook->description = 'Pod polami strażnika w każdym chronionym formularzu. Parametr form_id to nazwa formularza.';
        $hook->position = false;

        return (bool) $hook->add();
    }

    /**
     * Zapis o ochronie przy formularzu.
     *
     * Regulamin reCAPTCHA pozwala schować znaczek Google z rogu strony tylko
     * wtedy, gdy w drodze klienta widać zapis „chronione przez reCAPTCHA"
     * z odnośnikami do polityki prywatności i warunków Google. Stawiamy go przy
     * każdym chronionym formularzu i tylko wtedy, gdy ocena naprawdę działa:
     * przy wyłączonym module zapis byłby nieprawdą.
     */
    public function hookDisplayQuinceFormNotice(): string
    {
        if (!(new RecaptchaSettings())->isReady()) {
            return '';
        }

        return $this->fetch('module:quince_recaptcha/views/templates/hook/notice.tpl');
    }

    public function uninstall(): bool
    {
        (new RecaptchaSettings())->removeAll();

        return parent::uninstall();
    }

    /**
     * Zgłoszenie do silnika: co dostarczamy i gdzie tego szukać.
     *
     * @return array<int,array{type:string,id:string,label:string,route:string}>
     */
    public function hookActionQuinceRegisterFeature(): array
    {
        return [[
            'type' => 'captcha',
            'id' => 'quince_recaptcha',
            'label' => $this->trans('reCAPTCHA', [], 'Modules.Quincerecaptcha.Admin'),
            'route' => 'quince_recaptcha',
        ]];
    }

    /**
     * Ocena zgłoszenia formularza.
     *
     * Zwracamy `null`, gdy moduł nie jest gotowy albo Google nie odpowiedziało —
     * silnik traktuje to jako brak zdania i przepuszcza zgłoszenie dalej.
     *
     * @param array{formId?:string,values?:array<string,mixed>} $params
     */
    public function hookActionQuinceFormVerify(array $params): ?bool
    {
        $ustawienia = new RecaptchaSettings();

        if (!$ustawienia->isReady()) {
            return null;
        }

        $wartosci = is_array($params['values'] ?? null) ? $params['values'] : [];
        $token = (string) ($wartosci['q_recaptcha'] ?? '');
        $formularz = (string) ($params['formId'] ?? '');

        return (new TokenCheck($ustawienia))->verify($token, $formularz);
    }

    /**
     * Skrypt Google i nasza obsługa — tylko wtedy, gdy moduł jest skonfigurowany.
     *
     * Ładujemy je na każdej stronie sklepu, bo formularz bywa wszędzie: w szufladzie
     * kontaktu, w stopce, na karcie produktu. Skrypt Google waży kilkanaście
     * kilobajtów i jest wczytywany z odroczeniem, więc nie opóźnia rysowania strony.
     */
    public function hookActionFrontControllerSetMedia(): void
    {
        $ustawienia = new RecaptchaSettings();

        if (!$ustawienia->isReady()) {
            return;
        }

        $klucz = $ustawienia->siteKey();

        $this->context->controller->registerJavascript(
            'quince-recaptcha-api',
            'https://www.google.com/recaptcha/api.js?render=' . urlencode($klucz),
            ['server' => 'remote', 'position' => 'bottom', 'priority' => 90, 'attributes' => 'defer']
        );

        Media::addJsDef(['quinceRecaptchaKey' => $klucz]);

        $this->context->controller->registerJavascript(
            'quince-recaptcha',
            'modules/' . $this->name . '/views/js/recaptcha.js',
            ['position' => 'bottom', 'priority' => 100]
        );

        // Znaczek Google w rogu strony chowamy, bo przy każdym chronionym
        // formularzu stoi zapis o ochronie (hookDisplayQuinceFormNotice).
        // Jedno bez drugiego łamie regulamin reCAPTCHA.
        $this->context->controller->registerStylesheet(
            'quince-recaptcha',
            'modules/' . $this->name . '/views/css/recaptcha.css',
            ['media' => 'all', 'priority' => 150]
        );
    }

    /**
     * Przycisk „Konfiguruj" w menedżerze modułów.
     *
     * PrestaShop pokazuje go po samym istnieniu tej metody
     * (`ModuleRepository::__construct`, `method_exists($tmpModule, 'getContent')`),
     * a my przenosimy sprzedawcę na ekran modułu w panelu.
     */
    public function getContent(): void
    {
        \Tools::redirectAdmin($this->get('router')->generate('quince_recaptcha'));
    }

}
