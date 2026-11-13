/* ===================================================================
   Quince reCAPTCHA: dokładanie znacznika do wysyłanego formularza

   reCAPTCHA v3 nie pokazuje zagadki: ocenia zachowanie i zwraca znacznik
   ważny około dwóch minut. Dlatego nie pobieramy go przy wczytaniu strony,
   tylko w chwili wysyłki: znacznik pobrany wcześniej zdążyłby wygasnąć
   na dłużej wypełnianym formularzu.

   Chronimy formularze, które silnik oznaczył swoimi polami: obecność pola
   strażnika jest jedynym warunkiem. Dzięki temu moduł nie musi znać z nazwy
   żadnego formularza w sklepie.
   =================================================================== */

(function () {
    'use strict';

    var POLE = 'q_recaptcha';
    var STRAZNIK = '[name="q_guard"]';

    function klucz() {
        return window.quinceRecaptchaKey || '';
    }

    /** Nazwa formularza: ta sama, którą sklep sprawdzi po stronie serwera. */
    function akcja(formularz) {
        var pole = formularz.querySelector('[name="q_form"]');

        return pole && pole.value ? pole.value : 'form';
    }

    function wstawZnacznik(formularz, token) {
        var pole = formularz.querySelector('[name="' + POLE + '"]');

        if (!pole) {
            pole = document.createElement('input');
            pole.type = 'hidden';
            pole.name = POLE;
            formularz.appendChild(pole);
        }

        pole.value = token;
    }

    function podepnij(formularz) {
        if (formularz.dataset.qRecaptcha === '1') {
            return;
        }

        formularz.dataset.qRecaptcha = '1';

        formularz.addEventListener('submit', function (e) {
            // Znacznik już jest: to nasza własna, ponowiona wysyłka.
            var pole = formularz.querySelector('[name="' + POLE + '"]');

            if (pole && pole.value) {
                return;
            }

            if (!window.grecaptcha || !klucz()) {
                return;
            }

            e.preventDefault();

            window.grecaptcha.ready(function () {
                window.grecaptcha.execute(klucz(), { action: akcja(formularz) })
                    .then(function (token) {
                        wstawZnacznik(formularz, token);
                        // requestSubmit, a nie submit: przechodzi przez walidację
                        // przeglądarki i przez zdarzenia, których słucha motyw.
                        if (typeof formularz.requestSubmit === 'function') {
                            formularz.requestSubmit();
                        } else {
                            formularz.submit();
                        }
                    })
                    .catch(function () {
                        // Google nie odpowiedziało. Wysyłamy bez znacznika,
                        // sklep i tak sprawdzi pułapkę, czas i limit powtórzeń.
                        wstawZnacznik(formularz, '');
                        formularz.submit();
                    });
            });
        });
    }

    function szukaj(korzen) {
        (korzen || document).querySelectorAll(STRAZNIK).forEach(function (pole) {
            var formularz = pole.closest('form');

            if (formularz) {
                podepnij(formularz);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        szukaj(document);
    });

    // Formularz bywa wstawiany po wczytaniu strony: w szufladzie kontaktu,
    // w oknie zapytania o produkt. Wtedy trzeba go podpiąć ponownie.
    if (window.Quince && typeof window.Quince.register === 'function') {
        window.Quince.register('form:has([name="q_guard"])', podepnij);
    }

    /* Wysyłki w tle. Szuflady (pytanie o produkt, opinia, specjalista, oferta)
       nie wysyłają formularza, tylko składają dane same i nie ma zdarzenia
       `submit`, na którym działa `podepnij`. Motyw woła dostawców tuż przed
       taką wysyłką, więc znacznik pobieramy tutaj, też w ostatniej chwili.

       Limit czasu: gdy skrypt Google nie dojedzie (zablokowany, brak sieci),
       wysyłka nie może wisieć bez końca. Idzie bez znacznika, a sklep odpowie
       jak na każde zgłoszenie, którego nie przyjął. */
    var LIMIT_MS = 8000;

    if (window.Quince && typeof window.Quince.addFormProvider === 'function') {
        window.Quince.addFormProvider(function (dane) {
            if (!dane || !dane.has('q_guard') || !window.grecaptcha || !klucz()) {
                return;
            }

            var akcjaWysylki = dane.get('q_form') || 'form';

            return new Promise(function (gotowe) {
                var zegar = setTimeout(function () {
                    dane.set(POLE, '');
                    gotowe();
                }, LIMIT_MS);

                window.grecaptcha.ready(function () {
                    window.grecaptcha.execute(klucz(), { action: akcjaWysylki })
                        .then(function (token) {
                            clearTimeout(zegar);
                            dane.set(POLE, token);
                            gotowe();
                        }, function () {
                            clearTimeout(zegar);
                            dane.set(POLE, '');
                            gotowe();
                        });
                });
            });
        });
    }
})();
