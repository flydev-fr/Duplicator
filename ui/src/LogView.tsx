import { useState } from 'react';
import type { State } from './api';
import { Icon } from './icons';
import { Button } from './ui';

export function LogView({ state, refresh }: { state: State | null; refresh: () => Promise<void> }) {
  const [filter, setFilter] = useState('');
  const [loading, setLoading] = useState(false);

  if (!state) {
    return (
      <div className="dup-loading">
        <span className="dup-spinner" /> Loading log…
      </div>
    );
  }

  const needle = filter.trim().toLowerCase();
  const lines = needle ? state.log.filter((l) => l.text.toLowerCase().includes(needle)) : state.log;

  return (
    <>
      <section className="dup-summary">
        <div className="dup-summary-main">
          <h2>Log</h2>
          <p className="dup-muted">Latest entries first.</p>
        </div>
        <div className="dup-summary-actions">
          <label className="dup-search">
            <Icon name="search" />
            <span className="dup-sr">Filter the log</span>
            <input type="search" placeholder="Filter" value={filter} onChange={(e) => setFilter(e.target.value)} />
          </label>
          <Button icon="refresh" busy={loading} onClick={async () => { setLoading(true); await refresh(); setLoading(false); }}>
            Refresh
          </Button>
        </div>
      </section>
      {lines.length === 0 ? (
        <div className="dup-empty">
          <Icon name="clock" />
          <p>{needle ? 'No entry matches this filter.' : 'The log is empty.'}</p>
        </div>
      ) : (
        <ol className="dup-log">
          {lines.map((l, i) => (
            <li key={i} className={/error|failed|fail\b/i.test(l.text) ? 'is-error' : /warning/i.test(l.text) ? 'is-warn' : undefined}>
              <time>{l.date}</time>
              <span>{l.text}</span>
            </li>
          ))}
        </ol>
      )}
    </>
  );
}
