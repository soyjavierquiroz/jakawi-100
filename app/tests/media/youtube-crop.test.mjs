import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
const { getYouTubeCropLayout, applyYouTubeCrop, framingMetadata } =
    await import(pathToFileURL(process.env.VSL_CROP_MODULE).href);
test('original embedded crop constant, center and explicit opt-in', () => {
    assert.equal(getYouTubeCropLayout(null), null);
    assert.equal(getYouTubeCropLayout(framingMetadata('horizontal')), null);
    assert.equal(
        getYouTubeCropLayout(framingMetadata('native-vertical')),
        null,
    );
    assert.deepEqual(
        getYouTubeCropLayout(framingMetadata('embedded-vertical')),
        {
            width: '316.049383%',
            height: 'auto',
            aspectRatio: '16 / 9',
            left: '50%',
            top: '50%',
            transform: 'translate(-50%, -50%)',
        },
    );
});
test('original focal point behavior and important style application', () => {
    const metadata = framingMetadata('embedded-vertical', 0.4, 0.7);
    const properties = new Map();
    applyYouTubeCrop(
        {
            style: {
                setProperty: (key, value, priority) =>
                    properties.set(key, [value, priority]),
            },
        },
        metadata,
    );
    assert.deepEqual(properties.get('inset'), ['auto', 'important']);
    assert.deepEqual(properties.get('width'), ['316.049383%', 'important']);
    assert.deepEqual(properties.get('left'), ['60%', 'important']);
    assert.deepEqual(properties.get('top'), ['30%', 'important']);
    assert.deepEqual(properties.get('aspect-ratio'), ['16 / 9', 'important']);
});
