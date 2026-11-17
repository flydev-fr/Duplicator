import { useCallback, useEffect, useMemo, useState } from 'react';
import { ApiError, type Api, type Bootstrap, type SettingNode, type SettingValues } from './api';
import { Icon } from './icons';
import { Button, useToast } from './ui';
import { matchesCondition } from './util';

interface Section {
  id: string;
  label: string;
  description: string;
  showIf: string;
  nodes: SettingNode[];
}

/** Top-level fieldsets become sections; loose fields before them go to "General" */
function toSections(nodes: SettingNode[]): Section[] {
  const sections: Section[] = [];
  let loose: SettingNode[] = [];
  const flush = () => {
    if (loose.length) sections.push({ id: `general-${sections.length}`, label: 'General', description: '', showIf: '', nodes: loose });
    loose = [];
  };
  nodes.forEach((n, i) => {
    if (n.type === 'fieldset') {
      flush();
      sections.push({ id: `s${i}`, label: n.label.trim() || 'General', description: n.description, showIf: n.showIf, nodes: n.children ?? [] });
    } else {
      loose.push(n);
    }
  });
  flush();
  return sections.filter((s) => s.nodes.length);
}

function collectValues(nodes: SettingNode[], into: SettingValues = {}): SettingValues {
  for (const n of nodes) {
    if (n.children) collectValues(n.children, into);
    else if (n.name) into[n.name] = n.value ?? '';
  }
  return into;
}

function collectErrors(nodes: SettingNode[], into: Record<string, string[]> = {}) {
  for (const n of nodes) {
    if (n.children) collectErrors(n.children, into);
    else if (n.name && n.errors?.length) into[n.name] = n.errors;
  }
  return into;
}

export function SettingsView({ boot, api, onSaved }: { boot: Bootstrap; api: Api; onSaved: () => void }) {
  const toast = useToast();
  const [nodes, setNodes] = useState<SettingNode[] | null>(null);
  const [saved, setSaved] = useState<SettingValues>({});
  const [values, setValues] = useState<SettingValues>({});
  const [errors, setErrors] = useState<Record<string, string[]>>({});
  const [active, setActive] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const load = useCallback(
    (sections: SettingNode[]) => {
      const v = collectValues(sections);
      setNodes(sections);
      setSaved(v);
      setValues(v);
      setErrors(collectErrors(sections));
    },
    [],
  );

  useEffect(() => {
    api
      .get<{ sections: SettingNode[] }>('settings')
      .then((r) => load(r.sections))
      .catch((e) => setLoadError((e as Error).message));
  }, [api, load]);

  const sections = useMemo(() => (nodes ? toSections(nodes) : []), [nodes]);
  const visibleSections = sections.filter((s) => matchesCondition(s.showIf, values));
  const current = visibleSections.find((s) => s.id === active) ?? visibleSections[0];
  const dirty = useMemo(() => JSON.stringify(values) !== JSON.stringify(saved), [values, saved]);

  useEffect(() => {
    const warn = (e: BeforeUnloadEvent) => {
      if (dirty) e.preventDefault();
    };
    window.addEventListener('beforeunload', warn);
    return () => window.removeEventListener('beforeunload', warn);
  }, [dirty]);

  if (loadError) {
    return (
      <div className="dup-notice dup-notice-error" role="alert">
        <Icon name="alert" />
        <span>{loadError}</span>
      </div>
    );
  }
  if (!nodes) {
    return (
      <div className="dup-loading">
        <span className="dup-spinner" /> Loading settings…
      </div>
    );
  }

  const set = (name: string, value: string | number | string[]) => {
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
      const r = await api.post<{ sections: SettingNode[] }>('saveSettings', { settings: JSON.stringify(values) });
      load(r.sections);
      toast('ok', 'Settings saved.');
      onSaved();
    } catch (e) {
      const err = e as ApiError;
      if (err.fieldErrors) {
        setErrors(err.fieldErrors);
        const first = sections.find((s) => Object.keys(err.fieldErrors!).some((name) => JSON.stringify(s.nodes).includes(`"name":"${name}"`)));
        if (first) setActive(first.id);
        toast('error', 'Some settings need your attention.');
      } else {
        toast('error', err.message);
      }
    } finally {
      setSaving(false);
    }
  };

  const sectionHasError = (s: Section) => Object.keys(errors).some((name) => JSON.stringify(s.nodes).includes(`"name":"${name}"`));

  return (
    <div className="dup-settings">
      <nav className="dup-settings-nav" aria-label="Settings sections">
        {visibleSections.map((s) => (
          <button key={s.id} type="button" className={`dup-settings-link${s === current ? ' is-active' : ''}`} onClick={() => setActive(s.id)} aria-current={s === current ? 'true' : undefined}>
            <span>{s.label}</span>
            {sectionHasError(s) && <span className="dup-dot" aria-label="has errors" />}
          </button>
        ))}
        <a className="dup-settings-native" href={boot.urls.nativeSettings}>
          Classic settings page
        </a>
      </nav>

      <div className="dup-settings-panel">
        {current && (
          <>
            <h2>{current.label}</h2>
            {current.description && <p className="dup-muted" dangerouslySetInnerHTML={{ __html: current.description }} />}
            <Fields nodes={current.nodes} values={values} errors={errors} onChange={set} />
          </>
        )}
      </div>

      <div className={`dup-savebar${dirty ? ' is-visible' : ''}`} aria-hidden={!dirty}>
        <span>Unsaved changes</span>
        <div>
          <Button variant="ghost" onClick={() => { setValues(saved); setErrors({}); }} disabled={saving}>
            Discard
          </Button>
          <Button variant="primary" icon="check" busy={saving} onClick={save}>
            Save settings
          </Button>
        </div>
      </div>
    </div>
  );
}

