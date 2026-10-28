import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import ts from 'typescript';
import { renderToStaticMarkup } from 'react-dom/server';
import { createElement } from 'react';

async function render(source, stubHead = false, banner = true) {
    // This static test renders Coming Soon without its optional community prop.
    source = source.replace("import { CityCommunityCard, type CityCommunityData } from '@/components/city-community-card';", 'const CityCommunityCard = () => null;');
    source = source.replace("import { usePage } from '@inertiajs/react';", `const usePage = () => ({props: {showDemoBanner: ${banner}}});`);
    if (stubHead) source = source.replace("import { Head } from '@inertiajs/react';", 'const Head = ({children}) => <>{children}</>;');
    let code = ts.transpileModule(source, { compilerOptions: { jsx: ts.JsxEmit.ReactJSX, module: ts.ModuleKind.ESNext } }).outputText;
    code = code.replaceAll('"react/jsx-runtime"', JSON.stringify(import.meta.resolve('react/jsx-runtime')));
    const { default: Component } = await import('data:text/javascript;base64,' + Buffer.from(code).toString('base64'));
    return renderToStaticMarkup(createElement(Component));
}

test('Coming Soon renders exact copy with no product links, inventory, theme toggle or app navigation', async () => {
    const html = await render(fs.readFileSync('resources/js/pages/coming-soon.tsx', 'utf8'), true);
    for (const text of ['COCHABAMBA · PRÓXIMAMENTE', 'TU CIUDAD ESTÁ A PUNTO DE', 'DARTE MÁS.', 'VIVE MÁS. GASTA MENOS.', 'LO QUE VIENE', 'Ventajas que sí querrás usar.', 'Nuevos planes para salir de la rutina.', 'Más razones para vivir tu ciudad.', 'ESTAMOS PREPARANDO LA PRIMERA SELECCIÓN JAKAWI PARA COCHABAMBA.']) assert(html.includes(text), text);
    assert(!/<nav|<a |<form|data-prelaunch-notice/.test(html));
    assert(html.includes('name="robots" content="index,follow"'));
    assert(html.includes('href="https://jakawi.com/"'));
});

test('Notice renders commercially truthful, globally enabled in-flow sticky message without controls', async () => {
    const html = await render(fs.readFileSync('resources/js/components/prelaunch-notice.tsx', 'utf8'));
    assert(html.includes('MODO PRE-LANZAMIENTO'));
    assert(html.includes('JAKAWI está en modo DEMO. Las experiencias, beneficios, retos o premios son ilustrativos y no corresponden a items reales.'));
    assert(!html.includes('Experiencia ilustrativa'));
    assert(!html.includes('no están disponibles comercialmente'));
    assert.equal((html.match(/data-prelaunch-notice/g) ?? []).length, 1);
    assert(html.includes('sticky top-0 z-40'));
    assert(!/<button|<a |fixed|ficticio/.test(html));
});

test('Disabled global banner emits no markup or reserved space', async () => {
    assert.equal(await render(fs.readFileSync('resources/js/components/prelaunch-notice.tsx', 'utf8'), false, false), '');
});
