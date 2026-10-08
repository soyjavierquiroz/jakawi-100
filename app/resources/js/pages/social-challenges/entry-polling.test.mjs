import assert from 'node:assert/strict';
import test from 'node:test';
import { pollDelay, shouldPollEntry } from './entry-polling.ts';

test('polls each pending entry and stops when its inspection reaches a terminal result', () => {
    const entries = [
        { inspection_status: 'pending', refresh_pending: true },
        { inspection_status: 'inspected', refresh_pending: false },
        { inspection_status: 'failed', refresh_pending: true },
    ];
    assert.deepEqual(entries.map(shouldPollEntry), [true, false, true]);
    entries[0] = { inspection_status: 'inspected', refresh_pending: false };
    assert.deepEqual(entries.map(shouldPollEntry), [false, false, true]);
    entries[2] = { inspection_status: 'failed', refresh_pending: false };
    assert.deepEqual(entries.map(shouldPollEntry), [false, false, false]);
    assert.equal(pollDelay(14_999), 1_000);
    assert.equal(pollDelay(15_000), 5_000);
});