function Fields({ nodes, values, errors, onChange }: { nodes: SettingNode[]; values: SettingValues; errors: Record<string, string[]>; onChange: (name: string, v: string | number | string[]) => void }) {
  return (
    <div className="dup-fields">
      {nodes.map((n, i) => {
        if (!matchesCondition(n.showIf, values)) return null;
        if (n.type === 'fieldset') {
          return (
            <fieldset key={i} className="dup-subsection">
              {n.label.trim() && <legend>{n.label}</legend>}
              {n.description && <p className="dup-muted" dangerouslySetInnerHTML={{ __html: n.description }} />}
              <Fields nodes={n.children ?? []} values={values} errors={errors} onChange={onChange} />
            </fieldset>
          );
        }
        if (n.type === 'markup') {
          return (
            <div key={i} className="dup-markup">
              {n.label.trim() && <div className="dup-field-label">{n.label}</div>}
              <div dangerouslySetInnerHTML={{ __html: n.html ?? '' }} />
              {n.notes && <p className="dup-field-notes" dangerouslySetInnerHTML={{ __html: n.notes }} />}
            </div>
          );
        }
        return <Field key={n.name ?? i} node={n} value={values[n.name!]} errors={errors[n.name!]} required={!!n.required || (!!n.requiredIf && matchesCondition(n.requiredIf, values))} onChange={(v) => onChange(n.name!, v)} />;
      })}
    </div>
  );
}

function Field({ node, value, errors, required, onChange }: { node: SettingNode; value: SettingValues[string] | undefined; errors?: string[]; required: boolean; onChange: (v: string | number | string[]) => void }) {
  const id = `dup-f-${node.name}`;
  const width = node.columnWidth && node.columnWidth < 100 ? `${node.columnWidth}%` : undefined;
  const describedBy = [node.description && `${id}-d`, node.notes && `${id}-n`, errors?.length && `${id}-e`].filter(Boolean).join(' ') || undefined;
  const common = { id, 'aria-describedby': describedBy, 'aria-invalid': errors?.length ? true : undefined };

  let control: React.ReactNode;
  switch (node.type) {
    case 'checkbox':
      return (
        <div className={`dup-field dup-field-check${errors?.length ? ' has-error' : ''}`} style={{ flexBasis: width }}>
          <label className="dup-switch">
            <input type="checkbox" {...common} checked={Number(value) === 1} onChange={(e) => onChange(e.target.checked ? 1 : 0)} />
            <span className="dup-switch-track" aria-hidden="true" />
            <span className="dup-field-label">{node.label}</span>
          </label>
          {node.description && <p id={`${id}-d`} className="dup-field-desc" dangerouslySetInnerHTML={{ __html: node.description }} />}
          {node.notes && <p id={`${id}-n`} className="dup-field-notes" dangerouslySetInnerHTML={{ __html: node.notes }} />}
          {errors?.map((e) => <p key={e} id={`${id}-e`} className="dup-field-error">{e}</p>)}
        </div>
      );
    case 'select':
    case 'radios':
      if (node.multiple) {
        const selected = Array.isArray(value) ? value : [];
        control = (
          <div className="dup-checklist" role="group" aria-labelledby={`${id}-l`}>
            {node.options?.map((o) => (
              <label key={o.value} className="dup-check">
                <input type="checkbox" checked={selected.includes(o.value)} onChange={(e) => onChange(e.target.checked ? [...selected, o.value] : selected.filter((v) => v !== o.value))} />
                <span>{o.label}</span>
              </label>
            ))}
          </div>
        );
      } else if (node.type === 'radios') {
        control = (
          <div className="dup-radios" role="radiogroup" aria-labelledby={`${id}-l`}>
            {node.options?.map((o) => (
              <label key={o.value} className="dup-check">
                <input type="radio" name={id} checked={String(value) === o.value} onChange={() => onChange(o.value)} />
                <span>{o.label}</span>
              </label>
            ))}
          </div>
        );
      } else {
        control = (
          <select {...common} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
            {node.options?.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        );
      }
      break;
    case 'textarea':
      control = node.secret ? (
        <textarea {...common} rows={4} value={String(value ?? '')} placeholder={node.hasValue ? 'Saved. Leave empty to keep it, or paste a new value.' : ''} onChange={(e) => onChange(e.target.value)} spellCheck={false} />
      ) : (
        <textarea {...common} rows={6} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} spellCheck={false} />
      );
      break;
    case 'integer':
      control = <input {...common} type="number" inputMode="numeric" value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} />;
      break;
    default:
      control = node.secret ? (
        <input {...common} type="password" autoComplete="new-password" value={String(value ?? '')} placeholder={node.hasValue ? '•••••••• saved, leave empty to keep' : ''} onChange={(e) => onChange(e.target.value)} />
      ) : (
        <input {...common} type="text" value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} spellCheck={false} />
      );
  }

  return (
    <div className={`dup-field${errors?.length ? ' has-error' : ''}`} style={{ flexBasis: width }}>
      <label id={`${id}-l`} htmlFor={id} className="dup-field-label">
        {node.label}
        {required && <span className="dup-required" aria-label="required">*</span>}
        {node.secret && node.hasValue && (
          <span className="dup-saved">
            <Icon name="key" /> saved
          </span>
        )}
      </label>
      {node.description && <p id={`${id}-d`} className="dup-field-desc" dangerouslySetInnerHTML={{ __html: node.description }} />}
      {control}
      {node.notes && <p id={`${id}-n`} className="dup-field-notes" dangerouslySetInnerHTML={{ __html: node.notes }} />}
      {errors?.map((e) => <p key={e} id={`${id}-e`} className="dup-field-error">{e}</p>)}
    </div>
  );
}
