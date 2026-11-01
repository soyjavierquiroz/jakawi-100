# Fase 11I — Acceso a beneficios

Cambio limitado a COM sobre Fase 11G. No se modifica el contrato de emisión de CLUB ni la autenticación existente.

- Primera entrada: una pantalla, un clic en «Entrar a mis beneficios» y destino fijo `/beneficios`. La preparación se hace en segundo plano, sin consumir el ticket ni autenticar.
- Sesión del mismo titular: cero confirmaciones y cero clics. Se vuelve a validar ticket, compra, identidad autorizada y membresía, y se conserva la sesión existente. El ticket no se usa para autenticar ni se consume en esta ruta.
- Otra cuenta: una pantalla que indica «Estás usando otra cuenta JAKAWI.». «Cambiar cuenta y entrar» confirma explícitamente el cambio; cancelar conserva la cuenta y vuelve a CLUB.
- Branding: logo oficial `/jakawi-logo.svg`, Montserrat local, CSS de COM, tokens `--brand`, `--background`, `--brand-foreground`, `--radius-control` y contrastes existentes. Sin analytics ni recursos externos. La política de formularios permite únicamente COM y el retorno oficial a `https://jakawi.club`, para que cancelar funcione también en navegador. Anchos comprobados: 390/1440; sin desbordamiento ni scroll innecesario.
- Seguridad: ticket de 90 s, hash en servidor, consumo atómico único, revalidación al consumir, CSRF, invalidación y regeneración de sesión conservados. GET, prefetch y preparación no autentican. Se borra el fragmento antes de solicitar assets. Cambiar el fragmento en la misma pestaña reinicia la preparación. Doble clic bloqueado; refresh reutiliza solo el hash temporal en sesión.
- Errores: acceso vencido/repetido y CSRF vencido con mensaje simple; conflicto con retorno seguro a CLUB. Sin códigos de backend.
- Validación funcional: 31 pruebas y 354 comprobaciones de acceso/compras en PostgreSQL aislado `jakawi_test`. Pruebas de navegador con datos ficticios; ningún fixture ni ticket de prueba en LIVE. Caché Blade y rutas verificadas.
- Despliegue: solo los cuatro servicios de COM (app/web/worker/scheduler), sin migraciones. CLUB, CRM, PostgreSQL y proxy conservan sus contenedores. El SHA desplegado y la verificación final se entregan en `LIVE.json` junto con las evidencias de navegador.
