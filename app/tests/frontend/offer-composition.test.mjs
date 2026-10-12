import assert from 'node:assert/strict';
import { test } from 'node:test';
import fs from 'node:fs';
import ts from 'typescript';
const source = fs.readFileSync(
    new URL(
        '../../resources/js/components/marketing/offer/composition.ts',
        import.meta.url,
    ),
    'utf8',
);
const js = ts.transpileModule(source, {
    compilerOptions: {
        target: ts.ScriptTarget.ES2022,
        module: ts.ModuleKind.ES2022,
    },
}).outputText;
const { distinctOfferFaq, distinctOfferValue } = await import(
    'data:text/javascript;base64,' + Buffer.from(js).toString('base64')
);
const presentation = (value, hero = {}) => ({
    hero: {
        eyebrow: 'Oferta',
        headline: 'Café con brunch',
        description: 'Café de cortesía con brunch.',
        ...hero,
    },
    value,
});
test('omit repeated simple value; retain monetary value and distinct contractual fields', () => {
    assert.equal(
        distinctOfferValue(
            presentation({
                tone: 'offer',
                title: 'Café de cortesía con brunch',
            }),
        ),
        undefined,
    );
    for (const field of ['note', 'detail', 'description']) {
        const value = {
            tone: 'offer',
            title: 'Café de cortesía con brunch',
            [field]: 'Sólo con membresía activa',
        };
        assert.deepEqual(distinctOfferValue(presentation(value)), value);
    }
    const money = { tone: 'offer', title: 'BOB 20 de descuento desde BOB 160' };
    assert.deepEqual(distinctOfferValue(presentation(money)), money);
});
test('reward remains early; remove only a complete repeated supporting sentence', () => {
    const value = {
        tone: 'reward',
        title: 'Cena para dos. Premio simulado, no canjeable.',
        note: 'Revisión requerida',
    };
    const result = distinctOfferValue(
        presentation(value, {
            description: 'Premio simulado, no canjeable. Consulta las reglas.',
        }),
    );
    assert.equal(result.title, 'Cena para dos.');
    assert.equal(result.note, 'Revisión requerida');
    assert.equal(
        distinctOfferValue(presentation(value, { description: value.title }))
            .title,
        value.title,
    );
});
test('FAQ retains novel limits, negation, pricing, sequence and objections', () => {
    const answers = [
        'Membresía activa requerida.',
        'No se requiere membresía activa.',
        'Máximo 2 canjes.',
        'BOB 20 de descuento.',
        'Reserva antes de pagar.',
    ];
    const items = answers.map((answer) => ({ question: answer, answer }));
    assert.deepEqual(
        distinctOfferFaq(items, [
            'Membresía activa requerida.',
            'Máximo 1 canje.',
            'BOB 10 de descuento.',
            'Paga antes de reservar.',
        ]),
        items.slice(1),
    );
});
test('empty optional data never manufactures value or FAQ content', () => {
    assert.equal(distinctOfferValue(presentation(undefined)), undefined);
    assert.deepEqual(distinctOfferFaq([], []), []);
});
