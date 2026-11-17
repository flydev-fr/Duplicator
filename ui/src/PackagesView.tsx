import { useState } from 'react';
import type { Api, Bootstrap, Package, State } from './api';
import { Icon, ServiceIcon } from './icons';
import { Button, IconButton, useConfirm, useToast } from './ui';
import { formatSize, fullDate, serviceLabel, timeAgo } from './util';

export function PackagesView({ boot, api, state, refresh }: { boot: Bootstrap; api: Api; state: State | null; refresh: () => Promise<void> }) {
  const toast = useToast();
  const { confirm, dialog } = useConfirm();
  const [building, setBuilding] = useState(false);
  const [busyKey, setBusyKey] = useState<string | null>(null);

  if (!state) {
    return (
      <div className="dup-loading">
        <span className="dup-spinner" /> Loading packages…
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
    state.retention.maxPackages ? `keeps ${state.retention.maxPackages}` : null,
    state.retention.deadline && state.retention.deadline !== 'never' ? `removes after ${state.retention.deadline}` : null,
  ].filter(Boolean).join(', ');

  return (
    <>
      <section className="dup-summary">
        <div className="dup-summary-main">
          <h2>Packages</h2>
          <p className="dup-muted">A package holds the site files and its database. Restore it anywhere with the installer.</p>
        </div>
        <div className="dup-summary-actions">
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

      {building && <div className="dup-progress" role="progressbar" aria-label="Building package" />}

      <dl className="dup-facts">
        <div>
          <dt>Storage</dt>
          <dd>{enabledStorages.length ? enabledStorages.map(serviceLabel).join(', ') : <span className="dup-warn-text">None selected</span>}</dd>
        </div>
        <div>
          <dt>Encryption</dt>
          <dd className="dup-with-icon">
            <Icon name={state.encryption ? 'lock' : 'unlock'} />
            {state.encryption ? 'On' : 'Off'}
            {state.encryption && (
              <a className="dup-inline-link" href={`${boot.urls.self}?action=dupcrypt`}>
                dupcrypt.php
              </a>
            )}
          </dd>
        </div>
        <div>
          <dt>Retention</dt>
          <dd>{retention || 'Keeps every package'}</dd>
        </div>
        <div>
          <dt>Schedule</dt>
          <dd>{state.schedule.mode && state.schedule.mode !== 'none' ? `${state.schedule.mode}${state.schedule.cycle ? ' · ' + state.schedule.cycle : ''}` : 'Manual'}</dd>
        </div>
      </dl>

      {state.errors.map((err) => (
        <div key={err} className="dup-notice dup-notice-warn">
          <Icon name="alert" />
          <span>{err}</span>
        </div>
      ))}

      {state.packages.length === 0 ? (
        <div className="dup-empty">
          <Icon name="layers" />
          <p>No package yet.</p>
          <p className="dup-muted">Build the first one with “Backup now”.</p>
        </div>
      ) : (
        <div className="dup-table-wrap">
          <table className="dup-table">
            <thead>
              <tr>
                <th scope="col">Package</th>
                <th scope="col">Storage</th>
                <th scope="col">Created</th>
                <th scope="col" className="dup-num">Size</th>
                <th scope="col" className="dup-actions-col">
                  <span className="dup-sr">Actions</span>
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
                      <div className="dup-name">
                        <span className="dup-name-text" title={p.name}>
                          {p.name}
                        </span>
                        {p.encrypted && (
                          <span className="dup-badge" title="Encrypted: restoring it needs the secret key">
                            <Icon name="lock" /> Encrypted
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="dup-muted">
                      <span className="dup-with-icon">
                        <ServiceIcon service={p.service} />
                        {serviceLabel(p.service)}
                      </span>
                    </td>
                    <td title={fullDate(p.time)}>{timeAgo(p.time)}</td>
                    <td className="dup-num">{formatSize(p.size)}</td>
                    <td className="dup-actions">
                      {busy ? (
                        <span className="dup-spinner" aria-label="Working" />
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
