import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
const { vslProgress, verticalFraming, resolveFraming } = await import(
    pathToFileURL(process.env.VSL_PRESENTATION_MODULE).href
);
const close = (actual, expected) =>
    assert.ok(Math.abs(actual - expected) < 0.001, `${actual} != ${expected}`);
test('configured vertical and horizontal ratios select layout before loading', () => {
    assert.equal(
        resolveFraming('mp4', { kind: 'auto', sourceAspectRatio: 9 / 16 })
            .viewportAspectRatio,
        3 / 4,
    );
    assert.equal(
        resolveFraming('mp4', { kind: 'auto', sourceAspectRatio: 16 / 9 })
            .viewportAspectRatio,
        16 / 9,
    );
    assert.equal(resolveFraming('youtube').kind, 'horizontal');
    assert.equal(resolveFraming('development-fixture').kind, 'native-vertical');
});
test('full vertical presentation preserves the entire native or embedded content height', () => {
    for (const type of ['mp4', 'bunny', 'youtube']) {
        for (const kind of ['native-vertical', 'embedded-vertical']) {
            const frame = resolveFraming(type, { kind, viewportAspectRatio: 9 / 16 });
            assert.equal(frame.viewportAspectRatio, 9 / 16);
            close(verticalFraming(frame).heightPercent, 100);
            close(verticalFraming(frame).topPercent, 0);
        }
    }
});
test('mobile 3:4 cover fills the frame without stretching any source framing', () => {
    for (const type of ['mp4', 'bunny', 'youtube']) {
        for (const kind of ['native-vertical', 'embedded-vertical', 'horizontal']) {
            const frame = { ...resolveFraming(type, { kind }), viewportAspectRatio: 3 / 4 };
            const cover = verticalFraming(frame);
            assert.ok(cover.widthPercent >= 100 && cover.heightPercent >= 100);
            close((cover.widthPercent * (3 / 4)) / cover.heightPercent, frame.sourceAspectRatio);
            close(cover.leftPercent, (100 - cover.widthPercent) / 2);
            close(cover.topPercent, (100 - cover.heightPercent) / 2);
        }
    }
});
test('embedded content requires explicit framing, never inferred from a 16:9 canvas', () => {
    assert.equal(
        resolveFraming('mp4', { kind: 'auto', sourceAspectRatio: 16 / 9 }).kind,
        'horizontal',
    );
    const frame = resolveFraming('mp4', {
        kind: 'embedded-vertical',
        focalX: 0.5,
    });
    assert.equal(frame.viewportAspectRatio, 3 / 4);
    assert.equal(frame.sourceAspectRatio, 16 / 9);
    assert.equal(frame.contentAspectRatio, 9 / 16);
    assert.equal(
        resolveFraming('youtube', { kind: 'native-vertical' })
            .viewportAspectRatio,
        3 / 4,
    );
});
test('native 9:16 cover fills width, crops height, preserves aspect and centers', () => {
    const frame = verticalFraming({ sourceAspectRatio: 9 / 16 });
    close(frame.widthPercent, 100);
    close(frame.heightPercent, 133.333333);
    close(frame.leftPercent, 0);
    close(frame.topPercent, -16.666666);
});
test('embedded vertical geometry scales the whole wide canvas with different horizontal crop', () => {
    const frame = verticalFraming({
        sourceAspectRatio: 16 / 9,
        contentAspectRatio: 9 / 16,
    });
    close(frame.widthPercent, 316.049382);
    close(frame.heightPercent, 133.333333);
    close(frame.leftPercent, -108.024691);
    close(frame.topPercent, -16.666666);
});
test('focal positions are configurable and invalid geometry is rejected', () => {
    close(
        verticalFraming({ sourceAspectRatio: 9 / 16, focalY: 0 }).topPercent,
        0,
    );
    close(
        verticalFraming({ sourceAspectRatio: 9 / 16, focalY: 1 }).topPercent,
        -33.333333,
    );
    assert.throws(() => verticalFraming({ sourceAspectRatio: 0 }));
});
test('VSL curve is monotonic, initially fast and progressively slows', () => {
    assert.equal(vslProgress(0, 100), 0);
    assert.equal(vslProgress(100, 100), 1);
    assert.ok(vslProgress(10, 100) > 0.1);
    const early = vslProgress(10, 100) - vslProgress(0, 100);
    const late = vslProgress(90, 100) - vslProgress(80, 100);
    assert.ok(early > late);
    let previous = 0;
    for (let t = 0; t <= 100; t++) {
        const progress = vslProgress(t, 100);
        assert.ok(progress >= previous);
        previous = progress;
    }
});
test('decorative progress resets on timeline reset and handles configurable acceleration and invalid samples', () => {
    assert.equal(vslProgress(0, 100), 0);
    assert.equal(vslProgress(50, 100, 1), 0.5);
    assert.equal(vslProgress(NaN, 100), 0);
    assert.equal(vslProgress(20, 0), 0);
    assert.equal(vslProgress(120, 100), 1);
    assert.equal(vslProgress(-10, 100), 0);
});

test('unconfigured and auto layout use stable defaults instead of loaded dimensions', () => {
    for (const type of ['mp4', 'bunny', 'youtube']) {
        assert.equal(resolveFraming(type).viewportAspectRatio, 16 / 9);
        assert.deepEqual(
            resolveFraming(type, { kind: 'auto' }),
            resolveFraming(type),
        );
    }
    assert.equal(
        resolveFraming('development-fixture').viewportAspectRatio,
        3 / 4,
    );
});
test('explicit framing fixes container and media geometry regardless of playback status', () => {
    for (const type of ['bunny', 'mp4', 'youtube']) {
        for (const kind of [
            'native-vertical',
            'horizontal',
            'embedded-vertical',
        ]) {
            const frame = resolveFraming(type, { kind });
            assert.equal(
                frame.viewportAspectRatio,
                kind === 'horizontal' ? 16 / 9 : 3 / 4,
            );
            assert.equal(
                frame.sourceAspectRatio,
                kind === 'native-vertical' ? 9 / 16 : 16 / 9,
            );
        }
    }
    const frame = resolveFraming('bunny', { kind: 'native-vertical' });
    close(verticalFraming(frame).widthPercent, 100);
    assert.equal(
        resolveFraming('bunny', {
            kind: 'native-vertical',
            sourceAspectRatio: 480 / 854,
        }).viewportAspectRatio,
        3 / 4,
    );
});
