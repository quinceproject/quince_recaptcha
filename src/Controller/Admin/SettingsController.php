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

use Quince\Engine\Controller\Admin\QuinceController;
use Quince\Recaptcha\Settings\RecaptchaSettings;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ustawienia reCAPTCHY.
 *
 * Dziedziczy po klasie z silnika, więc ekran dostaje ten sam sidebar, pasek
 * zapisu i wygląd co reszta panelu Quince — bez kopiowania czegokolwiek.
 */
class SettingsController extends QuinceController
{
    public function __construct(private readonly RecaptchaSettings $settings)
    {
    }

    public function indexAction(): Response
    {
        return $this->renderPanel('@Modules/quince_recaptcha/views/templates/admin/settings.html.twig', [
            'settings' => $this->settings->all(),
            'ready' => $this->settings->isReady(),
            'layoutTitle' => 'reCAPTCHA',
            'layoutTitleDomain' => 'Modules.Quincerecaptcha.Admin',
            'quinceRoute' => 'quince_recaptcha',
        ]);
    }

    public function saveAction(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('quince_recaptcha_save', $request->request->get('_csrf_token'))) {
            $this->addFlash('error', $this->trans('Invalid security token, please try again.', 'Modules.Quincerecaptcha.Admin'));

            return $this->redirectToRoute('quince_recaptcha');
        }

        $this->settings->save($request->request->all());
        $this->addFlash('success', $this->trans('Settings saved.', 'Modules.Quincerecaptcha.Admin'));

        return $this->redirectToRoute('quince_recaptcha');
    }
}
