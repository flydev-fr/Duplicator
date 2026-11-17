import { useState } from 'react';
import type { Api, Bootstrap, Package, State } from './api';
import { Icon, ServiceIcon } from './icons';
import { Button, IconButton, useConfirm, useToast } from './ui';
import { formatSize, fullDate, serviceLabel, timeAgo } from './util';

const CYCLES: Record<string, string> = { everyHour: 'every hour', every6Hours: 'every 6 hours', everyDay: 'every day', every2Days: 'every 2 days', everyWeek: 'every week', every2Weeks: 'every 2 weeks', every4Weeks: 'every 4 weeks', logoutTrigger: 'on logout' };

function scheduleLabel(schedule: State['schedule']): string {
  if (schedule.mode === 'LazyCron') return `On site visits, ${CYCLES[schedule.cycle] ?? 'off'}`;
  if (schedule.mode === 'PWCron') return 'PwCron module';
  return 'Manual or server cron';
}

export function PackagesView({ boot, api, state, refresh }: { boot: Bootstrap; api: Api; state: State | null; refresh: () => Promise<void> }) {
  const toast = useToast();
  const { confirm, dialog } = useConfirm();
  const [building, setBuilding] = useState(false);
  const [busyKey, setBusyKey] = useState<string | null>(null);

  if (!state) {
    return (
      <div className="dpa-loading">
        <span className="dpa-spinner" /> Loading packages…
      </div>
    );
  }

  const enabledStorages = (Object.keys(state.storages) as (keyof State['storages'])[]).filter((k) => state.storages[k]);
  const localCount = state.packages.filter((p) => p.service === 'local').length;

  const backup = async () => {
    setBuilding(true);
    try {
      await api.post('backup');
      toast('ok', 'Package built.');
    } catch (e) {
      toast('error', (e as Error).message);
    } finally {
      setBuilding(false);
      refresh();
    }
  };

  const run = async (key: string, op: string, pkg: Package, success: string) => {
    setBusyKey(key);
    try {
      await api.post(op, { service: pkg.service, file: pkg.id });
      toast('ok', success);
      await refresh();
    } catch (e) {
      toast('error', (e as Error).message);
    } finally {
      setBusyKey(null);
    }
  };

  const remove = async (pkg: Package) => {
    const where = pkg.service === 'local' ? 'from this server' : `from ${serviceLabel(pkg.service)}`;
    if (await confirm('Delete this package?', <p><strong>{pkg.name}</strong> will be deleted {where}. This cannot be undone.</p>)) {
      run(`del:${pkg.service}:${pkg.id}`, 'delete', pkg, 'Package deleted.');
    }
  };

  const removeAll = async () => {
    if (await confirm('Delete all local packages?', <p>The {localCount} packages in the local folder will be deleted. Packages in cloud storages are kept. This cannot be undone.</p>, 'Delete all')) {
      try {
        await api.post('deleteAll');
        toast('ok', 'Local packages deleted.');
      } catch (e) {
        toast('error', (e as Error).message);
      }
      refresh();
    }
  };

  const retention = [
    state.retention.maxPackages ? `Keeps the last ${state.retention.maxPackages}` : null,
    state.retention.deadline && state.retention.deadline !== 'never' ? `deletes after ${state.retention.deadline}` : null,
  ].filter(Boolean).join(', ');

  return (
    <>
      <section className="dpa-summary">
        <div className="dpa-summary-main">
          <h2>Packages</h2>
          <p className="dpa-muted">A package holds the site files and its database. Restore it anywhere with the installer.</p>
        </div>
        <div className="dpa-summary-actions">
          {localCount > 0 && (
            <Button variant="ghost" icon="trash" onClick={removeAll} disabled={building}>
              Delete all local
            </Button>
          )}
          <Button variant="primary" icon="backup" busy={building} onClick={backup}>
            {building ? 'Building package…' : 'Backup now'}
          </Button>
        </div>
      </section>

      {building && <div className="dpa-progress" role="progressbar" aria-label="Building package" />}

      <dl className="dpa-facts">
        <div>
          <dt>Storage</dt>
          <dd>{enabledStorages.length ? enabledStorages.map(serviceLabel).join(', ') : <span className="dpa-warn-text">None selected</span>}</dd>
        </div>
        <div>
          <dt>Encryption</dt>
          <dd>
            <span className={`dpa-status ${state.encryption ? 'is-on' : 'is-off'}`}>
              <Icon name={state.encryption ? 'shield-check' : 'shield'} />
              {state.encryption ? 'Packages encrypted' : 'Not encrypted'}
            </span>
            {state.encryption ? (
              <a className="dpa-fact-link" href={`${boot.urls.self}?action=dupcrypt`}>
                <Icon name="download" /> Download dupcrypt.php
              </a>
            ) : (
              boot.canEditSettings && (
                <a className="dpa-fact-link" href="#/settings">
                  Turn on
                </a>
              )
            )}
          </dd>
        </div>
        <div>
          <dt>Retention</dt>
          <dd>{retention || 'Keeps every package'}</dd>
        </div>
        <div>
          <dt>Schedule</dt>
          <dd>{scheduleLabel(state.schedule)}</dd>
        </div>
      </dl>

      {state.errors.map((err) => (
        <div key={err} className="dpa-notice dpa-notice-warn">
          <Icon name="alert" />
          <span>{err}</span>
        </div>
      ))}

      {state.packages.length === 0 ? (
        <div className="dpa-empty">
          <Icon name="layers" />
          <p>No package yet.</p>
          <p className="dpa-muted">Build the first one with “Backup now”.</p>
        </div>
      ) : (
        <div className="dpa-table-wrap">
          <table className="dpa-table">
            <thead>
              <tr>
                <th scope="col">Package</th>
                <th scope="col">Storage</th>
                <th scope="col">Created</th>
                <th scope="col" className="dpa-num">Size</th>
                <th scope="col" className="dpa-actions-col">
                  <span className="dpa-sr">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {state.packages.map((p) => {
                const key = `${p.service}:${p.id}`;
                const busy = busyKey?.endsWith(key);
                return (
                  <tr key={key} className={busy ? 'is-busy' : undefined}>
                    <td>
                      <div className="dpa-name">
                        <span className={`dpa-enc${p.encrypted ? ' is-on' : ''}`} title={p.encrypted ? 'Encrypted: restoring it needs the secret key' : p.encrypted === false ? 'Not encrypted' : undefined}>
                          {p.encrypted ? <Icon name="shield-check" /> : null}
                          <span className="dpa-sr">{p.encrypted ? 'Encrypted' : p.encrypted === false ? 'Not encrypted' : ''}</span>
                        </span>
                        <span className="dpa-name-text" title={p.name}>
                          {p.name}
                        </span>
                      </div>
                    </td>
                    <td className="dpa-muted">
                      <span className="dpa-with-icon">
                        <ServiceIcon service={p.service} />
                        {serviceLabel(p.service)}
                      </span>
                    </td>
                    <td title={fullDate(p.time)}>{timeAgo(p.time)}</td>
                    <td className="dpa-num">{formatSize(p.size)}</td>
                    <td className="dpa-actions">
                      {busy ? (
                        <span className="dpa-spinner" aria-label="Working" />
                      ) : (
                        <>
                          {p.service === 'local' ? (
                            <IconButton icon="download" label="Download package" href={`${boot.urls.self}?action=packages&file=${encodeURIComponent(p.id)}`} />
                          ) : (
                            <IconButton icon="sync" label="Copy to the local folder" onClick={() => run(`sync:${key}`, 'sync', p, 'Package copied to the local folder.')} />
                          )}
                          <IconButton icon="installer" label="Download installer.php" href={`${boot.urls.self}?action=packages&installer=1`} />
                          <IconButton icon="trash" label="Delete package" tone="danger" onClick={() => remove(p)} />
                        </>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
      {dialog}
    </>
  );
}
