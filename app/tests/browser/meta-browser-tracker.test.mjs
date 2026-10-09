import { test } from 'node:test';
import assert from 'node:assert/strict';
import { MetaBrowserTracker } from '../../resources/js/lib/meta-browser-tracker.ts';

const id = '11111111-1111-4111-8111-111111111111';
const view = { event_id: id, event_name: 'ViewContent', standard: true, custom_data: { content_category: 'benefit' } };
const context = { provider: 'META', pixel_id: '123456789', events: [view], cta: { name: 'JakawiLandingCTA', standard: false } };
const flush = () => new Promise((resolve) => setImmediate(resolve));

test('disabled/non-META traffic never loads the library', async () => {
    let loads = 0;
    const tracker = new MetaBrowserTracker(async () => { loads++; return () => {}; });
    for (const provider of ['NONE', 'TIKTOK', 'GOOGLE']) {
        tracker.configure({ ...context, provider }); tracker.emit(view);
    }
    tracker.configure(null); tracker.emit(view);
    await flush(); assert.equal(loads, 0);
});

test('double render emits once, uses canonical ID, disables automatic collection before init', async () => {
    const calls = []; let loads = 0;
    const tracker = new MetaBrowserTracker(async () => { loads++; return (...args) => calls.push(args); });
    tracker.configure(context); tracker.emit(view); tracker.emit(view);
    await flush();
    tracker.configure(context); tracker.emit(view);
    await flush();
    assert.equal(loads, 1);
    assert.deepEqual(calls, [
        ['set', 'autoConfig', false, context.pixel_id], ['init', context.pixel_id],
        ['trackSingle', context.pixel_id, 'ViewContent', view.custom_data, { eventID: id }],
    ]);
    assert.ok(!JSON.stringify(calls).includes('PageView'));
});

test('loaded library stops emitting after provider deactivation and switching', async () => {
    const calls = [];
    const tracker = new MetaBrowserTracker(async () => (...args) => calls.push(args));
    tracker.configure(context); tracker.emit(view); await flush();
    for (const provider of ['NONE', 'TIKTOK', 'GOOGLE']) {
        tracker.configure({ ...context, provider });
        tracker.cta('22222222-2222-4222-8222-222222222222', 'signup', 'hero');
        tracker.emit({ ...view, event_id: '33333333-3333-4333-8333-333333333333' });
    }
    await flush(); assert.equal(calls.length, 3);
});

test('provider change during load discards queued emissions', async () => {
    let ready; const calls = [];
    const tracker = new MetaBrowserTracker(() => new Promise((resolve) => { ready = resolve; }));
    tracker.configure(context); tracker.emit(view);
    tracker.configure(null); ready((...args) => calls.push(args));
    await flush(); assert.equal(calls.length, 0);
});

test('CTA receives the persisted UUID and centrally mapped custom name', async () => {
    const calls = [];
    const tracker = new MetaBrowserTracker(async () => (...args) => calls.push(args));
    tracker.configure(context); tracker.cta(id, 'signup', 'hero'); await flush();
    assert.deepEqual(calls.at(-1), ['trackSingleCustom', context.pixel_id, 'JakawiLandingCTA',
        { cta_kind: 'signup', cta_location: 'hero' }, { eventID: id }]);
});

test('load/delivery failure is best effort and invalid IDs never load', async () => {
    let loads = 0;
    const tracker = new MetaBrowserTracker(async () => { loads++; throw new Error('blocked'); });
    tracker.configure(context); tracker.emit({ ...view, event_id: 'invalid' });
    await flush(); assert.equal(loads, 0);
    assert.doesNotThrow(() => tracker.emit(view)); await flush(); assert.equal(loads, 1);
});

test('strict-mode remount during load emits only the latest canonical descriptor', async () => {
    let ready; const calls = [];
    const tracker = new MetaBrowserTracker(() => new Promise((resolve) => { ready = resolve; }));
    tracker.configure(context); tracker.emit(view);
    tracker.configure(context); tracker.emit(view);
    ready((...args) => calls.push(args)); await flush();
    assert.equal(calls.filter((args) => args[0] === 'trackSingle').length, 1);
});

test('synchronous adapter failures and non-contractual CTA data stay harmless', async () => {
    let loads = 0;
    const tracker = new MetaBrowserTracker(() => { loads++; throw new Error('unavailable'); });
    tracker.configure(context);
    tracker.cta(id, 'PRIVATE@example.test', 'hero');
    tracker.cta(id, 'signup', 'PRIVATE');
    assert.equal(loads, 0);
    assert.doesNotThrow(() => tracker.emit(view)); assert.equal(loads, 1);
});

test('loaded CTA emits during click capture before Inertia navigation clears the gate', async () => {
    const calls = [];
    const tracker = new MetaBrowserTracker(async () => (...args) => calls.push(args));
    tracker.configure(context); tracker.emit(view); await flush();
    const ctaId = '22222222-2222-4222-8222-222222222222';
    tracker.cta(ctaId, 'signup', 'hero');
    tracker.configure(null);
    assert.deepEqual(calls.at(-1), ['trackSingleCustom', context.pixel_id, 'JakawiLandingCTA',
        { cta_kind: 'signup', cta_location: 'hero' }, { eventID: ctaId }]);
});
