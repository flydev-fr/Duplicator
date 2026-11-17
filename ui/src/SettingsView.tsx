import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { ApiError, type Api, type Bootstrap, type Option, type SettingValue, type SettingValues, type SettingsPayload } from './api';
import { Icon } from './icons';
import { Button, CopyButton, useToast } from './ui';
import { formatSize } from './util';

type SectionId = 'package' | 'storage' | 'schedule' | 'security' | 'advanced';

const SECTIONS: { id: SectionId; label: string; icon: string }[] = [
  { id: 'package', label: 'Package', icon: 'layers' },
  { id: 'storage', label: 'Storage', icon: 'cloud' },
  { id: 'schedule', label: 'Schedule & retention', icon: 'clock' },
  { id: 'security', label: 'Encryption', icon: 'shield' },
  { id: 'advanced', label: 'Advanced', icon: 'settings' },
];

/** Which section shows each setting, to point at errors returned by the server */
const SECTION_OF: Record<string, SectionId> = {
  packageName: 'package', backupWire: 'package', backups: 'package', backupLogs: 'package', backupSessions: 'package',
  backupCache: 'package', backupFiles: 'package', backupVariants: 'package', ignoredPath: 'package', ignoredExtensions: 'package',
  useLocalFolder: 'storage', path: 'storage', orphanedLogs: 'storage', removeBackups: 'storage',
  useFTP: 'storage', ftpHostname: 'storage', ftpUsername: 'storage', ftpPassword: 'storage', ftpPort: 'storage', ftpTimeout: 'storage', ftpSSL: 'storage', ftpPassive: 'storage', ftpDirectory: 'storage',
  useDropbox: 'storage', dropboxAccessToken: 'storage',
  useGoogleDrive: 'storage', googleKeyFile: 'storage', shareWithEmail: 'storage',
  useAmazonS3: 'storage', awsAccessKey: 'storage', awsSecretKey: 'storage', awsBucketName: 'storage', awsSubDir: 'storage', awsRegion: 'storage',
  cronMode: 'schedule', cycle: 'schedule', maxPackages: 'schedule', deadline: 'schedule',
  encryptionEnabled: 'security', encryptionPublicKey: 'security',
  backup_mode: 'advanced', chmodPermission: 'advanced', shellScript: 'advanced', zip_flush_mbytes: 'advanced',
};

interface Ctx {
  values: SettingValues;
  set: (name: string, value: SettingValue) => void;
  errors: Record<string, string[]>;
  data: SettingsPayload;
}

