import { metaBrowserTracker, type MetaBrowserContext } from './lib/meta-browser-tracker';
const data = document.getElementById('membership-meta-context');
if (data?.textContent) {
    const context = JSON.parse(data.textContent) as MetaBrowserContext;
    metaBrowserTracker.configure(context);
    context.events.forEach(event => metaBrowserTracker.emit(event));
}
