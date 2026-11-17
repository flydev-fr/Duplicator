import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { Api, Bootstrap, State } from './api';
import { Icon } from './icons';
import { ToastProvider } from './ui';
import { PackagesView } from './PackagesView';
import { SettingsView } from './SettingsView';
import { LogView } from './LogView';

type Route = 'packages' | 'settings' | 'log';

function readRoute(): Route {
  const hash = window.location.hash.replace(/^#\/?/, '');
  return hash === 'settings' || hash === 'log' ? hash : 'packages';
}

/** Follow the admin theme (light or dark) from its actual background colour */
function useAdminTheme(ref: React.RefObject<HTMLDivElement | null>) {
  const [theme, setTheme] = useState<'light' | 'dark'>('light');
  useLayoutEffect(() => {
    const detect = () => {
      let el: HTMLElement | null = ref.current?.parentElement ?? document.body;
      let color = 'rgba(0, 0, 0, 0)';
      while (el) {
        color = getComputedStyle(el).backgroundColor;
        if (!/rgba\(.*,\s*0\)$/.test(color) && color !== 'transparent') break;
        el = el.parentElement;
      }
      const [r, g, b] = (color.match(/[\d.]+/g) ?? ['255', '255', '255']).map(Number);
      setTheme(0.2126 * r + 0.7152 * g + 0.0722 * b < 128 ? 'dark' : 'light');
    };
    detect();
    const observer = new MutationObserver(detect);
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    observer.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-theme'] });
    return () => observer.disconnect();
  }, [ref]);
  return theme;
}

export function App({ boot, api }: { boot: Bootstrap; api: Api }) {
  const rootRef = useRef<HTMLDivElement>(null);
  const theme = useAdminTheme(rootRef);
  const [route, setRoute] = useState<Route>(readRoute);
  const [state, setState] = useState<State | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);

  useEffect(() => {
    const onHash = () => setRoute(readRoute());
    window.addEventListener('hashchange', onHash);
    return () => window.removeEventListener('hashchange', onHash);
  }, []);

  const refresh = useCallback(async () => {
    try {
      setState(await api.get<State>('state'));
      setLoadError(null);
    } catch (e) {
      setLoadError((e as Error).message);
    }
  }, [api]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const tabs: { id: Route; label: string; icon: string }[] = [
    { id: 'packages', label: 'Packages', icon: 'layers' },
    ...(boot.canEditSettings ? [{ id: 'settings' as Route, label: 'Settings', icon: 'settings' }] : []),
    { id: 'log', label: 'Log', icon: 'clock' },
  ];

  return (
    <div ref={rootRef} className="dpa-app" data-theme={theme}>
      <ToastProvider>
        <header className="dpa-header">
          <div className="dpa-brand">
            <img src={boot.urls.logo} alt="Duplicator" className="dpa-logo" />
            <span className="dpa-version">v{boot.version}</span>
          </div>
          <nav className="dpa-tabs" aria-label="Duplicator">
            {tabs.map((t) => (
              <a key={t.id} href={`#/${t.id === 'packages' ? '' : t.id}`} className={`dpa-tab${route === t.id ? ' is-active' : ''}`} aria-current={route === t.id ? 'page' : undefined}>
                <Icon name={t.icon} />
                {t.label}
              </a>
            ))}
          </nav>
          <a className="dpa-help" href={boot.urls.support} target="_blank" rel="noopener noreferrer">
            Support <Icon name="external" />
          </a>
        </header>

        {loadError && (
          <div className="dpa-notice dpa-notice-error" role="alert">
            <Icon name="alert" />
            <span>{loadError}</span>
          </div>
        )}

        <main className="dpa-main">
          {route === 'packages' && <PackagesView boot={boot} api={api} state={state} refresh={refresh} />}
          {route === 'settings' && boot.canEditSettings && <SettingsView boot={boot} api={api} onSaved={refresh} />}
          {route === 'log' && <LogView state={state} refresh={refresh} />}
        </main>
      </ToastProvider>
    </div>
  );
}
