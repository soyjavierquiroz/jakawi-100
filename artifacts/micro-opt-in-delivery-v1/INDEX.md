# JAKAWI Micro opt-in + Delivery V1

Local/test only. Producción permanece en `e848b9f`; sin deploy ni cambios a los tres demos publicados.

- Modal único: nombre → correo → WhatsApp → sesión existente → requisitos / acción explícita → Delivery.
- Contraseña permanente diferida; reset seguro por cola no bloqueante. Identidades existentes requieren login/recuperación.
- Beneficio reutiliza código/QR reales y agrega dirección/condiciones. Experiencia conserva estado pending/confirmed. Reto entrega instrucciones, no el premio. Unlock conserva guards de source y añade elegibilidad autenticada y participación vigente a disclosure.
- Welcome Courtesy: FUTURE / OFF / NOT IMPLEMENTED en [Roadmap](../../docs/ROADMAP.md#welcome-courtesy--cortesía-jakawi).

[Validación](validation.json): 12 escenarios / 270 assertions, TypeScript, docs, diff y un único build frontend final PASS; 101 páginas en manifest. [Backend](backend-validation.txt), [build](frontend-build.txt), [browser](browser-validation.json).

390 px: Café completo hasta ubicación, Sunset smoke, Reto Cena con verificación contextual. Una revisión desktop a 1440 px. Capturas: [Café / ubicación](cafe-location-390.png), [desktop](cafe-name-1440.png), [Benefit Delivery](benefit-delivery-390.png), [Experience Delivery](experience-delivery-390.png), [Challenge Delivery](challenge-delivery-390.png).

[Contadores después de browser](test-domain-counts.json): un canje, una reserva pending, una participación y una membresía, exclusivamente fixtures de Delivery en `jakawi_test`. El browser no ejecutó ninguna mutación de dominio; las tres adquisiciones sólo crearon sesiones/cuentas aisladas. Pruebas de seguridad Unlock automatizadas, incluido compromiso explícito y source negado a guest/no participante/no elegible/cancelado. La última ejecución focalizada restableció la DB de tests; fixtures no persistidos al terminar. No suite amplia ni mutaciones productivas.
