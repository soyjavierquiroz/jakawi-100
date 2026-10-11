import { test } from 'node:test';
import assert from 'node:assert/strict';
const { parseVideoShortcode: parse } = await import(`${process.env.VSL_CONFIG_MODULE}/video-shortcode.js`);
const { videoThemeColors: theme } = await import(`${process.env.VSL_CONFIG_MODULE}/video-theme.js`);
test('public shortcode converts to the same player configuration', () => {
    assert.deepEqual(parse('[jakawi_video source="youtube" id="hI-Ecy2D8pg" mode="vsl" framing="embedded-vertical"]'), {
        source: { type: 'youtube', url: 'https://www.youtube.com/watch?v=hI-Ecy2D8pg' }, mode: 'vsl', framing: 'embedded-vertical',
    });
    assert.equal(parse('[jakawi_video source="bunny" url="https://cdn.example/video.m3u8" mode="course" autoplay="off"]').mode, 'course');
});
test('rejects code, unknown/duplicate parameters and unsafe URLs', () => {
    for (const input of [
        '[jakawi_video source="mp4" url="javascript:alert(1)"]',
        '[jakawi_video source="mp4" url="https://user:pass@example.com/v.mp4"]',
        '[jakawi_video source="youtube" id="hI-Ecy2D8pg" onload="alert(1)"]',
        '[jakawi_video source="youtube" source="mp4" id="hI-Ecy2D8pg"]',
        '[jakawi_video source="youtube" id="hI-Ecy2D8pg" mode="admin"]',
        '[jakawi_video source="youtube" id="hI-Ecy2D8pg" text="red;display:none"]',
        '<div>[jakawi_video source="youtube" id="hI-Ecy2D8pg"]</div>',
    ]) assert.equal(parse(input), null, input);
});
test('theme inherits by default and corrects low contrast instance colors', () => {
    assert.deepEqual(theme(), { accent: undefined });
    assert.equal(theme({ text: '#ffffff', overlay: '#ffffff' }).text, '#000000');
    assert.equal(theme({ text: '#111111', overlay: '#111111' }).text, '#ffffff');
    assert.equal(theme({ accent: 'url(unsafe)' }).accent, undefined);
});

const { resolveFraming } = await import(`${process.env.VSL_CONFIG_MODULE}/marketing-presentation.js`);
test('simple framing aliases retain the approved geometry', () => {
    for (const kind of ['embedded-vertical', 'native-vertical', 'horizontal']) {
        assert.deepEqual(resolveFraming('youtube', kind), resolveFraming('youtube', { kind }));
    }
});