export function SettingsView({ api, onSaved }: { boot: Bootstrap; api: Api; onSaved: () => void }) {
  const toast = useToast();
  const [data, setData] = useState<SettingsPayload | null>(null);
  const [values, setValues] = useState<SettingValues>({});
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [section, setSection] = useState<SectionId>('package');
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const load = (payload: SettingsPayload) => {
    setData(payload);
    setValues(payload.values);
    setErrors({});
  };

  useEffect(() => {
    api.get<SettingsPayload>('settings').then(load).catch((e) => setLoadError((e as Error).message));
  }, [api]);

  const dirty = useMemo(() => !!data && JSON.stringify(values) !== JSON.stringify(data.values), [values, data]);

  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => {
      if (dirty) e.preventDefault();
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  if (loadError) {
    return (
      <div className="dpa-notice dpa-notice-error" role="alert">
        <Icon name="alert" />
        <span>{loadError}</span>
      </div>
    );
  }
  if (!data) {
    return (
      <div className="dpa-loading">
        <span className="dpa-spinner" /> Loading settings…
      </div>
    );
  }

  const set = (name: string, value: SettingValue) => {
    setValues((v) => ({ ...v, [name]: value }));
    setErrors((e) => {
      if (!e[name]) return e;
      const next = { ...e };
      delete next[name];
      return next;
    });
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = await api.post<SettingsPayload>('saveSettings', { settings: JSON.stringify(values) });
      load(payload);
      toast('ok', 'Settings saved.');
      onSaved();
    } catch (e) {
      const err = e as ApiError;
      if (err.fieldErrors) {
        setErrors(err.fieldErrors);
        const first = Object.keys(err.fieldErrors).map((n) => SECTION_OF[n]).find(Boolean);
        if (first) setSection(first);
        toast('error', 'Some settings need your attention.');
      } else {
        toast('error', err.message);
      }
    } finally {
      setSaving(false);
    }
  };

  const ctx: Ctx = { values, set, errors, data };
  const sectionErrors = (id: SectionId) => Object.keys(errors).some((n) => SECTION_OF[n] === id);

  return (
    <div className="dpa-settings">
      <nav className="dpa-settings-nav" aria-label="Settings">
        {SECTIONS.map((s) => (
          <button key={s.id} type="button" className={`dpa-settings-link${s.id === section ? ' is-active' : ''}`} onClick={() => setSection(s.id)} aria-current={s.id === section ? 'true' : undefined}>
            <Icon name={s.icon} />
            <span>{s.label}</span>
            {sectionErrors(s.id) && <span className="dpa-dot" aria-label="needs attention" />}
          </button>
        ))}
      </nav>

      <div className="dpa-settings-body">
        {section === 'package' && <PackageSection {...ctx} />}
        {section === 'storage' && <StorageSection {...ctx} />}
        {section === 'schedule' && <ScheduleSection {...ctx} />}
        {section === 'security' && <SecuritySection {...ctx} />}
        {section === 'advanced' && <AdvancedSection {...ctx} />}
      </div>

      <div className={`dpa-savebar${dirty ? ' is-visible' : ''}`} aria-hidden={!dirty}>
        <span>You have unsaved changes</span>
        <div>
          <Button variant="ghost" onClick={() => { setValues(data.values); setErrors({}); }} disabled={saving}>
            Discard
          </Button>
          <Button variant="primary" busy={saving} onClick={save}>
            Save changes
          </Button>
        </div>
      </div>
    </div>
  );
}

/* ---------- Building blocks ---------- */

function Panel({ title, intro, children }: { title: string; intro?: ReactNode; children: ReactNode }) {
  return (
    <section className="dpa-panel">
      <header className="dpa-panel-head">
        <h2>{title}</h2>
        {intro && <p>{intro}</p>}
      </header>
      {children}
    </section>
  );
}

/** One setting: label and help on the left, control on the right */
function Row({ label, help, htmlFor, errors, children, align = 'center' }: { label: ReactNode; help?: ReactNode; htmlFor?: string; errors?: string[]; children: ReactNode; align?: 'center' | 'start' }) {
  return (
    <div className={`dpa-row${errors?.length ? ' has-error' : ''}${align === 'start' ? ' is-top' : ''}`}>
      <div className="dpa-row-label">
        {htmlFor ? <label htmlFor={htmlFor}>{label}</label> : <span>{label}</span>}
        {help && <p>{help}</p>}
      </div>
      <div className="dpa-row-control">
        {children}
        {errors?.map((e) => (
          <p key={e} className="dpa-error">{e}</p>
        ))}
      </div>
    </div>
  );
}

function Switch({ checked, onChange, label, disabled }: { checked: boolean; onChange: (v: boolean) => void; label: string; disabled?: boolean }) {
  return (
    <label className={`dpa-switch${disabled ? ' is-disabled' : ''}`}>
      <input type="checkbox" checked={checked} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
      <span className="dpa-switch-track" aria-hidden="true" />
      <span className="dpa-sr">{label}</span>
    </label>
  );
}

function Text({ id, value, onChange, placeholder, type = 'text', mono, width }: { id: string; value: SettingValue | undefined; onChange: (v: string) => void; placeholder?: string; type?: string; mono?: boolean; width?: 'sm' | 'md' }) {
  return <input id={id} type={type} className={`${mono ? 'is-mono ' : ''}${width ? 'is-' + width : ''}`} value={String(value ?? '')} placeholder={placeholder} spellCheck={false} autoComplete={type === 'password' ? 'new-password' : 'off'} onChange={(e) => onChange(e.target.value)} />;
}

