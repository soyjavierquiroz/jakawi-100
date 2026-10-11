import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
// Optional isolated integration test. Serve tap-harness with its Vite config;
// fixture directory contains alpha.mp4 and HLS playlists/segments, never production data.
test('VSL whole-frame sound, providers, keyboard, touch, recovery and source cleanup', {
    skip: !process.env.VSL_PUPPETEER_MODULE || !process.env.VSL_CHROMIUM || !process.env.VSL_TAP_FIXTURES,
    timeout: 180000,
}, async () => {
    const { default: puppeteer } = await import(pathToFileURL(process.env.VSL_PUPPETEER_MODULE).href);
    const browser = await puppeteer.launch({executablePath: process.env.VSL_CHROMIUM, args:['--no-sandbox']});
    const base = process.env.VSL_TAP_URL || 'http://127.0.0.1:8096/tests/media/tap-harness/';
    const selector = '[data-vsl-sound]';
    try {
        const page = await browser.newPage();
        await page.setRequestInterception(true);
        page.on('request', request => {
            if (!request.url().startsWith('https://media.test/')) return void request.continue();
            const name = path.basename(new URL(request.url()).pathname);
            const file = path.join(process.env.VSL_TAP_FIXTURES, name);
            const contentType = name.endsWith('.m3u8') ? 'application/vnd.apple.mpegurl' : name.endsWith('.ts') ? 'video/mp2t' : 'video/mp4';
            void request.respond({status:200, contentType, headers:{'access-control-allow-origin':'*'}, body:fs.readFileSync(file)});
        });
        await page.evaluateOnNewDocument(() => {
            // Provider contract fixture: validates iframe hit testing without relying on YouTube availability.
            window.YT = { Player: class {
                muted = true; volume = 100; time = 8;
                constructor(mount, options) {
                    this.events = options.events;
                    this.frame = document.createElement('iframe'); mount.replaceWith(this.frame);
                    window.tapYoutube = this;
                    queueMicrotask(() => this.events.onReady({ target: this }));
                }
                mute() {this.muted = true;} unMute() {this.muted = false;}
                isMuted() {return this.muted;} getVolume() {return this.volume;}
                setVolume(v) {this.volume = v;} getCurrentTime() {return this.time;}
                getDuration() {return 20;} seekTo(v) {this.time = v;}
                playVideo() {queueMicrotask(() => this.events.onStateChange({data:1}));}
                pauseVideo() {queueMicrotask(() => this.events.onStateChange({data:2}));}
                destroy() {this.frame.remove();}
            }};
            const play = HTMLMediaElement.prototype.play;
            window.tapPlayRequests = [];
            HTMLMediaElement.prototype.play = function() {
                window.tapPlayRequests.push({time:this.currentTime,muted:this.muted});
                if (location.search.includes('block=1') && !window.tapAutoplayBlocked) {
                    window.tapAutoplayBlocked=true;
                    return Promise.reject(new DOMException('fixture autoplay block','NotAllowedError'));
                }
                if (window.tapHold && !this.muted) {
                    window.tapHold=false;
                    const block=event=>event.stopImmediatePropagation();
                    this.addEventListener('playing',block,{capture:true});
                    window.tapRelease=()=>{this.removeEventListener('playing',block,{capture:true});this.dispatchEvent(new Event('playing'));};
                    return play.call(this);
                }
                if (window.tapReject && !this.muted) {
                    window.tapReject = false;
                    return Promise.reject(new DOMException('fixture block', 'NotAllowedError'));
                }
                return play.call(this);
            };
        });
        async function open(provider, mobile = false, mode = 'vsl') {
            await page.setViewport({width: mobile ? 390 : 1280, height: 900, isMobile: mobile, hasTouch: mobile});
            await page.goto(`${base}?provider=${provider}&mode=${mode}`, {waitUntil:'networkidle0'});
            if (mode === 'vsl') await page.waitForSelector(selector);
        }
        async function activate(method) {
            const media = await page.$('video, iframe');
            const count = await page.evaluate(() => window.tapEvents.filter(e => e.type === 'audio-activated').length);
            const rect = await page.$eval(selector, element => {const r = element.getBoundingClientRect(); return {x:r.x,y:r.y,w:r.width,h:r.height};});
            const target = method === 'corner' || method === 'touch' ? {x:rect.x+8,y:rect.y+8} : {x:rect.x+rect.w/2,y:rect.y+rect.h/2};
            assert.equal(await page.evaluate(({x,y}) => document.elementFromPoint(x,y).matches('[data-vsl-sound]'), target), true);
            if (method === 'Enter' || method === 'Space') {
                await page.focus(selector);
                assert.equal(await page.$eval(selector,e=>getComputedStyle(e).outlineStyle), 'solid');
                await page.keyboard.press(method);
            } else if (method === 'touch') await page.touchscreen.tap(target.x,target.y);
            else await page.mouse.click(target.x,target.y);
            await page.waitForFunction(() => !document.querySelector('[data-vsl-sound]'));
            const event = await page.evaluate(() => window.tapEvents.filter(e => e.type === 'audio-activated').at(-1));
            assert.equal(event.state.muted, false);
            assert.ok(event.state.currentTime < 1, 'activation restarts at zero');
            assert.equal(await page.evaluate(() => window.tapEvents.filter(e => e.type === 'audio-activated').length), count+1);
            assert.equal(await media.evaluate(e=>e.isConnected), true, 'media node was preserved');
        }
        for (const provider of ['mp4','bunny','youtube']) {
            for (const method of ['center','corner','Enter','Space','touch']) {
                await open(provider, method === 'touch');
                if (provider === 'youtube') assert.ok(Math.abs(await page.$eval('iframe',e=>parseFloat(e.style.width))-316.049383)<0.001);
                else {
                    await page.$eval('video',v=>{v.currentTime=1;});
                    await page.waitForSelector(selector);
                }
                console.log(provider, method);
                if (method === 'center' && provider === 'mp4') await page.screenshot({path:process.env.VSL_TAP_SCREENSHOT || '/tmp/jakawi-video-touch.png'});
                await activate(method);
                await page.click('button[aria-label="Pausar video"]');
                await page.waitForFunction(() => [...document.querySelectorAll('button')].some(e=>e.textContent==='Continuar'));
                assert.equal(await page.$(selector), null);
                await page.locator('button').filter(e=>e.textContent==='Continuar').click();
            }
        }
        await open('mp4');
        await page.evaluate(()=>{window.tapHold=true;const button=document.querySelector('[data-vsl-sound]');button.click();button.click();});
        assert.equal(await page.evaluate(()=>window.tapPlayRequests.filter(e=>!e.muted).length),1);
        await page.waitForFunction(()=>!document.querySelector('video').paused);
        assert.ok(await page.$(selector),'layer waits for confirmation');
        await page.evaluate(()=>window.tapRelease());
        await page.waitForFunction(()=>!document.querySelector('[data-vsl-sound]'));
        await open('mp4',true);
        const client=await page.createCDPSession();
        await client.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:200,y:380}]});
        await client.send('Input.dispatchTouchEvent',{type:'touchMove',touchPoints:[{x:200,y:180}]});
        await client.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
        await page.waitForFunction(()=>window.scrollY>50);
        assert.equal(await page.evaluate(()=>window.tapEvents.filter(e=>e.type==='audio-activated').length),0);
        await open('mp4');
        // Pause must win hit testing even before audio activation.
        await page.click('button[aria-label="Pausar video"]');
        assert.equal(await page.evaluate(()=>window.tapEvents.filter(e=>e.type==='audio-activated').length),0);
        assert.equal(await page.$(selector),null);
        await open('mp4');
        await page.evaluate(()=>{window.tapReject=true;});
        await page.click(selector);
        await page.waitForFunction(()=>window.tapEvents.some(e=>e.type==='error'&&e.error.kind==='playback-blocked'));
        await page.locator('button').filter(e=>e.textContent==='Ver video').click();
        await page.waitForSelector(selector);
        await activate('corner');
        await page.click('#source');
        await page.waitForSelector(selector);
        assert.equal(await page.$eval('video',v=>v.muted),true);
        await activate('corner');
        await page.$eval('video',v=>{v.currentTime=v.duration-0.1;v.playbackRate=16;});
        await page.waitForFunction(()=>[...document.querySelectorAll('button')].some(e=>e.textContent==='Volver a ver'));
        assert.equal(await page.$(selector),null);
        await page.$eval('video',v=>{v.playbackRate=1;});
        await page.locator('button').filter(e=>e.textContent==='Volver a ver').click();
        await page.waitForFunction(()=>!document.querySelector('video').paused);
        assert.equal(await page.$(selector),null);
        await page.goto(`${base}?provider=mp4&block=1`,{waitUntil:'networkidle0'});
        await page.waitForFunction(()=>window.tapEvents.some(e=>e.type==='error'&&e.error.kind==='autoplay-blocked'));
        assert.equal(await page.$(selector),null);
        await page.locator('button').filter(e=>e.textContent==='Ver video').click();
        await page.waitForSelector(selector);
        await activate('center');
        for (const provider of ['mp4','bunny','youtube']) {await open(provider,false,'course'); assert.equal(await page.$(selector),null);}
        console.log('Providers: real MP4/HLS fixtures; YouTube API contract fixture. Desktop, mobile touch emulation, Enter/Space, pause/replay, recovery and source reset passed.');
    } finally {await browser.close();}
});
