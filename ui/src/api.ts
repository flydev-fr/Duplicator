// Talks to DupAdminApi (Classes/DupAdminApi.php) through setup/duplicator/api/

export interface Bootstrap {
  version: string;
  api: string;
  csrf: { name: string; value: string };
  urls: { self: string; logo: string; nativeSettings: string; support: string; repository: string };
  canEditSettings: boolean;
}

export type Service = 'local' | 'dropbox' | 'googledrive' | 'amazon';

export interface Package {
  service: Service;
  name: string;
  id: string;
  size: number;
  time: number;
  encrypted: boolean | null;
}

export interface LogLine {
  date: string;
  text: string;
}

export interface State {
  packages: Package[];
  errors: string[];
  storages: { local: boolean; ftp: boolean; dropbox: boolean; googledrive: boolean; amazon: boolean };
  encryption: boolean;
  retention: { maxPackages: number; deadline: string };
  schedule: { mode: string; cycle: string };
  log: LogLine[];
  canEditSettings: boolean;
}

export interface Option {
  value: string;
  label: string;
}

export interface SettingNode {
  type: string;
  label: string;
  description: string;
  notes: string;
  icon: string;
  showIf: string;
  children?: SettingNode[];
  html?: string;
  name?: string;
  value?: string | number | string[];
  options?: Option[];
  multiple?: boolean;
  required?: boolean;
  requiredIf?: string;
  columnWidth?: number;
  errors?: string[];
  secret?: boolean;
  hasValue?: boolean;
}

export type SettingValues = Record<string, string | number | string[]>;

export function readBootstrap(): Bootstrap {
  const el = document.getElementById('duplicator-bootstrap');
  if (!el?.textContent) throw new Error('Duplicator: missing bootstrap data');
  return JSON.parse(el.textContent) as Bootstrap;
}

export class ApiError extends Error {
  fieldErrors?: Record<string, string[]>;
}

export function createApi(boot: Bootstrap) {
  async function parse<T>(res: Response): Promise<T> {
    let data: any;
    try {
      data = await res.json();
    } catch {
      throw new ApiError(`Unexpected answer from the server (HTTP ${res.status})`);
    }
    if (!res.ok || data.error || data.ok === false) {
      const err = new ApiError(data.error || 'The operation failed');
      if (data.errors && !Array.isArray(data.errors)) err.fieldErrors = data.errors;
      throw err;
    }
    return data as T;
  }

  return {
    async get<T>(op: string): Promise<T> {
      const res = await fetch(`${boot.api}?op=${encodeURIComponent(op)}`, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      });
      return parse<T>(res);
    },
    async post<T>(op: string, fields: Record<string, string> = {}): Promise<T> {
      const body = new FormData();
      body.set('op', op);
      body.set(boot.csrf.name, boot.csrf.value);
      for (const [k, v] of Object.entries(fields)) body.set(k, v);
      const res = await fetch(boot.api, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        body,
      });
      return parse<T>(res);
    },
  };
}

export type Api = ReturnType<typeof createApi>;
