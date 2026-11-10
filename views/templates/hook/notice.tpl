{**
 * Quince reCAPTCHA — zapis o ochronie przy formularzu.
 *
 * Treść i odnośniki według wymogu Google dla schowanego znaczka reCAPTCHA.
 * Nazwy polityk są osobnymi napisami, żeby tłumaczenie mogło ustawić je
 * w zdaniu w swojej kolejności.
 *
 * Wygląd: podpowiedź pod polem formularza motywu (`q-field-help`).
 *}

{capture name='qRecaptchaPrywatnosc'}<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">{l s='Privacy Policy' d='Modules.Quincerecaptcha.Shop'}</a>{/capture}
{capture name='qRecaptchaWarunki'}<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">{l s='Terms of Service' d='Modules.Quincerecaptcha.Shop'}</a>{/capture}

<p class="q-field-help q-form-notice">
  {l s='This site is protected by reCAPTCHA and the Google %privacy% and %terms% apply.'
    sprintf=['%privacy%' => $smarty.capture.qRecaptchaPrywatnosc, '%terms%' => $smarty.capture.qRecaptchaWarunki]
    d='Modules.Quincerecaptcha.Shop'}
</p>
