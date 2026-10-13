import assert from 'node:assert/strict';
import { test } from 'node:test';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import ts from 'typescript';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
const require = createRequire(import.meta.url);
const root = path.resolve('resources/js');
let props = { auth: { user: { id: 1 } } };
const cache = new Map();
function load(file) {
    file = path.resolve(root, file);
    if (cache.has(file)) return cache.get(file);
    const js = ts.transpileModule(fs.readFileSync(file, 'utf8'), {
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
            esModuleInterop: true,
        },
    }).outputText;
    const module = { exports: {} };
    const localRequire = (name) => {
        if (name === 'lucide-react')
            return { Clock3: () => React.createElement('svg') };
        if (name === '@inertiajs/react')
            return {
                usePage: () => ({ props }),
                Link: ({ children, ...rest }) =>
                    React.createElement('a', rest, children),
                Head: () => null,
            };
        if (
            name.includes('LandingVideo') ||
            name.includes('jakawi-image') ||
            name.includes('marketing/primitives')
        )
            return {};
        if (name.startsWith('@/')) return load(name.slice(2) + '.tsx');
        return require(name);
    };
    new Function('require', 'module', 'exports', js)(
        localRequire,
        module,
        module.exports,
    );
    cache.set(file, module.exports);
    return module.exports;
}
const { Delivery, DeliveryLayout } = load('components/marketing/delivery.tsx');
const { MarketingValueBlock, MarketingPrices } = load(
    'components/marketing/offer/blocks.tsx',
);
const render = (Component, data, children) =>
    renderToStaticMarkup(React.createElement(Component, data, children));
test('linked brand and ecosystem follow fulfillment and account access', () => {
    props = { auth: { user: { id: 1 } }, newAcquisitionAccount: true };
    const html = render(DeliveryLayout, {}, 'QR-FULFILLMENT');
    assert.match(html, /href="\/" aria-label="Ir al inicio de JAKAWI"/);
    assert(html.indexOf('QR-FULFILLMENT') < html.indexOf('TU ACCESO JAKAWI'));
    assert(
        html.indexOf('TU ACCESO JAKAWI') <
            html.indexOf('SIGUE VIVIENDO TU CIUDAD'),
    );
    assert.match(html, /href="\/mi-jakawi"/);
    assert.match(html, /href="\/explorar"/);
    assert(
        !render(DeliveryLayout, { delivered: false }, 'ACTIVATION').includes(
            'SIGUE VIVIENDO',
        ),
    );
});
test('pending outcome has no completed cue or success surface', () => {
    const html = render(
        Delivery,
        { heading: 'Pendiente', subject: 'Experiencia' },
        'Espera confirmación',
    );
    assert.match(html, /data-delivery-outcome="pending"/);
    assert(!html.includes('bg-success'));
    assert.match(
        render(
            Delivery,
            { completed: true, heading: 'Confirmada', subject: 'Experiencia' },
            'Referencia',
        ),
        /data-delivery-outcome="completed"/,
    );
});
test('existing reward tone is yellow; informational offer stays neutral; truthful member price is green', () => {
    assert.match(
        render(MarketingValueBlock, {
            value: { tone: 'reward', label: 'Premio', title: 'Cena' },
        }),
        /bg-reward/,
    );
    const offer = render(MarketingValueBlock, {
        value: { tone: 'offer', label: 'Valor', title: 'Oferta' },
    });
    assert.match(offer, /data-value-tone="offer"/);
    assert(!offer.includes('bg-success'));
    assert(!offer.includes('bg-reward'));
    assert.match(
        render(MarketingPrices, {
            prices: [
                { label: 'Normal', formattedAmount: '120' },
                {
                    label: 'Miembro',
                    formattedAmount: '80',
                    memberAdvantage: true,
                },
            ],
        }),
        /bg-success/,
    );
});
test('Benefit instructions/address/code/validity/terms retain hierarchy', () => {
    props = { auth: { user: { id: 1 } } };
    const { default: Redemption } = load('pages/redemptions/show.tsx');
    const html = render(Redemption, {
        redemption: {
            status: 'pending',
            public_id: 'qa',
            code: '123456',
            partner_name: 'Partner',
            location_name: 'Sucursal',
            benefit_title: 'Café',
            address: 'Calle QA',
            terms: 'Condiciones QA',
            expires_at: '2099-01-01T12:00:00Z',
        },
    });
    for (const text of [
        'TU BENEFICIO ESTÁ LISTO',
        'CÓMO USARLO',
        'Calle QA',
        '123456',
        'Válido hasta',
        'Condiciones QA',
    ])
        assert(html.includes(text));
    assert(html.indexOf('CÓMO USARLO') < html.indexOf('123456'));
    assert(html.indexOf('123456') < html.indexOf('Condiciones QA'));
});
test('Challenge instructions precede verification; Unlock preserves authorized reveal guard', () => {
    const challenge = fs.readFileSync(
        path.join(root, 'pages/social-challenges/show.tsx'),
        'utf8',
    );
    assert(
        challenge.indexOf('<ChallengeDelivery ') <
            challenge.indexOf('aria-label="Seguridad de tu participación"'),
    );
    const unlock = fs.readFileSync(
        path.join(root, 'pages/unlocks/show.tsx'),
        'utf8',
    );
    assert.match(unlock, /unlock\.revealable && auth\.user && eligible/);
    assert.match(unlock, /<Delivery completed heading="DESBLOQUEADO"/);
});

test('Delivery does not render the floating PWA installation prompt; Challenge action follows state', () => {
    const app = fs.readFileSync(path.join(root, 'app.tsx'), 'utf8');
    assert.match(app, /page.component !== 'redemptions\/show'/);
    assert.match(app, /page.props.conversionStage === 'result'/);
    const challenge = fs.readFileSync(
        path.join(root, 'pages/social-challenges/challenge-delivery.tsx'),
        'utf8',
    );
    assert(
        challenge.indexOf('TU PARTICIPACIÓN') <
            challenge.indexOf('{primaryAction &&'),
    );
});
