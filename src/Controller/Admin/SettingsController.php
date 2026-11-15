<?php
/**
 * Quince
 *
 * @author    Quince Project <quinceproject@proton.me>
 * @copyright Since 2026 Marcin Gajewski
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Quince\Recaptcha\Controller\Admin;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Quince\Engine\Controller\Admin\QuinceController;
use Quince\Recaptcha\Settings\RecaptchaSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia reCAPTCHY.
 *
 * Dziedziczy po klasie z silnika, więc ekran dostaje ten sam sidebar, pasek
 * zapisu i wygląd co reszta panelu Quince.
 */
class SettingsController extends QuinceController
{
    public function __construct(private readonly RecaptchaSettings $settings)
    {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to view this.')]
    public function indexAction(): Response
    {
        $ustawienia = $this->settings->all();

        // Klucze tajne nie wracają do przeglądarki: formularz pokazuje tylko, że są zapisane.
        $sekrety = [
            'SECRET_KEY' => ($ustawienia['SECRET_KEY'] ?? '') !== '',
            'API_KEY' => ($ustawienia['API_KEY'] ?? '') !== '',
        ];
        $ustawienia['SECRET_KEY'] = '';
        $ustawienia['API_KEY'] = '';

        return $this->renderPanel('@Modules/quince_recaptcha/views/templates/admin/settings.html.twig', [
            'settings' => $ustawienia,
            'secretsSet' => $sekrety,
            'ready' => $this->settings->isReady(),
            'layoutTitle' => 'reCAPTCHA',
            'layoutTitleDomain' => 'Modules.Quincerecaptcha.Admin',
            'quinceRoute' => 'quince_recaptcha',
        ]);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'You do not have permission to change this.')]
    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_recaptcha_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quincerecaptcha.Admin'));

            return $this->redirectToRoute('quince_recaptcha');
        }

        $wartosci = $request->request->all();

        // Puste pole klucza tajnego znaczy „bez zmian": formularz nie zna zapisanej wartości.
        foreach (['SECRET_KEY', 'API_KEY'] as $klucz) {
            if (trim((string) ($wartosci[$klucz] ?? '')) === '') {
                unset($wartosci[$klucz]);
            }
        }

        $this->settings->save($wartosci);
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quincerecaptcha.Admin'));

        return $this->redirectToRoute('quince_recaptcha');
    }
}
