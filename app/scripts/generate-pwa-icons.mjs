import { deflateSync } from 'node:zlib';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const outputDir = resolve('public/icons');

const crcTable = new Uint32Array(256).map((_, index) => {
    let crc = index;
    for (let bit = 0; bit < 8; bit += 1) {
        crc = crc & 1 ? 0xedb88320 ^ (crc >>> 1) : crc >>> 1;
    }
    return crc >>> 0;
});

function crc32(buffer) {
    let crc = 0xffffffff;
    for (const byte of buffer) {
        crc = crcTable[(crc ^ byte) & 0xff] ^ (crc >>> 8);
    }
    return (crc ^ 0xffffffff) >>> 0;
}

function chunk(type, data) {
    const typeBuffer = Buffer.from(type);
    const length = Buffer.alloc(4);
    const checksum = Buffer.alloc(4);

    length.writeUInt32BE(data.length);
    checksum.writeUInt32BE(crc32(Buffer.concat([typeBuffer, data])));

    return Buffer.concat([length, typeBuffer, data, checksum]);
}

function writePng(path, size, drawPixel) {
    const header = Buffer.alloc(13);
    header.writeUInt32BE(size, 0);
    header.writeUInt32BE(size, 4);
    header[8] = 8;
    header[9] = 6;

    const rows = [];
    for (let y = 0; y < size; y += 1) {
        const row = Buffer.alloc(1 + size * 4);
        for (let x = 0; x < size; x += 1) {
            const [r, g, b, a] = drawPixel(x, y, size);
            const offset = 1 + x * 4;
            row[offset] = r;
            row[offset + 1] = g;
            row[offset + 2] = b;
            row[offset + 3] = a;
        }
        rows.push(row);
    }

    const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);
    const png = Buffer.concat([
        signature,
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(Buffer.concat(rows), { level: 9 })),
        chunk('IEND', Buffer.alloc(0)),
    ]);

    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, png);
}

function iconPixel(x, y, size) {
    const margin = Math.round(size * 0.14);
    const stroke = Math.max(16, Math.round(size * 0.105));
    const radius = Math.round(size * 0.1);

    let r = 250;
    let g = 250;
    let b = 248;
    let a = 255;

    const inside =
        x >= margin &&
        y >= margin &&
        x < size - margin &&
        y < size - margin &&
        !(
            x < margin + radius &&
            y < margin + radius &&
            Math.hypot(x - (margin + radius), y - (margin + radius)) > radius
        ) &&
        !(
            x >= size - margin - radius &&
            y < margin + radius &&
            Math.hypot(
                x - (size - margin - radius - 1),
                y - (margin + radius),
            ) > radius
        ) &&
        !(
            x < margin + radius &&
            y >= size - margin - radius &&
            Math.hypot(
                x - (margin + radius),
                y - (size - margin - radius - 1),
            ) > radius
        ) &&
        !(
            x >= size - margin - radius &&
            y >= size - margin - radius &&
            Math.hypot(
                x - (size - margin - radius - 1),
                y - (size - margin - radius - 1),
            ) > radius
        );

    if (!inside) {
        return [r, g, b, a];
    }

    r = 32;
    g = 40;
    b = 36;

    const top = Math.round(size * 0.28);
    const bottom = Math.round(size * 0.68);
    const left = Math.round(size * 0.36);
    const right = Math.round(size * 0.61);
    const hook = Math.round(size * 0.54);

    const inTop = y >= top && y <= top + stroke && x >= left && x <= right;
    const inStem = x >= right - stroke && x <= right && y >= top && y <= bottom;
    const inHook =
        y >= bottom - stroke && y <= bottom && x >= hook - stroke && x <= right;
    const inLeftHook =
        x >= hook - stroke &&
        x <= hook &&
        y >= bottom - stroke * 2 &&
        y <= bottom;

    if (inTop || inStem || inHook || inLeftHook) {
        return [250, 250, 248, 255];
    }

    return [r, g, b, a];
}

writePng(resolve(outputDir, 'pwa-192.png'), 192, iconPixel);
writePng(resolve(outputDir, 'pwa-512.png'), 512, iconPixel);
writePng(resolve(outputDir, 'pwa-maskable-512.png'), 512, iconPixel);
writePng(resolve('public/apple-touch-icon.png'), 180, iconPixel);
