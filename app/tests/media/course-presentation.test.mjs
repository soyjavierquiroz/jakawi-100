import assert from 'node:assert/strict';
import { test } from 'node:test';
import { pathToFileURL } from 'node:url';
const { courseProgress, courseTime } = await import(
    pathToFileURL(process.env.VSL_COURSE_MODULE).href
);
test('course progress is linear provider time and handles unknown duration', () => {
    assert.equal(courseProgress(10, 100), 0.1);
    assert.equal(courseProgress(50, 100), 0.5);
    assert.equal(courseProgress(100, 100), 1);
    for (const [time, duration] of [
        [0, 0],
        [10, NaN],
        [NaN, 10],
        [10, Infinity],
    ])
        assert.equal(courseProgress(time, duration), 0);
    assert.equal(courseProgress(-1, 100), 0);
    assert.equal(courseProgress(200, 100), 1);
});
test('real times include hour-long lessons and unknown metadata', () => {
    assert.equal(courseTime(75), '01:15');
    assert.equal(courseTime(3661), '1:01:01');
    assert.equal(courseTime(NaN), '00:00');
    assert.equal(courseTime(-1), '00:00');
});