function Select({ id, value, options, onChange }: { id: string; value: SettingValue | undefined; options: Option[]; onChange: (v: string) => void }) {
  return (
    <select id={id} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
      {options.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

function Secret({ id, name, ctx, multiline }: { id: string; name: string; ctx: Ctx; multiline?: boolean }) {
  const saved = ctx.data.secretsSet[name];
  const placeholder = saved ? 'Saved. Type a new value to replace it.' : '';
  return (
    <div className="dpa-secret">
      {multiline ? (
        <textarea id={id} rows={5} className="is-mono" value={String(ctx.values[name] ?? '')} placeholder={placeholder} spellCheck={false} onChange={(e) => ctx.set(name, e.target.value)} />
      ) : (
        <Text id={id} type="password" value={ctx.values[name]} placeholder={placeholder} onChange={(v) => ctx.set(name, v)} />
      )}
      {saved && (
        <span className="dpa-secret-state">
          <Icon name="key" /> Saved, never shown
        </span>
      )}
    </div>
  );
}

function Choice({ name, value, current, title, detail, badge, disabled, onChange }: { name: string; value: string; current: SettingValue | undefined; title: string; detail: string; badge?: string; disabled?: boolean; onChange: (v: string) => void }) {
  const checked = String(current) === value;
  return (
    <label className={`dpa-choice${checked ? ' is-checked' : ''}${disabled ? ' is-disabled' : ''}`}>
      <input type="radio" name={name} checked={checked} disabled={disabled} onChange={() => onChange(value)} />
      <span className="dpa-choice-text">
        <span className="dpa-choice-title">
          {title}
          {badge && <span className="dpa-tag">{badge}</span>}
        </span>
        <span className="dpa-choice-detail">{detail}</span>
      </span>
    </label>
  );
}

function Command({ children }: { children: string }) {
  return (
    <div className="dpa-command">
      <code>{children}</code>
      <CopyButton text={children} />
    </div>
  );
}

const on = (v: SettingValue | undefined) => Number(v) === 1;
const flag = (b: boolean) => (b ? 1 : 0);

/* ---------- Sections ---------- */

function PackageSection({ values, set, errors, data }: Ctx) {
  const defaultName = data.env.host.replace(/[-:]/g, '_');
  // the module stores exclusions: these switches show what is included
  const parts: { name: string; title: string; detail: string; warnWhenIncluded?: string }[] = [
    { name: 'backupWire', title: 'ProcessWire core', detail: 'The wire/ folder. Keep it to restore on an empty server.' },
    { name: 'backupFiles', title: 'Uploaded files', detail: 'site/assets/files: the images and documents of your pages.' },
    { name: 'backupVariants', title: 'Image variations', detail: 'Resized copies of images. ProcessWire creates them again when needed.' },
    { name: 'backupLogs', title: 'Logs', detail: 'site/assets/logs' },
    { name: 'backupCache', title: 'Cache', detail: 'site/assets/cache. Rebuilt automatically.' },
    { name: 'backupSessions', title: 'Sessions', detail: 'site/assets/sessions. Restoring them logs users back in.' },
    { name: 'backups', title: 'Previous packages', detail: 'site/assets/backups.', warnWhenIncluded: 'Each package would contain all the older ones and grow fast.' },
  ];
  const extOptions = data.options.ignoredExtensions ?? [];
  const excludedExt = Array.isArray(values.ignoredExtensions) ? values.ignoredExtensions : [];

  return (
    <>
      <Panel title="Package" intro="A package is a ZIP of the site files and its database dump. Choose what goes in it.">
        <Row label="File name" htmlFor="s-packageName" errors={errors.packageName} help={<>Packages are named <code>date-name.package2.zip</code>. Leave empty to use <code>{defaultName}</code>.</>}>
          <Text id="s-packageName" value={values.packageName} placeholder={defaultName} onChange={(v) => set('packageName', v)} width="md" />
        </Row>
        <Row label="The database" help="Always included.">
          <span className="dpa-static">
            <Icon name="check" /> Included
          </span>
        </Row>
      </Panel>

      <Panel title="Included files" intro="Everything in the site folder is included, except what you turn off here.">
        {parts.map((p) => {
          const included = !on(values[p.name]);
          return (
            <Row key={p.name} label={p.title} help={p.detail} errors={errors[p.name]}>
              <Switch checked={included} label={p.title} onChange={(v) => set(p.name, flag(!v))} />
              {p.warnWhenIncluded && included && (
                <p className="dpa-inline-warn">
                  <Icon name="alert" /> {p.warnWhenIncluded}
                </p>
              )}
            </Row>
          );
        })}
      </Panel>

      <Panel title="Exclusions">
        <Row label="File types" help="Files with these extensions are left out." errors={errors.ignoredExtensions} align="start">
          <div className="dpa-chips" role="group" aria-label="Excluded file types">
            {extOptions.map((o) => {
              const selected = excludedExt.includes(o.value);
              return (
                <button key={o.value} type="button" className={`dpa-chip${selected ? ' is-selected' : ''}`} aria-pressed={selected} onClick={() => set('ignoredExtensions', selected ? excludedExt.filter((x) => x !== o.value) : [...excludedExt, o.value])}>
                  {selected && <Icon name="check" />}
                  {o.label}
                </button>
              );
            })}
          </div>
        </Row>
        <Row label="Paths and patterns" htmlFor="s-ignoredPath" errors={errors.ignoredPath} align="start" help={<>One per line: a folder starting and ending with <code>/</code> such as <code>/site/assets/files/1042/</code>, a file name, an extension or a regular expression.</>}>
          <textarea id="s-ignoredPath" rows={5} className="is-mono" value={String(values.ignoredPath ?? '')} spellCheck={false} onChange={(e) => set('ignoredPath', e.target.value)} />
        </Row>
      </Panel>
    </>
  );
}

function StorageCard({ title, icon, description, enabled, onToggle, missing, install, status, children }: { title: string; icon: string; description: string; enabled: boolean; onToggle: (v: boolean) => void; missing?: boolean; install?: string; status?: ReactNode; children?: ReactNode }) {
  return (
    <div className={`dpa-storage${enabled ? ' is-on' : ''}`}>
      <div className="dpa-storage-head">
        <span className="dpa-storage-icon">
          <Icon name={icon} />
        </span>
        <div className="dpa-storage-title">
          <strong>{title}</strong>
          <span>{description}</span>
        </div>
        {missing && !enabled ? <span className="dpa-tag">Not installed</span> : status}
        <Switch checked={enabled} label={title} disabled={missing && !enabled} onChange={onToggle} />
      </div>
      {missing && (
        <div className="dpa-storage-install">
          <p>This storage needs its PHP library. In the site root, run:</p>
          <Command>{install ?? ''}</Command>
        </div>
      )}
      {enabled && children && <div className="dpa-storage-body">{children}</div>}
    </div>
  );
}

function StorageSection({ values, set, errors, data }: Ctx) {
  const env = data.env;
  const ctx = { values, set, errors, data };
  const anyOn = ['useLocalFolder', 'useFTP', 'useDropbox', 'useGoogleDrive', 'useAmazonS3'].some((k) => on(values[k]));

  return (
    <Panel title="Storage" intro="Where packages are kept. Turn on as many as you like: each package is saved to all of them.">
      {!anyOn && (
        <p className="dpa-inline-warn">
          <Icon name="alert" /> Turn on at least one storage, or no package can be built.
        </p>
      )}
      <div className="dpa-storages">
        <StorageCard
          title="This server"
          icon="disk"
          description="A folder on the web server."
          enabled={on(values.useLocalFolder)}
          onToggle={(v) => set('useLocalFolder', flag(v))}
          status={on(values.useLocalFolder) && env.diskFree !== null ? <span className="dpa-storage-status">{formatSize(env.diskFree)} free</span> : undefined}
        >
          <Row label="Folder" htmlFor="s-path" errors={errors.path} help={<>An absolute path, or <code>~/</code> for a folder inside the site. Leave empty for the default.</>}>
            <Text id="s-path" value={values.path} placeholder={env.defaultPath} mono onChange={(v) => set('path', v)} />
            {!env.folderWritable && <p className="dpa-error">This folder does not exist or cannot be written.</p>}
          </Row>
          <Row label="Clean up old logs" help="Remove the log files of packages that were deleted.">
            <Switch checked={on(values.orphanedLogs)} label="Clean up old logs" onChange={(v) => set('orphanedLogs', flag(v))} />
          </Row>
        </StorageCard>

        <StorageCard title="FTP" icon="ftp" description="Any FTP or FTPS server." enabled={on(values.useFTP)} onToggle={(v) => set('useFTP', flag(v))}>
          <Row label="Server" htmlFor="s-ftpHostname" errors={errors.ftpHostname}>
            <div className="dpa-inline-fields">
              <Text id="s-ftpHostname" value={values.ftpHostname} placeholder="ftp.example.com" onChange={(v) => set('ftpHostname', v)} />
              <Text id="s-ftpPort" value={values.ftpPort} placeholder="21" width="sm" onChange={(v) => set('ftpPort', v)} />
            </div>
          </Row>
          <Row label="User" htmlFor="s-ftpUsername" errors={errors.ftpUsername}>
            <Text id="s-ftpUsername" value={values.ftpUsername} onChange={(v) => set('ftpUsername', v)} width="md" />
          </Row>
          <Row label="Password" htmlFor="s-ftpPassword" errors={errors.ftpPassword}>
            <Secret id="s-ftpPassword" name="ftpPassword" ctx={ctx} />
          </Row>
          <Row label="Folder" htmlFor="s-ftpDirectory" help="Created if it does not exist." errors={errors.ftpDirectory}>
            <Text id="s-ftpDirectory" value={values.ftpDirectory} placeholder="backups/my-site" mono onChange={(v) => set('ftpDirectory', v)} width="md" />
          </Row>
          <Row label="Encrypted connection" help="FTPS (FTP over TLS). Use it whenever the server supports it.">
            <Switch checked={on(values.ftpSSL)} label="Encrypted connection" onChange={(v) => set('ftpSSL', flag(v))} />
          </Row>
          <Row label="Passive mode" help="Needed by most servers behind a firewall.">
            <Switch checked={on(values.ftpPassive)} label="Passive mode" onChange={(v) => set('ftpPassive', flag(v))} />
          </Row>
          <Row label="Timeout" htmlFor="s-ftpTimeout" help="In seconds." errors={errors.ftpTimeout}>
            <Text id="s-ftpTimeout" value={values.ftpTimeout} placeholder="90" width="sm" onChange={(v) => set('ftpTimeout', v)} />
          </Row>
        </StorageCard>

        <StorageCard title="Dropbox" icon="box" description="Packages go to the Apps/Duplicator folder." enabled={on(values.useDropbox)} onToggle={(v) => set('useDropbox', flag(v))} missing={!env.sdk.dropbox} install="composer require kunalvarma05/dropbox-php-sdk">
          <Row label="Access token" htmlFor="s-dropboxAccessToken" errors={errors.dropboxAccessToken} help={<>Create an app in the <a href="https://www.dropbox.com/developers/apps" target="_blank" rel="noopener noreferrer">Dropbox App Console</a>, then generate a token.</>}>
            <Secret id="s-dropboxAccessToken" name="dropboxAccessToken" ctx={ctx} />
          </Row>
        </StorageCard>

        <StorageCard title="Google Drive" icon="drive" description="Uploaded with a Google service account." enabled={on(values.useGoogleDrive)} onToggle={(v) => set('useGoogleDrive', flag(v))} missing={!env.sdk.googledrive} install="composer require google/apiclient">
          <Row label="Service account key" htmlFor="s-googleKeyFile" errors={errors.googleKeyFile} align="start" help={<>The JSON key of a service account, from the <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">Google Cloud console</a>.</>}>
            <Secret id="s-googleKeyFile" name="googleKeyFile" ctx={ctx} multiline />
          </Row>
          <Row label="Share with" htmlFor="s-shareWithEmail" errors={errors.shareWithEmail} help="Your Google account, to see the packages in your own Drive.">
            <Text id="s-shareWithEmail" type="email" value={values.shareWithEmail} placeholder="you@example.com" onChange={(v) => set('shareWithEmail', v)} width="md" />
          </Row>
        </StorageCard>

        <StorageCard title="Amazon S3" icon="bucket" description="A bucket on Amazon S3. Packages stay private." enabled={on(values.useAmazonS3)} onToggle={(v) => set('useAmazonS3', flag(v))} missing={!env.sdk.amazon} install="composer require aws/aws-sdk-php">
          <Row label="Access key ID" htmlFor="s-awsAccessKey" errors={errors.awsAccessKey}>
            <Text id="s-awsAccessKey" value={values.awsAccessKey} mono onChange={(v) => set('awsAccessKey', v)} width="md" />
          </Row>
          <Row label="Secret access key" htmlFor="s-awsSecretKey" errors={errors.awsSecretKey}>
            <Secret id="s-awsSecretKey" name="awsSecretKey" ctx={ctx} />
          </Row>
          <Row label="Bucket" htmlFor="s-awsBucketName" help="Created if it does not exist." errors={errors.awsBucketName}>
            <Text id="s-awsBucketName" value={values.awsBucketName} mono onChange={(v) => set('awsBucketName', v)} width="md" />
          </Row>
          <Row label="Folder in the bucket" htmlFor="s-awsSubDir" errors={errors.awsSubDir}>
            <Text id="s-awsSubDir" value={values.awsSubDir} placeholder="backups" mono onChange={(v) => set('awsSubDir', v)} width="md" />
          </Row>
          <Row label="Region" htmlFor="s-awsRegion" errors={errors.awsRegion}>
            <Select id="s-awsRegion" value={values.awsRegion} options={data.options.awsRegion ?? []} onChange={(v) => set('awsRegion', v)} />
          </Row>
        </StorageCard>
      </div>

      {!on(values.useLocalFolder) && (
        <Row label="After upload" help="When this server is not a storage, the package built here can be deleted once it is uploaded.">
          <label className="dpa-check">
            <input type="checkbox" checked={on(values.removeBackups)} onChange={(e) => set('removeBackups', flag(e.target.checked))} />
            <span>Delete the package from this server</span>
          </label>
        </Row>
      )}
    </Panel>
  );
}

function ScheduleSection({ values, set, errors, data }: Ctx) {
  const env = data.env;
  return (
    <>
      <Panel title="Automatic backups" intro="Packages can always be built from the Packages page. Choose how they are also built on their own.">
        <div className="dpa-choices" role="radiogroup" aria-label="Automatic backups">
          <Choice name="cronMode" value="none" current={values.cronMode} title="Off, or with your server's scheduler" detail="Nothing starts on its own. For automatic backups, add the command below to a cron job (crontab, cPanel, Plesk...)." onChange={(v) => set('cronMode', v)} />
          <Choice name="cronMode" value="LazyCron" current={values.cronMode} title="On site visits" detail="With the core module LazyCron: a page view starts the backup once the interval has passed." badge={env.lazyCron ? undefined : 'LazyCron not installed'} disabled={!env.lazyCron && values.cronMode !== 'LazyCron'} onChange={(v) => set('cronMode', v)} />
          <Choice name="cronMode" value="PWCron" current={values.cronMode} title="With the PwCron module" detail="PwCron runs the backup from its own cron job." badge={env.pwCron ? undefined : 'PwCron not installed'} disabled={!env.pwCron && values.cronMode !== 'PWCron'} onChange={(v) => set('cronMode', v)} />
        </div>
        {errors.cronMode?.map((e) => <p key={e} className="dpa-error">{e}</p>)}

        {values.cronMode === 'LazyCron' && (
          <Row label="Interval" htmlFor="s-cycle" errors={errors.cycle} help="How often a visit may start a backup.">
            <Select id="s-cycle" value={values.cycle} options={data.options.cycle ?? []} onChange={(v) => set('cycle', v)} />
          </Row>
        )}
        {values.cronMode === 'none' && (
          <div className="dpa-howto">
            <p>
              <strong>Cron command.</strong> Run it as the web server user, for example every night at 3:00 (<code>0 3 * * *</code>):
            </p>
            <Command>{`php ${env.cronScript}`}</Command>
          </div>
        )}
      </Panel>

      <Panel title="Retention" intro="Older packages are deleted after each new one, in every storage.">
        <Row label="Keep at most" htmlFor="s-maxPackages" errors={errors.maxPackages} help={`From 1 to ${env.maxPackages}.`}>
          <div className="dpa-with-suffix">
            <input id="s-maxPackages" type="number" min={1} max={env.maxPackages} className="is-sm" value={String(values.maxPackages ?? '')} onChange={(e) => set('maxPackages', e.target.value)} />
            <span>packages</span>
          </div>
        </Row>
        <Row label="Delete packages older than" htmlFor="s-deadline" errors={errors.deadline}>
          <Select id="s-deadline" value={values.deadline} options={(data.options.deadline ?? []).map((o) => ({ ...o, label: o.value === '' ? 'Never' : o.label.replace(/\s*\[warning!\]/i, ' (testing only)') }))} onChange={(v) => set('deadline', v)} />
        </Row>
      </Panel>
    </>
  );
}

function SecuritySection({ values, set, errors, data }: Ctx) {
  const env = data.env;
  const enabled = on(values.encryptionEnabled);
  const key = String(values.encryptionPublicKey ?? '').trim();
  const unchanged = key === String(data.values.encryptionPublicKey ?? '').trim();
  let keyState: ReactNode = null;
  if (key && !key.startsWith('dup-pk1:')) {
    keyState = <p className="dpa-error">{key.startsWith('dup-sk1:') ? 'This is the secret key. Paste the public key (dup-pk1:...) and keep the secret key off this server.' : 'A public key starts with dup-pk1:'}</p>;
  } else if (key && unchanged && env.publicKey === 'valid') {
    keyState = (
      <p className="dpa-ok-text">
        <Icon name="check" /> Valid public key
      </p>
    );
  } else if (key && unchanged && env.publicKey) {
    keyState = <p className="dpa-error">{env.publicKey}</p>;
  }

  return (
    <>
      <Panel title="Encryption" intro="Packages are encrypted before they are stored anywhere. The server only knows the public key: it can encrypt, never decrypt.">
        {!env.sodium && (
          <p className="dpa-inline-warn">
            <Icon name="alert" /> The PHP sodium extension is not enabled on this server. Enable <code>extension=sodium</code> in php.ini, or no package can be built while encryption is on.
          </p>
        )}
        <Row label="Encrypt packages" help="If a package cannot be encrypted, it is deleted. An unencrypted copy never leaves the server.">
          <Switch checked={enabled} label="Encrypt packages" onChange={(v) => set('encryptionEnabled', flag(v))} />
        </Row>
        {enabled && (
          <Row label="Public key" htmlFor="s-encryptionPublicKey" errors={errors.encryptionPublicKey} help="Starts with dup-pk1:">
            <Text id="s-encryptionPublicKey" value={values.encryptionPublicKey} placeholder="dup-pk1:..." mono onChange={(v) => set('encryptionPublicKey', v)} />
            {keyState}
          </Row>
        )}
      </Panel>

      <Panel title="Create your keys" intro="Do it once, on your own computer. It needs PHP 7.2 or newer installed on that computer.">
        <ol className="dpa-steps">
          <li>
            <strong>Download the encryption tool.</strong>
            <p>A single PHP file, safe to keep anywhere.</p>
            <a className="dpa-btn dpa-btn-secondary" href={env.dupcryptUrl}>
              <Icon name="download" /> Download dupcrypt.php
            </a>
          </li>
          <li>
            <strong>Run it in a terminal.</strong>
            <p>Open a terminal in the folder where you saved the file (Terminal on macOS and Linux, PowerShell on Windows) and run:</p>
            <Command>php dupcrypt.php keygen</Command>
            <p>It prints two keys: a public key starting with <code>dup-pk1:</code> and a secret key starting with <code>dup-sk1:</code>.</p>
          </li>
          <li>
            <strong>Paste the public key above</strong> and save.
          </li>
          <li>
            <strong>Store the secret key in your password manager.</strong>
            <p>It is the only way to restore an encrypted package. Never put it on this server: if it is lost, encrypted packages cannot be restored.</p>
          </li>
        </ol>
      </Panel>

      <Panel title="Restoring an encrypted package">
        <p className="dpa-panel-text">Upload the package and installer.php as usual: the installer asks for the secret key. You can also decrypt a package on your computer:</p>
        <Command>php dupcrypt.php decrypt my-site.package2.zip my-site-decrypted.zip</Command>
        <p className="dpa-panel-text dpa-muted">The tool asks for the secret key, and writes a regular package that the installer restores without a key.</p>
      </Panel>
    </>
  );
}

function AdvancedSection({ values, set, errors, data }: Ctx) {
  const env = data.env;
  const native = String(values.backup_mode) === '1';
  const checks: [string, boolean, string][] = [
    ['PHP zip extension', env.phpZip, 'Required to build packages.'],
    ['zip command', env.zipBinary, 'Optional. Faster archives with the native method.'],
    ['mysqldump', env.mysqldump, 'Optional. Needed by the native method.'],
    ['PHP sodium extension', env.sodium, 'Needed for encryption.'],
  ];
  return (
    <>
      <Panel title="Database dump" intro="How the database is exported into the package.">
        <div className="dpa-choices" role="radiogroup" aria-label="Database dump">
          <Choice name="backup_mode" value="0" current={String(values.backup_mode)} title="PHP" detail="Works on every server. Fine for most sites." onChange={(v) => set('backup_mode', v)} />
          <Choice name="backup_mode" value="1" current={String(values.backup_mode)} title="mysqldump" detail="Faster for large databases. Runs a shell script on the server." badge={env.mysqldump ? undefined : 'Not available on this server'} disabled={!env.mysqldump && !native} onChange={(v) => set('backup_mode', v)} />
        </div>
        {errors.backup_mode?.map((e) => <p key={e} className="dpa-error">{e}</p>)}
        {native && (
          <>
            <Row label="Script permissions" htmlFor="s-chmodPermission" errors={errors.chmodPermission} help="Applied to the generated script. 0700 lets only the web server user read and run it.">
              <Text id="s-chmodPermission" value={values.chmodPermission} placeholder="0700" mono width="sm" onChange={(v) => set('chmodPermission', v)} />
            </Row>
            <Row label="Dump script" htmlFor="s-shellScript" errors={errors.shellScript} align="start" help={<>Placeholders such as <code>%%DATABASE%%</code> are replaced when the script runs.</>}>
              <textarea id="s-shellScript" rows={12} className="is-mono" value={String(values.shellScript ?? '')} spellCheck={false} onChange={(e) => set('shellScript', e.target.value)} />
            </Row>
          </>
        )}
      </Panel>

      <Panel title="Large sites">
        <Row label="Write the archive every" htmlFor="s-zip_flush_mbytes" errors={errors.zip_flush_mbytes} help="Saves memory on very large sites. 0 writes it once at the end.">
          <div className="dpa-with-suffix">
            <input id="s-zip_flush_mbytes" type="number" min={0} className="is-sm" value={String(values.zip_flush_mbytes ?? '')} onChange={(e) => set('zip_flush_mbytes', e.target.value)} />
            <span>MB</span>
          </div>
        </Row>
      </Panel>

      <Panel title="This server">
        <ul className="dpa-checks">
          {checks.map(([label, ok, detail]) => (
            <li key={label} className={ok ? 'is-ok' : 'is-missing'}>
              <Icon name={ok ? 'check' : 'x'} />
              <span>
                <strong>{label}</strong> {detail}
              </span>
            </li>
          ))}
        </ul>
      </Panel>
    </>
  );
}
